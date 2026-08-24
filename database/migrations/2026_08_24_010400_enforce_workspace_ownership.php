<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACCOUNT_TABLES = [
        'properties',
        'units',
        'tenants',
        'leases',
        'asset_items',
        'unit_assets',
        'inspections',
        'rent_invoices',
        'rent_payments',
        'maintenance_tickets',
        'contract_templates',
        'lease_contracts',
        'settings',
    ];

    public function up(): void
    {
        foreach (self::ACCOUNT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable(false)->change();
            });
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedBigInteger('property_owner_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedBigInteger('property_owner_id')->nullable()->change();
        });

        foreach (array_reverse(self::ACCOUNT_TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->change();
            });
        }
    }
};
