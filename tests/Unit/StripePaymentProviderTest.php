<?php

namespace Wexample\SymfonyRemotePaymentStripe\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Wexample\SymfonyRemotePayment\Class\PaymentRequest;
use Wexample\SymfonyRemotePayment\Enum\BalanceMovementType;
use Wexample\SymfonyRemotePayment\Enum\ProviderPaymentStatus;
use Wexample\SymfonyRemotePayment\Exception\InvalidWebhookException;
use Wexample\SymfonyRemotePayment\Exception\PaymentProviderException;
use Wexample\SymfonyRemotePaymentStripe\Class\StripePaymentProvider;
use Wexample\SymfonyRemotePaymentStripe\Tests\Fixtures\FakeStripeHttpClient;

class StripePaymentProviderTest extends TestCase
{
    private FakeStripeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->http);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    private function provider(): StripePaymentProvider
    {
        return new StripePaymentProvider('sk_test_x', 'whsec_test', ['card', 'sepa_debit']);
    }

    public function testInitiate(): void
    {
        $this->http->queue('POST', '/v1/payment_intents', [
            'id' => 'pi_1', 'object' => 'payment_intent', 'status' => 'requires_payment_method',
            'client_secret' => 'pi_1_secret', 'amount' => 2500, 'currency' => 'eur',
        ]);

        $initiation = $this->provider()->initiate(new PaymentRequest('pay-1', 2500, 'EUR', 'card', idempotencyKey: 'k1'));

        $this->assertSame('pi_1', $initiation->providerReference);
        $this->assertSame(ProviderPaymentStatus::Pending, $initiation->status);
        $this->assertSame('pi_1_secret', $initiation->clientSecret);
        $call = $this->http->calls[0];
        $this->assertSame('eur', $call['params']['currency']);
        $this->assertSame('pay-1', $call['params']['metadata']['reference']);
        $this->assertContains('Idempotency-Key: k1', $call['headers']);
    }

    public function testFetchDetectsPartialRefund(): void
    {
        $this->http->queue('GET', '/v1/payment_intents/pi_1', [
            'id' => 'pi_1', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => 2500,
            'amount_received' => 2500, 'currency' => 'eur', 'payment_method_types' => ['card'],
            'latest_charge' => ['id' => 'ch_1', 'object' => 'charge', 'paid' => true, 'created' => 1767225600, 'amount_refunded' => 500],
        ]);

        $payment = $this->provider()->fetch('pi_1');

        $this->assertSame(ProviderPaymentStatus::PartiallyRefunded, $payment->status);
        $this->assertSame('ch_1', $payment->chargeReference);
        $this->assertSame(500, $payment->amountRefunded);
        $this->assertSame('2026-01-01', $payment->datePaid->format('Y-m-d'));
    }

    public function testApiErrorsAreWrapped(): void
    {
        $this->expectException(PaymentProviderException::class);
        $this->provider()->fetch('pi_missing');
    }

    public function testBalanceIsReadOldestFirst(): void
    {
        $this->http->queue('GET', '/v1/balance_transactions', [
            'object' => 'list', 'has_more' => false, 'url' => '/v1/balance_transactions',
            'data' => [
                ['id' => 'txn_3', 'object' => 'balance_transaction', 'type' => 'payout', 'amount' => -9625, 'fee' => 0,
                    'currency' => 'eur', 'created' => 1767400000, 'source' => 'po_1', 'reporting_category' => 'payout'],
                ['id' => 'txn_2', 'object' => 'balance_transaction', 'type' => 'stripe_fee', 'amount' => -200, 'fee' => 0,
                    'currency' => 'eur', 'created' => 1767300000, 'source' => null, 'reporting_category' => 'fee'],
                ['id' => 'txn_1', 'object' => 'balance_transaction', 'type' => 'charge', 'amount' => 10000, 'fee' => 175,
                    'currency' => 'eur', 'created' => 1767225600, 'description' => 'Order 12',
                    'source' => ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_1'], 'reporting_category' => 'charge'],
            ],
        ]);

        $movements = $this->provider()->readBalance(new DateTimeImmutable('2026-01-01'));

        $this->assertSame(['txn_1', 'txn_2', 'txn_3'], array_map(fn ($m) => $m->externalId, $movements));
        $this->assertSame(BalanceMovementType::Charge, $movements[0]->type);
        $this->assertSame(9825, $movements[0]->getNet());
        $this->assertSame('pi_1', $movements[0]->paymentReference);
        $this->assertSame('ch_1', $movements[0]->sourceReference);
        $this->assertSame(BalanceMovementType::Fee, $movements[1]->type);
        $this->assertSame(BalanceMovementType::Payout, $movements[2]->type);
        $this->assertSame('EUR', $movements[2]->currencyCode);
    }

    public function testWebhook(): void
    {
        $payload = json_encode([
            'id' => 'evt_1', 'object' => 'event', 'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_1', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount_received' => 2500,
                'latest_charge' => 'ch_1', 'metadata' => ['reference' => 'pay-1'],
            ]],
        ]);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $notification = $this->provider()->parseWebhook($payload, ['stripe-signature' => $signature]);

        $this->assertSame('evt_1', $notification->eventId);
        $this->assertSame(ProviderPaymentStatus::Succeeded, $notification->status);
        $this->assertSame('pay-1', $notification->reference);
        $this->assertSame('ch_1', $notification->chargeReference);

        $other = json_encode(['id' => 'evt_2', 'object' => 'event', 'type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1']]]);
        $this->assertNull($this->provider()->parseWebhook($other, ['stripe-signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$other, 'whsec_test')]));

        $this->expectException(InvalidWebhookException::class);
        $this->provider()->parseWebhook($payload, ['stripe-signature' => 't='.$timestamp.',v1=forged']);
    }

    public function testUnconfigured(): void
    {
        $provider = new StripePaymentProvider(null);
        $this->assertSame('unconfigured', $provider->checkStatus()->state->value);
        $this->assertTrue($provider->supports('card'));
        $this->assertFalse($provider->supports('sepa_debit'));
    }
}
