<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'id_number')) {
                $table->string('id_number')->nullable()->after('email');
            }
        });

        // Optional: enforce uniqueness (recommended for security)
        Schema::table('tenants', function (Blueprint $table) {
            // only add unique if not already
            // NOTE: if you already have duplicates, fix them first.
            $table->unique('id_number');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // safe rollback (optional)
            // $table->dropUnique(['id_number']);
            // $table->dropColumn('id_number');
        });
    }
};