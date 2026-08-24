<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class DeductionPolicySeeder extends Seeder
{
    public function run(): void
    {
        // Store as decimals (0.00 - 1.00)
        Setting::set('deduction.missing_rate', '1.00'); // 100%
        Setting::set('deduction.damaged_rate', '0.30'); // 30%
    }
}