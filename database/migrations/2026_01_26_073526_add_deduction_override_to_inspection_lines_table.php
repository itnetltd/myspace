<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inspection_lines', function (Blueprint $table) {
            $table->decimal('deduction_override', 14, 2)->nullable()->after('evidence_photo_path');
            $table->string('deduction_reason')->nullable()->after('deduction_override');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_lines', function (Blueprint $table) {
            $table->dropColumn(['deduction_override', 'deduction_reason']);
        });
    }
};