<?php

namespace App\Services\Man;

use App\Models\Man\DyeingBatch;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\DyeingRemakeRequest;
use App\Models\Man\ManTaskAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DyeingReplacementReleaseService
{
    /** Approve a replacement into Dyeing and attach it to the original JO. */
    public function release(int $remakeId, int $actorId, array $allowedStatuses = ['ready_for_dyeing']): void
    {
        DB::transaction(function () use ($remakeId, $actorId, $allowedStatuses) {
            $remake = DyeingRemakeRequest::with(['batch', 'salesOrder', 'fabrics.originalFabric', 'fabrics.replacementFabric'])
                ->lockForUpdate()->findOrFail($remakeId);
            if (! in_array($remake->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages(['replacement' => 'This replacement is not waiting for supervisor approval.']);
            }

            $pairs = $remake->fabrics;
            if ($pairs->isEmpty() || $pairs->contains(fn ($pair) => ! $pair->replacement_fabric_id
                || $pair->replacementFabric?->status !== 'knitting_approved'
                || ! $pair->originalFabric)) {
                throw ValidationException::withMessages(['replacement' => 'Every replacement fabric must be inspector-approved before it can return to Dyeing.']);
            }

            $originalBatch = $remake->batch;
            if (! $originalBatch) {
                throw ValidationException::withMessages(['replacement' => 'The original Dyeing batch could not be found.']);
            }

            $weight = (float) $pairs->sum(fn ($pair) => (float) $pair->replacementFabric->weight);
            if ($weight > (float) $originalBatch->load_capacity_kg + 0.001) {
                throw ValidationException::withMessages(['replacement' => 'Replacement fabric weight exceeds the original Dyeing machine capacity.']);
            }

            $newBatch = DyeingBatch::create([
                'code' => 'DB-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'manufacturing_order_id' => $remake->manufacturing_order_id,
                'sales_order_id' => $remake->sales_order_id,
                'machine_id' => $originalBatch->machine_id,
                'assigned_to' => $originalBatch->assigned_to,
                'assigned_by' => $actorId,
                'color' => $originalBatch->color,
                'load_capacity_kg' => $originalBatch->load_capacity_kg,
                'total_weight_kg' => $weight,
                'status' => 'queued',
            ]);

            foreach ($pairs as $pair) {
                $oldItem = DyeingBatchFabric::where('fabric_id', $pair->original_fabric_id)->lockForUpdate()->firstOrFail();
                $oldItem->update([
                    'status' => 'replaced',
                    'replacement_fabric_id' => $pair->replacement_fabric_id,
                ]);
                $pair->originalFabric->update([
                    'status' => 'rejected',
                    'rejection_reason' => 'Replaced by '.$pair->replacementFabric->code.' under remake '.$remake->code,
                ]);

                $firstStage = $oldItem->requires_bleaching ? 'bleaching' : 'pre_treatment';
                DyeingBatchFabric::create([
                    'dyeing_batch_id' => $newBatch->id,
                    'fabric_id' => $pair->replacement_fabric_id,
                    'requires_bleaching' => $oldItem->requires_bleaching,
                    'current_stage' => $firstStage,
                    'status' => 'active',
                ]);
                $pair->replacementFabric->update([
                    'status' => $firstStage === 'bleaching' ? 'bleaching' : 'dyeing',
                    'machine_id' => $originalBatch->machine_id,
                ]);
            }

            $remake->update(['status' => 'completed']);
            if (! $originalBatch->fabrics()->whereNotIn('status', ['completed', 'replaced'])->exists()) {
                $originalBatch->update(['status' => 'completed']);
            }

            ManTaskAssignment::where('dyeing_remake_request_id', $remake->id)->active()->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $remake->salesOrder?->update([
                'knitting_done_at' => now(),
                'knitting_done_by' => $actorId,
            ]);
        });
    }
}
