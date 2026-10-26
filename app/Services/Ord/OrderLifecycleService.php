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

    /**
     * ORD must not self-approve a purchase order: approval belongs to ECO
     * credit review and the client / executive approval gates.
     */
    public const PO_TRANSITIONS = [
        'credit_review' => ['tier_assignment', 'pending_client_approval', 'on_hold', 'cancelled'],
        'tier_assignment' => ['pending_client_approval', 'on_hold', 'cancelled'],
        'pending_client_approval' => ['approved', 'on_hold', 'cancelled'],
        'approved' => ['released_to_production', 'on_hold', 'cancelled'],
        'production' => ['released_to_production', 'completed'],
        'released_to_production' => ['completed'],
        'on_hold' => ['credit_review', 'pending_client_approval', 'cancelled'],
        'rejected' => [],
        'completed' => [],
        'cancelled' => [],
    ];

    public const TERMINAL = ['completed', 'cancelled', 'rejected'];

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
            'cancelled', 'rejected' => 'cancelled',
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
        return DB::transaction(function () use ($order, $to, $actor, $notes) {
            $lockedOrder = SalesOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $allowed = self::allowedSoTransitions($lockedOrder->status);
            if (! in_array($to, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Cannot move sales order {$lockedOrder->jo_number} from '{$lockedOrder->status}' to '{$to}'. Allowed: ".implode(', ', $allowed)
                );
            }

            $from = $lockedOrder->status;
            $now = now();
            $extra = ['status' => $to];

            match ($to) {
                'confirmed' => [$extra['confirmed_at'] = $lockedOrder->confirmed_at ?? $now, $extra['confirmed_by'] = $lockedOrder->confirmed_by ?? $actor?->id],
                'in_production' => $extra['production_started_at'] = $now,
                'production_done' => $extra['production_done_at'] = $now,
                'delivered' => $extra['delivered_at'] = $now,
                'on_hold' => $extra['on_hold_reason'] = $notes ?? $lockedOrder->on_hold_reason ?? 'Put on hold by '.$actor?->name,
                'cancelled' => $extra['cancel_reason'] = $notes ?? $lockedOrder->cancel_reason ?? 'Cancelled by '.$actor?->name,
                default => null,
            };

            if ($to !== 'on_hold') {
                $extra['on_hold_reason'] = null;
            }

            $lockedOrder->update($extra);

            $this->recordHistory($lockedOrder, 'SO', $from, $to, $actor?->id, $notes);

            return $lockedOrder->fresh();
        }, 3);
    }

    /**
     * Guarded PO transition (same audit guarantees as SO).
     *
     * @throws InvalidArgumentException on illegal transition
     */
    public function transitionPurchaseOrder(PurchaseOrder $order, string $to, ?User $actor, ?string $notes = null): PurchaseOrder
    {
        $to = strtolower(trim($to));
        return DB::transaction(function () use ($order, $to, $actor, $notes) {
            $lockedOrder = PurchaseOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $allowed = self::allowedPoTransitions($lockedOrder->status);
            if (! in_array($to, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Cannot move purchase order {$lockedOrder->po_number} from '{$lockedOrder->status}' to '{$to}'. Allowed: ".implode(', ', $allowed)
                );
            }

            $from = $lockedOrder->status;
            $extra = ['status' => $to];

            if (in_array($to, ['approved', 'released_to_production'], true)) {
                $extra['confirmed_at'] = $lockedOrder->confirmed_at ?? now();
                $extra['confirmed_by'] = $lockedOrder->confirmed_by ?? $actor?->id;
            }
            if ($to === 'on_hold') {
                $extra['on_hold_reason'] = $notes ?? $lockedOrder->on_hold_reason ?? 'Put on hold by '.$actor?->name;
            } else {
                $extra['on_hold_reason'] = null;
            }
            if ($to === 'cancelled') {
                $extra['cancel_reason'] = $notes ?? $lockedOrder->cancel_reason ?? 'Cancelled by '.$actor?->name;
            }

            $lockedOrder->update($extra);

            $this->recordHistory($lockedOrder, 'PO', $from, $to, $actor?->id, $notes);

            return $lockedOrder->fresh();
        }, 3);
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

    /**
     * A control number is derived from the (unique) job-order number, so it
     * is deterministic and does not depend on the new row existing yet.
     */
    public function nextControlNumber(string $joNumber): string
    {
        $base = 'CTL-'.preg_replace('/[^A-Z0-9]/', '', strtoupper($joNumber));
        $candidate = $base.'-1';
        $suffix = 1;

        while (SalesOrder::where('control_number', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }

    public function nextReturnNumber(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $prefix = "RMA-{$year}-";
            $max = \App\Models\Ord\OrderReturn::where('return_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->max('return_number');
            $seq = $max ? ((int) substr($max, -4)) + 1 : 1;

            return $prefix.str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
