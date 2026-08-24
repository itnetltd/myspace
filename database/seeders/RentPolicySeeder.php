<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class RentPolicySeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('rent.invoice_months_ahead', '6');
        Setting::set('rent.due_day', '5');

        Setting::set('rent.late_fee_enabled', '1');
        Setting::set('rent.late_fee_type', 'fixed'); // fixed|percent
        Setting::set('rent.late_fee_value', '5000'); // RWF or %
        Setting::set('rent.late_fee_grace_days', '3');
    }
}