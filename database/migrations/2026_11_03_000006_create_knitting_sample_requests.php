<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $roles = [
        'knitting_yarn', 'knitting_mechanic', 'knitting_inspector', 'knitting_sample',
        'dyeing_color', 'dyeing_bleaching', 'dyeing_lab_chemist',
        'finishing_squeezer', 'finishing_ironing', 'finishing_packaging', 'finishing_inspector',
        'checker_quality', 'maintenance_checker', 'pollution_control_operator',
        'safety_officer', 'boiler_operator', 'dyeing_forming',
    ];

    public function up(): void
    {
        $this->setRoleEnum('users');
        $this->setRoleEnum('manufacturing_supervisor_roles');

        Schema::create('knitting_sample_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('fabric_sample_request_id')->constrained('fabric_sample_requests')->cascadeOnDelete();
            $table->unsignedInteger('color_index');
            $table->string('fabric_design')->nullable();
            $table->string('texture')->nullable();
            $table->unsignedInteger('gsm')->nullable();
            $table->foreignId('yarn_material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('yarn_type')->nullable();
            $table->string('pantone_name')->nullable();
            $table->string('pantone_hex', 16)->nullable();
            $table->json('formula')->nullable();
            $table->decimal('requested_weight', 8, 3)->default(0.250);
            $table->string('status')->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->decimal('produced_weight', 8, 3)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        $supervisors = DB::table('users')->where('is_manufacturing_supervisor', true)
            ->where('supervisor_department', 'knitting')->pluck('id');
        foreach ($supervisors as $userId) {
            DB::table('manufacturing_supervisor_roles')->insertOrIgnore([
                'user_id' => $userId, 'manufacturing_role' => 'knitting_sample',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knitting_sample_requests');
    }

    private function setRoleEnum(string $table): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(',', array_map(fn ($role) => "'{$role}'", $this->roles));
            DB::statement("ALTER TABLE `{$table}` MODIFY `manufacturing_role` ENUM({$list}) NULL");

            return;
        }

        $roles = $this->roles;
        Schema::table($table, function (Blueprint $blueprint) use ($roles) {
            $blueprint->enum('manufacturing_role', $roles)->nullable()->change();
        });
    }

};
