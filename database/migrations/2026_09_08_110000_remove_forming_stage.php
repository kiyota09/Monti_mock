<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the dyeing-forming stage from manufacturing.
     *
     * Ironing now flows directly to packaging, so FormJob records are
     * obsolete: drop the form_jobs table and the form_job_id link on
     * package_items (Package carries its own quantity; no code writes
     * package_items rows). Historic 'forming' enum values on
     * fabrics.status / machines.type / users.manufacturing_role are left
     * intact so existing rows keep loading.
     */
    public function up(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            $table->dropForeign(['form_job_id']);
            $table->dropColumn('form_job_id');
        });

        Schema::dropIfExists('form_jobs');
    }

    public function down(): void
    {
        Schema::create('form_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iron_job_id')->constrained()->onDelete('cascade');
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->foreignId('product_id')->constrained();
            $table->text('remarks')->nullable();
            $table->foreignId('operator_id')->constrained('users');
            $table->string('shift');
            $table->string('code')->unique();
            $table->timestamp('processed_at');
            $table->timestamps();
        });

        Schema::table('package_items', function (Blueprint $table) {
            $table->foreignId('form_job_id')->nullable()->after('package_id')
                ->constrained('form_jobs')->onDelete('cascade');
        });
    }
};
