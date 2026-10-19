<?php

namespace App\Support;

/**
 * Shared human-readable payment method labels used on receipts across the
 * finance ledger and the supplier portal.
 */
class PaymentMethodLabel
{
    public static function label(?string $method): string
    {
        $key = strtolower(trim((string) $method));

        return match ($key) {
            'gcash' => 'GCash',
            'maya', 'paymaya' => 'Maya',
            'card' => 'Card',
            'grab_pay', 'grabpay' => 'GrabPay',
            'bank_transfer', 'bank transfer' => 'Bank transfer',
            'check', 'cheque' => 'Check',
            'cash' => 'Cash',
            'paymongo' => 'PayMongo',
            '', 'null' => 'Cash',
            default => ucwords(str_replace(['_', '-'], ' ', $key)),
        };
    }
}
