<?php

namespace Tests\Unit;

use App\Models\Man\ManTaskAssignment;
use App\Services\Man\DyeingAssignmentBoardService;
use App\Services\Man\DyeingMachineQueueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DyeingMachineQueueServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('man_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manufacturing_order_id')->nullable();
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->unsignedBigInteger('fabric_id')->nullable();
            $table->string('department');
            $table->string('stage');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('queue_position')->nullable();
            $table->json('machine_ids')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('man_task_assignments');

        parent::tearDown();
    }

    public function test_each_work_stage_resolves_the_matching_active_assignment(): void
    {
        foreach (DyeingAssignmentBoardService::BATCH_STAGE_TO_ASSIGNMENT as $batchStage => $assignmentStage) {
            ManTaskAssignment::query()->delete();
            $this->assignment($assignmentStage, 41, 501, 901, 0);

            $result = app(DyeingMachineQueueService::class)
                ->assertCanRecord(41, 7, $batchStage, [901]);

            self::assertCount(1, $result, "Batch stage {$batchStage} should resolve assignment stage {$assignmentStage}.");
        }
    }

    public function test_queue_check_ignores_other_stages_and_uses_supervisor_queue_order(): void
    {
        $this->assignment('dyeing_pre_treatment', 99, 600, 902, 0);
        $this->assignment('dyeing_color', 42, 502, 903, 8);
        $head = $this->assignment('dyeing_color', 41, 501, 901, 2);

        $result = app(DyeingMachineQueueService::class)
            ->assertCanRecord(41, 7, 'color', [901]);

        self::assertSame($head->id, $result->first()->id);
    }

    private function assignment(string $stage, int $staffId, int $orderId, int $fabricId, int $position): ManTaskAssignment
    {
        return ManTaskAssignment::query()->create([
            'manufacturing_order_id' => $orderId,
            'fabric_id' => $fabricId,
            'department' => 'dyeing',
            'stage' => $stage,
            'assigned_to' => $staffId,
            'status' => 'pending',
            'queue_position' => $position,
            'machine_ids' => [7],
        ]);
    }
}
