<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_messages')) {
            Schema::create('supplier_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
                // eco = Monti staff, supplier = vendor portal
                $table->enum('sender_type', ['eco', 'supplier'])->default('eco');
                $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('message');
                $table->string('attachment')->nullable();
                $table->json('meeting_data')->nullable();
                $table->boolean('is_system_event')->default(false);
                $table->timestamps();
                $table->index(['supplier_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('supplier_requests')) {
            Schema::create('supplier_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number')->unique();
                $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
                $table->date('delivery_date');
                $table->string('payment_terms');
                $table->text('notes')->nullable();
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['supplier_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('supplier_request_items')) {
            Schema::create('supplier_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_request_id')->constrained('supplier_requests')->onDelete('cascade');
                $table->string('material_name');
                $table->decimal('quantity', 15, 2);
                $table->string('unit');
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->text('specs')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_request_items');
        Schema::dropIfExists('supplier_requests');
        Schema::dropIfExists('supplier_messages');
    }
};
