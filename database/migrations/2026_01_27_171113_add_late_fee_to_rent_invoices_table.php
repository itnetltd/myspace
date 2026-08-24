<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rent_invoices', function (Blueprint $table) {
            $table->decimal('late_fee', 14, 2)->default(0)->after('amount_paid');
            $table->decimal('total_due', 14, 2)->default(0)->after('late_fee');
        });
    }

    public function down(): void
    {
        Schema::table('rent_invoices', function (Blueprint $table) {
            $table->dropColumn(['late_fee','total_due']);
        });
    }
};