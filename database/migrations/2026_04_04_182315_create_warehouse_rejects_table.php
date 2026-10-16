<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('warehouse_rejects', function (Blueprint $table) {
            $table->id();
            $table->morphs('rejectable'); // can be receiving_item or form_job (manufacturing)
            $table->enum('source', ['receiving', 'manufacturing']);
            $table->foreignId('warehouse_id')->nullable()->constrained();
            $table->decimal('quantity', 12, 2);
            $table->string('unit');
            $table->text('reason');
            $table->enum('status', ['pending_return', 'returned'])->default('pending_return');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_rejects');
    }
};