<?php

namespace App\Services\Man;

use App\Models\Man\ManTaskAssignment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DyeingMachineQueueService
{
    public function assertCanRecord(int $staffId, int $machineId, string $stage, array $fabricIds): Collection
    {
        // Work controllers pass DyeingBatchFabric stages; assignments store
        // the corresponding staff role stage (for example, color -> dyeing_color).
        $assignmentStage = DyeingAssignmentBoardService::BATCH_STAGE_TO_ASSIGNMENT[$stage] ?? $stage;
        $queue = ManTaskAssignment::query()->where('department', 'dyeing')->active()
            ->where('stage', $assignmentStage)
            ->whereJsonContains('machine_ids', $machineId)
            ->orderByRaw('CASE WHEN queue_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('queue_position')
            ->orderBy('created_at')->orderBy('id')
            ->lockForUpdate()->get();
        $head = $queue->first();

        if ($head && (int) $head->assigned_to !== $staffId) {
            $name = $head->assignee?->name ?? 'another staff member';
            throw ValidationException::withMessages([
                'machine_id' => "This machine is in use by {$name}'s earlier assignment. It will be available after that JO is submitted to the supervisor.",
            ]);
        }

        $sameJobOrder = static function (ManTaskAssignment $assignment) use ($head): bool {
            if ($head?->manufacturing_order_id) {
                return (int) $assignment->manufacturing_order_id === (int) $head->manufacturing_order_id;
            }
            if ($head?->sales_order_id) {
                return (int) $assignment->sales_order_id === (int) $head->sales_order_id;
            }

            return $assignment->id === $head?->id;
        };
        $requestedFabricIds = array_map('intval', $fabricIds);
        $allowed = $queue->filter(fn (ManTaskAssignment $assignment) =>
            (int) $assignment->assigned_to === $staffId
            && $sameJobOrder($assignment)
            && in_array((int) $assignment->fabric_id, $requestedFabricIds, true)
        );
        if ($allowed->count() !== count($fabricIds)) {
            throw ValidationException::withMessages([
                'fabric_ids' => 'These fabrics are not part of your active assignment at the head of this machine queue.',
            ]);
        }

        return $allowed;
    }

    public function completeSubmitted(Collection $assignments): void
    {
        foreach ($assignments as $assignment) {
            $assignment->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }
}
