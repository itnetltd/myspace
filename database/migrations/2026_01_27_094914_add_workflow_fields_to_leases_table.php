<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            if (!Schema::hasColumn('leases', 'unit_id')) {
                $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('leases', 'tenant_name')) {
                $table->string('tenant_name')->nullable(); // beginner mode (can migrate to tenants table later)
            }

            if (!Schema::hasColumn('leases', 'start_date')) {
                $table->date('start_date')->nullable();
            }

            if (!Schema::hasColumn('leases', 'end_date')) {
                $table->date('end_date')->nullable();
            }

            if (!Schema::hasColumn('leases', 'monthly_rent')) {
                $table->decimal('monthly_rent', 14, 2)->nullable();
            }

            if (!Schema::hasColumn('leases', 'deposit')) {
                $table->decimal('deposit', 14, 2)->default(0);
            }

            if (!Schema::hasColumn('leases', 'status')) {
                $table->string('status')->default('draft'); // draft|active|ended
            }
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            // keep safe: don't drop columns automatically in shared environments
        });
    }
};