<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM ↔ dyeing-lab ↔ client fabric sample loop.
     *
     * New flow: CRM requests a fabric sample from inside the inquiry
     * conversation (fabric + color description) → the dyeing lab chemist
     * sees it on the shade page, formulates the color, creates the recipe
     * and sends formula + sample photo back → CRM forwards it into the
     * conversation → the client approves (then sends a P.O.) or requests a
     * color adjustment (loops back to a new sample request).
     *
     * - fabric_sample_requests carries the loop state (one row per round;
     *   adjustment rounds point at their parent).
     * - conversation_messages.visible_to_client lets the lab→CRM handoff
     *   stay hidden until CRM forwards it; sample_request_id links the
     *   thread messages that belong to a round.
     */
    public function up(): void
    {
        Schema::create('fabric_sample_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('fabric_name');
            $table->text('color_description');
            $table->text('notes')->nullable();
            $table->string('urgency', 16)->default('normal'); // low|normal|high|urgent
            // requested|in_progress|formulated|forwarded|approved|adjustment_requested|cancelled
            $table->string('status', 32)->default('requested');
            $table->json('formula')->nullable(); // { dyestuffs: [{name,pct}], auxiliaries: [{name,gpl}], notes }
            $table->string('sample_image_path')->nullable();
            $table->foreignId('recipe_id')->nullable()->constrained('bom_records')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('formulated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('fabric_sample_requests')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            // Lab→CRM handoff stays hidden until CRM forwards it to the client.
            $table->boolean('visible_to_client')->default(true)->after('is_system_event');
            $table->foreignId('sample_request_id')->nullable()->after('visible_to_client')
                ->constrained('fabric_sample_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sample_request_id');
            $table->dropColumn('visible_to_client');
        });

        Schema::dropIfExists('fabric_sample_requests');
    }
};
