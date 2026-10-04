<?php

namespace Wexample\SymfonyRemotePaymentStripe\Class;

use DateTimeImmutable;
use DateTimeInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Wexample\PhpRemote\Class\RemoteStatus;
use Wexample\PhpRemote\Interface\RemoteInterface;
use Wexample\SymfonyRemotePayment\Class\BalanceMovement;
use Wexample\SymfonyRemotePayment\Class\PaymentInitiation;
use Wexample\SymfonyRemotePayment\Class\PaymentNotification;
use Wexample\SymfonyRemotePayment\Class\PaymentRequest;
use Wexample\SymfonyRemotePayment\Class\ProviderPayment;
use Wexample\SymfonyRemotePayment\Class\ProviderRefund;
use Wexample\SymfonyRemotePayment\Enum\ProviderPaymentStatus;
use Wexample\SymfonyRemotePayment\Exception\InvalidWebhookException;
use Wexample\SymfonyRemotePayment\Exception\PaymentProviderException;
use Wexample\SymfonyRemotePayment\Interface\BalanceReaderInterface;
use Wexample\SymfonyRemotePayment\Interface\PaymentGatewayInterface;
use Wexample\SymfonyRemotePayment\Interface\WebhookParserInterface;
use Wexample\SymfonyRemotePaymentStripe\Helper\StripeHelper;

/**
 * Stripe, through PaymentIntents: collects payments, reads webhooks, and reads
 * the balance transactions that accounting books (gross, fees, payouts).
 */
class StripePaymentProvider implements PaymentGatewayInterface, WebhookParserInterface, BalanceReaderInterface, RemoteInterface
{
    private ?StripeClient $client = null;

    /**
     * @param list<string> $methods Stripe payment method types this app accepts.
     */
    public function __construct(
        private readonly ?string $secretKey,
        private readonly ?string $webhookSecret = null,
        private readonly array $methods = ['card'],
        private readonly string $name = 'stripe',
        private readonly int $webhookTolerance = 300,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKey(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return 'Stripe';
    }

    public function checkStatus(): RemoteStatus
    {
        if (! $this->secretKey) {
            return RemoteStatus::unconfigured('No Stripe secret key.');
        }

        $this->getClient()->balance->retrieve();

        return RemoteStatus::up();
    }

    public function supports(string $method): bool
    {
        return in_array($method, $this->methods, true);
    }

    public function initiate(PaymentRequest $request): PaymentInitiation
    {
        $params = array_filter([
            'amount' => $request->amount,
            'currency' => strtolower($request->currencyCode),
            'payment_method_types' => [$request->method],
            'description' => $request->description,
            'receipt_email' => $request->customerEmail,
            'metadata' => ['reference' => $request->reference] + $request->metadata,
        ], fn ($value) => null !== $value);

        $intent = $this->call(fn () => $this->getClient()->paymentIntents->create(
            $params,
            $request->idempotencyKey ? ['idempotency_key' => $request->idempotencyKey] : []
        ));

        return new PaymentInitiation(
            providerReference: $intent->id,
            status: StripeHelper::mapIntentStatus($intent->status),
            clientSecret: $intent->client_secret,
            redirectUrl: $intent->next_action?->redirect_to_url?->url ?? null,
        );
    }

    public function fetch(string $providerReference): ProviderPayment
    {
        $intent = $this->call(fn () => $this->getClient()->paymentIntents->retrieve(
            $providerReference,
            ['expand' => ['latest_charge']]
        ));

        return $this->toProviderPayment($intent);
    }

    public function cancel(string $providerReference): ProviderPayment
    {
        return $this->toProviderPayment(
            $this->call(fn () => $this->getClient()->paymentIntents->cancel($providerReference))
        );
    }

    public function refund(
        string $providerReference,
        ?int $amount = null,
        ?string $reason = null
    ): ProviderRefund {
        $refund = $this->call(fn () => $this->getClient()->refunds->create(array_filter([
            'payment_intent' => $providerReference,
            'amount' => $amount,
            'reason' => StripeHelper::mapRefundReason($reason),
            'metadata' => $reason ? ['reason' => $reason] : null,
        ], fn ($value) => null !== $value)));

        $status = match ($refund->status) {
            'succeeded' => null === $amount ? ProviderPaymentStatus::Refunded : ProviderPaymentStatus::PartiallyRefunded,
            'failed', 'canceled' => ProviderPaymentStatus::Failed,
            default => ProviderPaymentStatus::Pending,
        };

        return new ProviderRefund($refund->id, $providerReference, $refund->amount, strtoupper($refund->currency), $status);
    }

    public function parseWebhook(
        string $payload,
        array $headers
    ): ?PaymentNotification {
        if (! $this->webhookSecret) {
            throw new InvalidWebhookException('No Stripe webhook secret configured.');
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $headers['stripe-signature'] ?? '',
                $this->webhookSecret,
                $this->webhookTolerance
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $exception) {
            throw new InvalidWebhookException($exception->getMessage(), 0, $exception);
        }

        $status = StripeHelper::mapEventType($event->type);

        if (null === $status) {
            return null;
        }

        $intent = $event->data->object;
        $charge = $intent->latest_charge ?? null;

        return new PaymentNotification(
            provider: $this->name,
            eventId: $event->id,
            providerReference: $intent->id,
            status: $status,
            amountReceived: $intent->amount_received ?? null,
            chargeReference: is_string($charge) ? $charge : ($charge->id ?? null),
            failureMessage: $intent->last_payment_error->message ?? null,
            reference: $intent->metadata['reference'] ?? null,
        );
    }

    public function readBalance(
        DateTimeInterface $from,
        ?DateTimeInterface $to = null
    ): iterable {
        $created = ['gte' => $from->getTimestamp()];
        if ($to) {
            $created['lte'] = $to->getTimestamp();
        }

        $page = $this->call(fn () => $this->getClient()->balanceTransactions->all([
            'created' => $created,
            'limit' => 100,
            'expand' => ['data.source'],
        ]));

        $movements = [];
        foreach ($page->autoPagingIterator() as $transaction) {
            $movements[] = $this->toMovement($transaction);
        }

        // Stripe lists newest first.
        usort($movements, fn (BalanceMovement $a, BalanceMovement $b) => [$a->dateCreated, $a->externalId] <=> [$b->dateCreated, $b->externalId]);

        return $movements;
    }

    public function getClient(): StripeClient
    {
        if (! $this->secretKey) {
            throw new PaymentProviderException('Stripe is not configured: no secret key.');
        }

        return $this->client ??= new StripeClient($this->secretKey);
    }

    private function toMovement(object $transaction): BalanceMovement
    {
        $source = $transaction->source ?? null;
        $sourceId = is_string($source) ? $source : ($source->id ?? null);
        $paymentIntent = is_object($source) ? ($source->payment_intent ?? null) : null;

        if (is_object($source) && 'refund' === ($source->object ?? null)) {
            $paymentIntent = $source->payment_intent ?? null;
        }

        return new BalanceMovement(
            externalId: $transaction->id,
            type: StripeHelper::mapBalanceType($transaction->type, $transaction->reporting_category ?? null),
            amount: (int) $transaction->amount,
            fee: (int) $transaction->fee,
            currencyCode: strtoupper($transaction->currency),
            dateCreated: (new DateTimeImmutable())->setTimestamp((int) $transaction->created),
            dateAvailable: isset($transaction->available_on)
                ? (new DateTimeImmutable())->setTimestamp((int) $transaction->available_on)
                : null,
            description: $transaction->description ?? null,
            sourceReference: $sourceId,
            paymentReference: is_string($paymentIntent) ? $paymentIntent : ($paymentIntent->id ?? null),
            data: ['reporting_category' => $transaction->reporting_category ?? null],
        );
    }

    private function toProviderPayment(object $intent): ProviderPayment
    {
        $charge = $intent->latest_charge ?? null;
        $status = StripeHelper::mapIntentStatus($intent->status);
        $refunded = is_object($charge) ? (int) ($charge->amount_refunded ?? 0) : 0;

        if (ProviderPaymentStatus::Succeeded === $status && $refunded > 0) {
            $status = $refunded >= $intent->amount_received
                ? ProviderPaymentStatus::Refunded
                : ProviderPaymentStatus::PartiallyRefunded;
        }

        if (ProviderPaymentStatus::Pending === $status && isset($intent->last_payment_error)) {
            $status = ProviderPaymentStatus::Failed;
        }

        return new ProviderPayment(
            providerReference: $intent->id,
            status: $status,
            amount: (int) $intent->amount,
            currencyCode: strtoupper($intent->currency),
            amountReceived: (int) ($intent->amount_received ?? 0),
            amountRefunded: $refunded,
            chargeReference: is_string($charge) ? $charge : ($charge->id ?? null),
            method: $intent->payment_method_types[0] ?? null,
            datePaid: is_object($charge) && ($charge->paid ?? false)
                ? (new DateTimeImmutable())->setTimestamp((int) $charge->created)
                : null,
            failureMessage: $intent->last_payment_error->message ?? null,
        );
    }

    /**
     * @template T
     * @param callable(): T $call
     * @return T
     */
    private function call(callable $call): mixed
    {
        try {
            return $call();
        } catch (ApiErrorException $exception) {
            throw new PaymentProviderException('Stripe: '.$exception->getMessage(), (int) $exception->getHttpStatus(), $exception);
        }
    }
}
