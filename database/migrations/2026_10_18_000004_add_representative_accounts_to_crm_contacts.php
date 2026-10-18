<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Representatives live on top of crm_contacts. A contact registered by the
     * business partner starts as `pending`; the CRM decides whether to grant it
     * a portal login. Once granted, the contact stores its own credentials and
     * the exact set of partner-portal pages it may use.
     */
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_contacts', 'status')) {
                $table->string('status')->default('active')->after('notes');
            }
            if (! Schema::hasColumn('crm_contacts', 'has_account')) {
                $table->boolean('has_account')->default(false)->after('status');
            }
            if (! Schema::hasColumn('crm_contacts', 'password')) {
                $table->string('password')->nullable()->after('has_account');
            }
            if (! Schema::hasColumn('crm_contacts', 'access_pages')) {
                $table->json('access_pages')->nullable()->after('password');
            }
            if (! Schema::hasColumn('crm_contacts', 'origin')) {
                $table->string('origin')->default('crm')->after('access_pages');
            }
            if (! Schema::hasColumn('crm_contacts', 'account_created_at')) {
                $table->timestamp('account_created_at')->nullable()->after('origin');
            }

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            foreach (['account_created_at', 'origin', 'access_pages', 'password', 'has_account', 'status'] as $column) {
                if (Schema::hasColumn('crm_contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
