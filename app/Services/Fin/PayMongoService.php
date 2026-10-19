<?php

namespace App\Services\Fin;

/**
 * Payment gateway layer for finance collections (PayMongo-ready).
 *
 * Modes:
 * - simulated (default): no PAYMONGO_SECRET_KEY configured. Charges are
 *   processed inline and always succeed with a simulated reference, so the
 *   full payment UX can be exercised end to end right now.
 * - live: set PAYMONGO_SECRET_KEY (+ PAYMONGO_PUBLIC_KEY,
 *   PAYMONGO_SANDBOX=false for production) and implement the redirect /
 *   webhook completion inside chargeLive() — the method contract already
 *   returns everything the ledger needs (success + reference).
 *
 * Online methods (gcash / maya / card) flow through charge(). Offline
 * methods (cash / bank_transfer / check) are recorded directly by the
 * caller without touching the gateway.
 */
class PayMongoService
{
    public const ONLINE_METHODS = ['gcash', 'maya', 'card'];

    /**
     * Payment methods offered on the PayMongo hosted checkout page. These
     * are PayMongo's own type names (Maya = "paymaya", GrabPay = "grab_pay").
     */
    public const CHECKOUT_METHOD_TYPES = ['card', 'gcash', 'paymaya', 'grab_pay'];

    public const METHOD_LABELS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'check' => 'Check',
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'card' => 'Card',
    ];

    public function isConfigured(): bool
    {
        return trim((string) config('services.paymongo.secret', env('PAYMONGO_SECRET_KEY', ''))) !== '';
    }

    public function mode(): string
    {
        return $this->isConfigured() ? 'live' : 'simulated';
    }

    public function isOnlineMethod(?string $method): bool
    {
        return in_array(strtolower((string) $method), self::ONLINE_METHODS, true);
    }

    public function label(?string $method): string
    {
        return self::METHOD_LABELS[strtolower((string) $method)] ?? (string) $method;
    }

    /**
     * Translate a PayMongo source/method type to our internal label.
     */
    public function normalizeMethod(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return match (strtolower($type)) {
            'paymaya' => 'maya',
            default => strtolower($type),
        };
    }

    /**
     * Create a PayMongo hosted Checkout Session ("PayMongo UI") and return its
     * redirect URL. The customer completes payment on PayMongo's own page and
     * is then redirected to success_url / cancel_url.
     *
     * @return array{success:bool, session_id:?string, checkout_url:?string, message:?string, simulated:bool}
     */
    public function createCheckoutSession(float $amount, array $meta = []): array
    {
        if ($amount <= 0) {
            return $this->checkoutError('Checkout amount must be greater than zero.');
        }

        if (! $this->isConfigured()) {
            return $this->checkoutError('PayMongo is not configured. Add PAYMONGO_SECRET_KEY to your .env file.');
        }

        $types = $meta['payment_method_types'] ?? self::CHECKOUT_METHOD_TYPES;

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth(
                (string) config('services.paymongo.secret', env('PAYMONGO_SECRET_KEY')), ''
            )->acceptJson()->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'description' => $meta['description'] ?? 'Monti payment',
                        'reference_number' => $meta['reference_number'] ?? null,
                        'metadata' => $meta['metadata'] ?? [],
                        'payment_method_types' => array_values($types),
                        'line_items' => [[
                            'currency' => 'PHP',
                            'amount' => (int) round($amount * 100),
                            'name' => $meta['item_name'] ?? 'Payment',
                            'description' => $meta['item_description'] ?? 'Payment',
                            'quantity' => 1,
                        ]],
                        'success_url' => $meta['success_url'] ?? route('fin.manager.payables'),
                        'cancel_url' => $meta['cancel_url'] ?? route('fin.manager.payables'),
                    ],
                ],
            ]);

            if (! $response->successful()) {
                return $this->checkoutError(
                    $response->json('errors.0.detail') ?: ('Gateway declined the checkout (' . $response->status() . ').')
                );
            }

            $session = $response->json('data', []);

            return [
                'success' => true,
                'session_id' => $session['id'] ?? null,
                'checkout_url' => $session['attributes']['checkout_url'] ?? null,
                'message' => null,
                'simulated' => false,
            ];
        } catch (\Throwable $e) {
            return $this->checkoutError('Gateway unreachable: ' . $e->getMessage());
        }
    }

    /**
     * Verify a Checkout Session against PayMongo and report whether it was
     * paid, plus the method / payment reference used. This is the local-,
     * webhook-free completion path used on the success return URL.
     *
     * @return array{paid:bool, method:?string, payment_id:?string, amount:?float, status:?string, message:?string}
     */
    public function verifyCheckoutSession(?string $sessionId): array
    {
        $blank = ['paid' => false, 'method' => null, 'payment_id' => null, 'amount' => null, 'status' => null, 'message' => null];

        if (! $sessionId || ! $this->isConfigured()) {
            return $blank + ['message' => 'Checkout session unavailable.'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth(
                (string) config('services.paymongo.secret', env('PAYMONGO_SECRET_KEY')), ''
            )->acceptJson()->get('https://api.paymongo.com/v1/checkout_sessions/' . $sessionId);

            if (! $response->successful()) {
                return $blank + ['message' => 'Could not verify the checkout session (' . $response->status() . ').'];
            }

            $attrs = $response->json('data.attributes', []);

            foreach (($attrs['payments'] ?? []) as $payment) {
                $pa = $payment['attributes'] ?? [];
                if (($pa['status'] ?? null) === 'paid') {
                    return [
                        'paid' => true,
                        'method' => $this->normalizeMethod($pa['source']['type'] ?? null),
                        'payment_id' => $payment['id'] ?? null,
                        'amount' => isset($pa['amount']) ? ((float) $pa['amount'] / 100) : null,
                        'status' => 'paid',
                        'message' => null,
                    ];
                }
            }

            $intent = $attrs['payment_intent']['attributes'] ?? [];
            if (($intent['status'] ?? null) === 'succeeded') {
                return [
                    'paid' => true,
                    'method' => $this->normalizeMethod($intent['source']['type'] ?? null),
                    'payment_id' => $attrs['payment_intent']['id'] ?? null,
                    'amount' => isset($intent['amount']) ? ((float) $intent['amount'] / 100) : null,
                    'status' => 'succeeded',
                    'message' => null,
                ];
            }

            return $blank + ['status' => $attrs['status'] ?? null];
        } catch (\Throwable $e) {
            return $blank + ['message' => 'Gateway unreachable: ' . $e->getMessage()];
        }
    }

    protected function checkoutError(string $message): array
    {
        return ['success' => false, 'session_id' => null, 'checkout_url' => null, 'message' => $message, 'simulated' => false];
    }

    /**
     * Process an online charge.
     *
     * @return array{success:bool, reference:?string, message:?string, simulated:bool}
     */
    public function charge(float $amount, string $method, array $meta = []): array
    {
        $method = strtolower($method);

        if (! $this->isOnlineMethod($method)) {
            return ['success' => false, 'reference' => null, 'message' => 'Unsupported online method.', 'simulated' => false];
        }

        if ($amount <= 0) {
            return ['success' => false, 'reference' => null, 'message' => 'Charge amount must be greater than zero.', 'simulated' => false];
        }

        if (! $this->isConfigured()) {
            return [
                'success' => true,
                'reference' => 'PM-SIM-' . strtoupper(substr(md5(uniqid($method . $amount, true)), 0, 10)),
                'message' => null,
                'simulated' => true,
            ];
        }

        return $this->chargeLive($amount, $method, $meta);
    }

    /**
     * Live PayMongo charge (Sources API: gcash / maya). Cards need the
     * Payment Intents + webhook completion flow — wire it here when the
     * frontend redirect step is built.
     *
     * @return array{success:bool, reference:?string, message:?string, simulated:bool}
     */
    protected function chargeLive(float $amount, string $method, array $meta = []): array
    {
        $type = $method === 'maya' ? 'grab_paymaya' : 'gcash';

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth(
                (string) config('services.paymongo.secret', env('PAYMONGO_SECRET_KEY')), ''
            )->post('https://api.paymongo.com/v1/sources', [
                'data' => [
                    'attributes' => [
                        'amount' => (int) round($amount * 100),
                        'currency' => 'PHP',
                        'type' => $type,
                        'redirect' => [
                            'success' => $meta['success_url'] ?? route('fin.manager.dashboard'),
                            'failed' => $meta['failed_url'] ?? route('fin.manager.dashboard'),
                        ],
                    ],
                ],
            ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'reference' => null,
                    'message' => 'Gateway declined the charge (' . $response->status() . ').',
                    'simulated' => false,
                ];
            }

            $source = $response->json('data', []);

            return [
                'success' => true,
                'reference' => $source['id'] ?? null,
                'checkout_url' => $source['attributes']['redirect']['checkout_url'] ?? null,
                'message' => null,
                'simulated' => false,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'reference' => null,
                'message' => 'Gateway unreachable: ' . $e->getMessage(),
                'simulated' => false,
            ];
        }
    }
}
