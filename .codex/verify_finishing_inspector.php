<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = Illuminate\Support\Facades\DB::table('users')
    ->where('email', 'finishing.inspector@example.com')
    ->first(['id', 'name', 'employee_id', 'email', 'role', 'position', 'manufacturing_role', 'is_active', 'must_change_password']);
var_export($user);
echo PHP_EOL;
var_export(Illuminate\Support\Facades\DB::table('page_permissions')
    ->where('user_id', $user->id)
    ->where('module', 'MAN')
    ->get(['page', 'permission_level'])->toArray());
