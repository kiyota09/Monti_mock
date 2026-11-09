<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('machines')->where('type', 'knitting')->update(['type' => 'circular_knitting']);
    }

    public function down(): void
    {
        DB::table('machines')->where('type', 'circular_knitting')->update(['type' => 'knitting']);
        DB::table('machines')->where('type', 'flatbed_knitting')->update(['type' => 'knitting']);
    }
};
