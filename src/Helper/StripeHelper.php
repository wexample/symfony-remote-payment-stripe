<?php

namespace Wexample\SymfonyRemotePaymentStripe\Helper;

use Wexample\SymfonyRemotePayment\Enum\BalanceMovementType;
use Wexample\SymfonyRemotePayment\Enum\ProviderPaymentStatus;

/**
 * Stripe vocabulary to provider-agnostic values.
 */
class StripeHelper
{
    public static function mapIntentStatus(string $status): ProviderPaymentStatus
    {
        return match ($status) {
            'requires_action' => ProviderPaymentStatus::RequiresAction,
            'processing', 'requires_capture' => ProviderPaymentStatus::Processing,
            'succeeded' => ProviderPaymentStatus::Succeeded,
            'canceled' => ProviderPaymentStatus::Canceled,
            default => ProviderPaymentStatus::Pending,
        };
    }

    /**
     * The status a payment_intent.* webhook event announces, or null when the
     * event says nothing about the payment status.
     */
    public static function mapEventType(string $type): ?ProviderPaymentStatus
    {
        return match ($type) {
            'payment_intent.succeeded' => ProviderPaymentStatus::Succeeded,
            'payment_intent.payment_failed' => ProviderPaymentStatus::Failed,
            'payment_intent.canceled' => ProviderPaymentStatus::Canceled,
            'payment_intent.processing' => ProviderPaymentStatus::Processing,
            'payment_intent.requires_action' => ProviderPaymentStatus::RequiresAction,
            default => null,
        };
    }

    public static function mapBalanceType(
        string $type,
        ?string $reportingCategory = null
    ): BalanceMovementType {
        if (in_array($reportingCategory, ['dispute', 'dispute_reversal'], true)) {
            return BalanceMovementType::Dispute;
        }

        return match ($type) {
            'charge', 'payment' => BalanceMovementType::Charge,
            'refund', 'payment_refund', 'refund_failure', 'payment_failure_refund' => BalanceMovementType::Refund,
            'payout' => BalanceMovementType::Payout,
            'payout_cancel', 'payout_failure' => BalanceMovementType::PayoutReversal,
            'stripe_fee', 'application_fee', 'tax_fee', 'stripe_fx_fee' => BalanceMovementType::Fee,
            'adjustment' => BalanceMovementType::Adjustment,
            default => BalanceMovementType::Other,
        };
    }

    /**
     * Stripe only accepts three refund reasons; anything else goes to metadata.
     */
    public static function mapRefundReason(?string $reason): ?string
    {
        return in_array($reason, ['duplicate', 'fraudulent', 'requested_by_customer'], true) ? $reason : null;
    }
}
