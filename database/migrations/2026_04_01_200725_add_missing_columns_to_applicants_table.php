<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('applicants', function (Blueprint $table) {
            // Education – individual columns (replaces JSON educational_background)
            $table->string('elementary_school')->nullable()->after('special_skills');
            $table->string('elementary_year')->nullable()->after('elementary_school');
            $table->string('high_school')->nullable()->after('elementary_year');
            $table->string('high_year')->nullable()->after('high_school');
            $table->string('college')->nullable()->after('high_year');
            $table->string('college_year')->nullable()->after('college');
            $table->string('vocational')->nullable()->after('college_year');
            $table->string('vocational_year')->nullable()->after('vocational');

            // Previous employment details (replaces the single text column)
            $table->string('previous_employment_company')->nullable()->after('vocational_year');
            $table->string('previous_employment_when')->nullable()->after('previous_employment_company');
            $table->string('previous_employment_position')->nullable()->after('previous_employment_when');
            $table->string('previous_employment_department')->nullable()->after('previous_employment_position');

            // Drop the old JSON column and the old single text column (they are not used)
            $table->dropColumn('educational_background');
            $table->dropColumn('previous_employment');
        });
    }

    public function down()
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'elementary_school', 'elementary_year', 'high_school', 'high_year',
                'college', 'college_year', 'vocational', 'vocational_year',
                'previous_employment_company', 'previous_employment_when',
                'previous_employment_position', 'previous_employment_department',
            ]);
            $table->json('educational_background')->nullable();
            $table->text('previous_employment')->nullable();
        });
    }
};
