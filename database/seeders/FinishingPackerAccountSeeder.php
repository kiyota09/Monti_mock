<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FinishingPackerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $now=now();
        $ceoId=DB::table('users')->where('role','CEO')->value('id');
        DB::table('users')->updateOrInsert(['email'=>'finishing.packer@example.com'],[
            'name'=>'Finishing Packer','first_name'=>'Finishing','last_name'=>'Packer',
            'password'=>Hash::make('password'),'must_change_password'=>0,'role'=>'MAN','position'=>'staff',
            'hrm_role_id'=>4,'hrm_department_id'=>4,'manufacturing_role'=>'finishing_packaging',
            'manufacturing_role_assigned_by'=>$ceoId,'is_active'=>1,'email_verified_at'=>$now,
            'timezone'=>'GMT+8','created_at'=>$now,'updated_at'=>$now,
        ]);
        $userId=DB::table('users')->where('email','finishing.packer@example.com')->value('id');
        foreach(['dashboard','finishing_packaging','reports','history'] as $page){
            DB::table('page_permissions')->updateOrInsert(
                ['user_id'=>$userId,'module'=>'MAN','page'=>$page],
                ['permission_level'=>'edit','created_at'=>$now,'updated_at'=>$now],
            );
        }
    }
}
