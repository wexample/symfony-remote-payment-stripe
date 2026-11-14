<?php

declare(strict_types=1);

namespace Wexample\SymfonyRemotePaymentStripe\Tests\Unit\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wexample\SymfonyRemotePayment\Enum\BalanceMovementType;
use Wexample\SymfonyRemotePayment\Enum\ProviderPaymentStatus;
use Wexample\SymfonyRemotePaymentStripe\Helper\StripeHelper;

final class StripeHelperTest extends TestCase
{
    public static function intentStatuses(): array
    {
        return [
            ['requires_action', ProviderPaymentStatus::RequiresAction],
            ['processing', ProviderPaymentStatus::Processing],
            ['requires_capture', ProviderPaymentStatus::Processing],
            ['succeeded', ProviderPaymentStatus::Succeeded],
            ['canceled', ProviderPaymentStatus::Canceled],
            ['requires_payment_method', ProviderPaymentStatus::Pending],
            ['requires_confirmation', ProviderPaymentStatus::Pending],
        ];
    }

    #[DataProvider('intentStatuses')]
    public function testIntentStatus(string $status, ProviderPaymentStatus $expected): void
    {
        $this->assertSame($expected, StripeHelper::mapIntentStatus($status));
    }

    public static function eventTypes(): array
    {
        return [
            ['payment_intent.succeeded', ProviderPaymentStatus::Succeeded],
            ['payment_intent.payment_failed', ProviderPaymentStatus::Failed],
            ['payment_intent.canceled', ProviderPaymentStatus::Canceled],
            ['payment_intent.processing', ProviderPaymentStatus::Processing],
            ['payment_intent.requires_action', ProviderPaymentStatus::RequiresAction],
            // Events about anything but the payment's status say nothing of it.
            ['payment_intent.created', null],
            ['charge.refunded', null],
            ['payout.paid', null],
        ];
    }

    #[DataProvider('eventTypes')]
    public function testEventType(string $type, ?ProviderPaymentStatus $expected): void
    {
        $this->assertSame($expected, StripeHelper::mapEventType($type));
    }

    public static function balanceTypes(): array
    {
        return [
            ['charge', null, BalanceMovementType::Charge],
            ['payment', null, BalanceMovementType::Charge],
            ['refund', null, BalanceMovementType::Refund],
            ['payment_refund', null, BalanceMovementType::Refund],
            ['refund_failure', null, BalanceMovementType::Refund],
            ['payment_failure_refund', null, BalanceMovementType::Refund],
            ['payout', null, BalanceMovementType::Payout],
            ['payout_cancel', null, BalanceMovementType::PayoutReversal],
            ['payout_failure', null, BalanceMovementType::PayoutReversal],
            ['stripe_fee', null, BalanceMovementType::Fee],
            ['application_fee', null, BalanceMovementType::Fee],
            ['tax_fee', null, BalanceMovementType::Fee],
            ['stripe_fx_fee', null, BalanceMovementType::Fee],
            ['adjustment', null, BalanceMovementType::Adjustment],
            ['transfer', null, BalanceMovementType::Other],
            // The reporting category wins over the type: a dispute's
            // adjustment or charge is a dispute.
            ['adjustment', 'dispute', BalanceMovementType::Dispute],
            ['charge', 'dispute_reversal', BalanceMovementType::Dispute],
            ['charge', 'charge', BalanceMovementType::Charge],
        ];
    }

    #[DataProvider('balanceTypes')]
    public function testBalanceType(string $type, ?string $reportingCategory, BalanceMovementType $expected): void
    {
        $this->assertSame($expected, StripeHelper::mapBalanceType($type, $reportingCategory));
    }

    public static function refundReasons(): array
    {
        return [
            ['duplicate', 'duplicate'],
            ['fraudulent', 'fraudulent'],
            ['requested_by_customer', 'requested_by_customer'],
            ['damaged_goods', null],
            ['', null],
            [null, null],
        ];
    }

    #[DataProvider('refundReasons')]
    public function testRefundReason(?string $reason, ?string $expected): void
    {
        $this->assertSame($expected, StripeHelper::mapRefundReason($reason));
    }
}
