<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class RentPolicySeeder extends Seeder
{
    public function run(): void
    {
        Account::query()->each(function (Account $account) {
            Setting::set('rent.invoice_months_ahead', '6', $account);
            Setting::set('rent.due_day', '5', $account);
            Setting::set('rent.late_fee_enabled', '1', $account);
            Setting::set('rent.late_fee_type', 'fixed', $account);
            Setting::set('rent.late_fee_value', '5000', $account);
            Setting::set('rent.late_fee_grace_days', '3', $account);
        });
    }
}
