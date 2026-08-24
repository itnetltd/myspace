<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DeductionPolicySeeder extends Seeder
{
    public function run(): void
    {
        Account::query()->each(function (Account $account) {
            Setting::set('deduction.missing_rate', '1.00', $account);
            Setting::set('deduction.damaged_rate', '0.30', $account);
        });
    }
}
