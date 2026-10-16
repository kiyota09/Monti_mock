<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real Facebook Page integration for the Socials Lead Hub:
     * - crm_social_accounts: connected page + encrypted Page access token
     * - crm_leads.source / external_id: attribution + idempotent converts
     *   (converting the same post/comment twice is refused, not duplicated)
     */
    public function up(): void
    {
        Schema::create('crm_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->default('facebook');
            $table->string('page_id');
            $table->string('page_name')->nullable();
            $table->string('page_url')->nullable();
            $table->text('access_token'); // encrypted cast on the model
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'page_id']);
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('source', 32)->nullable()->after('estimated_value');
            $table->string('external_id')->nullable()->after('source');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn(['source', 'external_id']);
        });
        Schema::dropIfExists('crm_social_accounts');
    }
};
