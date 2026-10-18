<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("fin_expenses", function (Blueprint $table) {
            $table->unsignedBigInteger("payroll_id")->nullable()->after("recorded_by");
            $table->foreign("payroll_id")->references("id")->on("payrolls")->onDelete("set null");
        });
    }

    public function down(): void
    {
        Schema::table("fin_expenses", function (Blueprint $table) {
            $table->dropForeign(["payroll_id"]);
            $table->dropColumn("payroll_id");
        });
    }
};
