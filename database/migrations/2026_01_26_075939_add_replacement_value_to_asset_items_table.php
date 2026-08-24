<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->decimal('replacement_value', 14, 2)->nullable()->after('purchase_cost');
        });
    }

    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropColumn('replacement_value');
        });
    }
};