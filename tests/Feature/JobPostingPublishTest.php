<?php

namespace Tests\Feature;

use App\Models\Core\User;
use App\Models\Hrm\HrmJobPosting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class JobPostingPublishTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('HRM');
            $table->string('position')->default('manager');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('page_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('module');
            $table->string('page');
            $table->string('permission_level')->nullable();
            $table->timestamps();
        });
        Schema::create('hrm_job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('posting_id')->unique()->nullable();
            $table->string('title')->nullable();
            $table->string('status')->default('Draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('workforce_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamps();
        });
        Schema::create('user_module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('module');
            $table->timestamps();
        });
    }

    public function test_hrm_manager_can_publish_draft(): void
    {
        $mgr = User::create([
            'name' => 'HRM Manager', 'email' => 'hrm.mgr@test.local',
            'password' => Hash::make('secret123'),
        ]);
        // NOTE: role/position are NOT mass-assignable — persist via query.
        User::where('id', $mgr->id)->update(['role' => 'HRM', 'position' => 'manager', 'is_active' => true]);
        $mgr = $mgr->fresh();
        $post = HrmJobPosting::create([
            'posting_id' => 'POST-TEST001', 'title' => 'Draft QA', 'status' => 'Draft',
        ]);

        $res = $this->actingAs($mgr)->post(route('hrm.recruitment.job-postings.publish', ['jobPosting' => $post->id]));

        $res->assertRedirect();
        $this->assertSame('Published', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
    }
}
