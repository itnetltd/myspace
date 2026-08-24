<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('management_agreements', function (Blueprint $table) {
            $table->decimal('management_fee_percentage', 7, 4)->default(0);
            $table->decimal('management_fee_fixed_amount', 14, 2)->default(0);
            $table->boolean('fee_migration_review_required')->default(false);
        });

        DB::table('management_agreements')->orderBy('id')->each(function ($agreement) {
            $percentage = '0.0000';
            $fixed = '0.00';
            $reviewRequired = false;

            if ($agreement->management_fee_type === 'percentage') {
                $percentage = $agreement->management_fee_value;
            } elseif ($agreement->management_fee_type === 'fixed') {
                $fixed = $agreement->management_fee_value;
            } elseif ($agreement->management_fee_type === 'percentage_plus_fixed') {
                $percentage = $agreement->management_fee_value;
                $reviewRequired = true;
            }

            DB::table('management_agreements')->where('id', $agreement->id)->update([
                'management_fee_percentage' => $percentage,
                'management_fee_fixed_amount' => $fixed,
                'fee_migration_review_required' => $reviewRequired,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('management_agreements', function (Blueprint $table) {
            $table->dropColumn([
                'management_fee_percentage',
                'management_fee_fixed_amount',
                'fee_migration_review_required',
            ]);
        });
    }
};
