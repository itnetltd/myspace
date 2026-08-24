<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACCOUNT_TABLES = [
        'properties',
        'tenants',
        'asset_items',
        'unit_assets',
        'inspections',
        'rent_invoices',
        'rent_payments',
        'maintenance_tickets',
        'lease_contracts',
    ];

    public function up(): void
    {
        foreach (self::ACCOUNT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('account_id')->nullable()->constrained()->restrictOnDelete();
            });
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('property_owner_id')
                ->nullable()
                ->constrained('property_owners')
                ->restrictOnDelete();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['account_id', 'key']);
        });

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropUnique(['name', 'language', 'version']);
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['account_id', 'name', 'language', 'version'], 'contract_templates_account_version_unique');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['id_number']);
            $table->unique(['account_id', 'id_number']);
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropUnique(['ticket_no']);
            $table->unique(['account_id', 'ticket_no']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'ticket_no']);
            $table->unique('ticket_no');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'id_number']);
            $table->unique('id_number');
        });

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropUnique('contract_templates_account_version_unique');
            $table->dropConstrainedForeignId('account_id');
            $table->unique(['name', 'language', 'version']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'key']);
            $table->dropConstrainedForeignId('account_id');
            $table->unique('key');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_owner_id');
        });

        foreach (array_reverse(self::ACCOUNT_TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('account_id');
            });
        }
    }
};
