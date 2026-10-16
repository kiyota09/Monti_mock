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
