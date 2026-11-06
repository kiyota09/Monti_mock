<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $statuses = ['pending', 'knitting_approved', 'dyeing', 'bleaching', 'squeezer', 'iron', 'forming', 'packed', 'rejected'];

    public function up(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->enum('status', $this->statuses)->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('fabrics')->where('status', 'knitting_approved')->update(['status' => 'pending']);
        Schema::table('fabrics', function (Blueprint $table) {
            $table->enum('status', ['pending', 'dyeing', 'bleaching', 'squeezer', 'iron', 'forming', 'packed', 'rejected'])->default('pending')->change();
        });
    }
};
