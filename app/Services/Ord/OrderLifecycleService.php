<?php

namespace App\Services\Ord;

use App\Models\Core\User;
use App\Models\Ord\OrderStatusHistory;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Central state machine for Monti Textile order management.
 *
 * Textile make-to-order pipeline (industry standard order-to-cash):
 *
 *   Sales Order (job order / JO):
 *     pending -> confirmed -> in_planning -> in_production
 *       -> production_done -> ready_for_dispatch -> in_transit
 *       -> delivered -> completed
 *     Side states: on_hold <-> (resume), cancelled (terminal), returned.
 *
 *   Purchase Order (client PO):
 *     credit_review -> pending_client_approval -> approved
 *       -> released_to_production -> completed
 *     Side states: on_hold, cancelled.
 *
 * Legacy statuses written by ECO / SCM / MAN (`pushed_to_ordermgmt`,
 * `pushed_to_scm`, `inv_check`, `inv_checked`, `production`, …) are
 * accepted as read aliases and normalised for display — the service never
 * rewrites history, it only appends guarded transitions with an audit row.
 */
class OrderLifecycleService
{
    /**
     * Canonical SO transitions: current => [allowed next …].
     */
    public const SO_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['in_planning', 'on_hold', 'cancelled'],
        'in_planning' => ['in_production', 'on_hold', 'cancelled'],
        'in_production' => ['production_done', 'on_hold'],
        'production_done' => ['ready_for_dispatch'],
        'ready_for_dispatch' => ['in_transit'],
        'in_transit' => ['delivered'],
        'delivered' => ['completed', 'returned'],
        'returned' => ['in_planning', 'completed'],
        'on_hold' => ['confirmed', 'in_planning', 'in_production', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Legacy SO statuses mapped to their canonical equivalent for
     * grouping/display. Kept so ECO/SCM/MAN writers keep working.
     */
    public const SO_ALIASES = [
        'pushed_to_ordermgmt' => 'confirmed',
        'pushed_to_scm' => 'in_planning',
        'inv_check' => 'in_planning',
        'inv_checked' => 'in_planning',
    ];

    public const PO_TRANSITIONS = [
        'credit_review' => ['tier_assignment', 'pending_client_approval', 'approved', 'on_hold', 'cancelled'],
        'tier_assignment' => ['pending_client_approval', 'approved', 'on_hold', 'cancelled'],
        'pending_client_approval' => ['approved', 'on_hold', 'cancelled'],
        'approved' => ['released_to_production', 'on_hold', 'cancelled'],
        'production' => ['released_to_production', 'completed'],
        'released_to_production' => ['completed'],
        'on_hold' => ['credit_review', 'pending_client_approval', 'approved', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public const TERMINAL = ['completed', 'cancelled'];

    /**
     * Normalise any stored status to its canonical form for grouping.
     */
    public static function canonicalSoStatus(?string $status): string
    {
        $status = (string) $status;

        return self::SO_ALIASES[$status] ?? $status;
    }

    /**
     * Pipeline group used by the ORD worklist / dashboard funnel.
     */
    public static function soGroup(?string $status): string
    {
        return match (self::canonicalSoStatus($status)) {
            'pending' => 'intake',
            'confirmed', 'in_planning' => 'confirmed',
            'in_production' => 'production',
            'production_done', 'ready_for_dispatch' => 'ready',
            'in_transit' => 'transit',
            'delivered', 'completed' => 'done',
            'on_hold', 'returned' => 'attention',
            'cancelled' => 'cancelled',
            default => 'intake',
        };
    }

    public static function poGroup(?string $status): string
    {
        return match ((string) $status) {
            'credit_review', 'tier_assignment', 'pending_client_approval' => 'intake',
            'approved' => 'confirmed',
            'production', 'released_to_production' => 'production',
            'completed' => 'done',
            'on_hold' => 'attention',
            'cancelled' => 'cancelled',
            default => 'intake',
        };
    }

    public static function allowedSoTransitions(?string $from): array
    {
        return self::SO_TRANSITIONS[self::canonicalSoStatus($from)] ?? [];
    }

    public static function allowedPoTransitions(?string $from): array
    {
        return self::PO_TRANSITIONS[(string) $from] ?? [];
    }

    /**
     * Guarded SO transition: validates, stamps milestone columns, writes
     * the audit row — all inside one transaction.
     *
     * @throws InvalidArgumentException on illegal transition
     */
    public function transitionSalesOrder(SalesOrder $order, string $to, ?User $actor, ?string $notes = null): SalesOrder
    {
        $to = strtolower(trim($to));
        $allowed = self::allowedSoTransitions($order->status);

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot move sales order {$order->jo_number} from '{$order->status}' to '{$to}'. Allowed: ".implode(', ', $allowed)
            );
        }

        return DB::transaction(function () use ($order, $to, $actor, $notes) {
            $from = $order->status;
            $now = now();
            $extra = ['status' => $to];

            match ($to) {
                'confirmed' => [$extra['confirmed_at'] = $order->confirmed_at ?? $now, $extra['confirmed_by'] = $order->confirmed_by ?? $actor?->id],
                'in_production' => $extra['production_started_at'] = $now,
                'production_done' => $extra['production_done_at'] = $now,
                'delivered' => $extra['delivered_at'] = $now,
                'on_hold' => $extra['on_hold_reason'] = $notes ?? $order->on_hold_reason ?? 'Put on hold by '.$actor?->name,
                'cancelled' => $extra['cancel_reason'] = $notes ?? $order->cancel_reason ?? 'Cancelled by '.$actor?->name,
                default => null,
            };

            if ($to !== 'on_hold') {
                $extra['on_hold_reason'] = null;
            }

            $order->update($extra);

            $this->recordHistory($order, 'SO', $from, $to, $actor?->id, $notes);

            return $order->fresh();
        });
    }

    /**
     * Guarded PO transition (same audit guarantees as SO).
     *
     * @throws InvalidArgumentException on illegal transition
     */
    public function transitionPurchaseOrder(PurchaseOrder $order, string $to, ?User $actor, ?string $notes = null): PurchaseOrder
    {
        $to = strtolower(trim($to));
        $allowed = self::allowedPoTransitions($order->status);

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot move purchase order {$order->po_number} from '{$order->status}' to '{$to}'. Allowed: ".implode(', ', $allowed)
            );
        }

        return DB::transaction(function () use ($order, $to, $actor, $notes) {
            $from = $order->status;
            $extra = ['status' => $to];

            if (in_array($to, ['approved', 'released_to_production'], true)) {
                $extra['confirmed_at'] = $order->confirmed_at ?? now();
                $extra['confirmed_by'] = $order->confirmed_by ?? $actor?->id;
            }
            if ($to === 'on_hold') {
                $extra['on_hold_reason'] = $notes ?? $order->on_hold_reason ?? 'Put on hold by '.$actor?->name;
            } else {
                $extra['on_hold_reason'] = null;
            }
            if ($to === 'cancelled') {
                $extra['cancel_reason'] = $notes ?? $order->cancel_reason ?? 'Cancelled by '.$actor?->name;
            }

            $order->update($extra);

            $this->recordHistory($order, 'PO', $from, $to, $actor?->id, $notes);

            return $order->fresh();
        });
    }

    /**
     * Append an audit row for a transition (also used to backfill history
     * for transitions performed by ECO / SCM / MAN).
     */
    public function recordHistory(PurchaseOrder|SalesOrder $order, string $type, ?string $from, string $to, ?int $actorId, ?string $notes = null): OrderStatusHistory
    {
        return OrderStatusHistory::create([
            'orderable_type' => $order::class,
            'orderable_id' => $order->id,
            'order_type' => $type,
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actorId,
            'notes' => $notes,
        ]);
    }

    /**
     * Yearly running document numbers: PO-2026-0001 / JO-2026-0001.
     * Uses a row lock so concurrent creators never collide.
     */
    public function nextPoNumber(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $prefix = "PO-{$year}-";
            $max = PurchaseOrder::where('po_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->max('po_number');
            $seq = $max ? ((int) substr($max, -4)) + 1 : 1;

            return $prefix.str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }

    public function nextJoNumber(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $prefix = "JO-{$year}-";
            $max = SalesOrder::where('jo_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->max('jo_number');
            $seq = $max ? ((int) substr($max, -4)) + 1 : 1;

            return $prefix.str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }

    public function nextControlNumber(string $joNumber): string
    {
        $count = SalesOrder::where('jo_number', $joNumber)->count() + 1;

        return 'CTL-'.preg_replace('/[^A-Z0-9]/', '', strtoupper($joNumber)).'-'.$count;
    }

    public function nextReturnNumber(): string
    {
        $year = now()->year;
        $prefix = "RMA-{$year}-";
        $max = \App\Models\Ord\OrderReturn::where('return_number', 'like', $prefix.'%')->max('return_number');
        $seq = $max ? ((int) substr($max, -4)) + 1 : 1;

        return $prefix.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
