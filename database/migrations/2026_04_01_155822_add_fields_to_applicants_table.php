<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('applicants', function (Blueprint $table) {
            // Personal Information
            $table->string('image')->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('image');
            $table->string('place_of_birth')->nullable()->after('date_of_birth');
            $table->string('citizenship')->nullable()->after('place_of_birth');
            $table->decimal('weight', 5, 2)->nullable()->after('citizenship');
            $table->decimal('height', 5, 2)->nullable()->after('weight');
            $table->string('civil_status')->nullable()->after('height');
            $table->string('sex')->nullable()->after('civil_status');
            $table->integer('age')->nullable()->after('sex');
            $table->string('religion')->nullable()->after('age');
            $table->string('contact_number')->nullable()->after('religion');
            $table->string('sss_number')->nullable()->after('contact_number');
            $table->string('sss_file')->nullable()->change(); // already exists, just keep
            $table->string('philhealth_number')->nullable()->after('sss_file');
            $table->string('philhealth_file')->nullable()->change();
            $table->string('pagibig_number')->nullable()->after('philhealth_file');
            $table->string('pagibig_file')->nullable()->change();

            // Family Information
            $table->string('spouse_name')->nullable()->after('pagibig_file');
            $table->string('spouse_occupation')->nullable()->after('spouse_name');
            $table->text('spouse_address')->nullable()->after('spouse_occupation');
            $table->integer('number_of_children')->nullable()->after('spouse_address');
            $table->json('children')->nullable()->after('number_of_children'); // store array of {name, dob}

            $table->string('mother_name')->nullable()->after('children');
            $table->text('mother_address')->nullable()->after('mother_name');
            $table->string('father_name')->nullable()->after('mother_address');
            $table->text('father_address')->nullable()->after('father_name');

            $table->text('languages')->nullable()->after('father_address'); // comma separated

            $table->string('emergency_name')->nullable()->after('languages');
            $table->string('emergency_relationship')->nullable()->after('emergency_name');
            $table->string('emergency_phone')->nullable()->after('emergency_relationship');
            $table->text('emergency_address')->nullable()->after('emergency_phone');

            // Education
            $table->json('educational_background')->nullable()->after('emergency_address');
            // expects: [ {level: 'elementary', school: '', year_graduated: ''}, ... ]

            // Skills and Others
            $table->text('special_skills')->nullable()->after('educational_background');
            $table->boolean('has_employment_record')->default(false)->after('special_skills');
            $table->json('employment_records')->nullable()->after('has_employment_record');
            // expects: [ {company: '', years_of_service: '', salary: '', position: '', reason_for_leaving: ''}, ... ]

            $table->text('machine_operation')->nullable()->after('employment_records');
            $table->string('referred_by')->nullable()->after('machine_operation');
            $table->text('referred_by_address')->nullable()->after('referred_by');
            $table->text('previous_employment')->nullable()->after('referred_by_address'); // "Have you ever been employed in this company?"
            $table->json('related_employees')->nullable()->after('previous_employment'); // store array of {name, relationship}

            // Workflow
            $table->string('assigned_module')->nullable()->after('related_employees'); // which module for interview
            $table->boolean('archived')->default(false)->after('assigned_module');
            $table->text('rejection_reason')->nullable()->after('archived');
            $table->text('interview_feedback')->nullable()->after('rejection_reason');
        });
    }

    public function down()
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'image', 'date_of_birth', 'place_of_birth', 'citizenship', 'weight', 'height',
                'civil_status', 'sex', 'age', 'religion', 'contact_number', 'sss_number',
                'philhealth_number', 'pagibig_number',
                'spouse_name', 'spouse_occupation', 'spouse_address', 'number_of_children', 'children',
                'mother_name', 'mother_address', 'father_name', 'father_address',
                'languages', 'emergency_name', 'emergency_relationship', 'emergency_phone', 'emergency_address',
                'educational_background', 'special_skills', 'has_employment_record', 'employment_records',
                'machine_operation', 'referred_by', 'referred_by_address', 'previous_employment', 'related_employees',
                'assigned_module', 'archived', 'rejection_reason', 'interview_feedback',
            ]);
        });
    }
};
