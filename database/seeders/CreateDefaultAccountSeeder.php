<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CreateDefaultAccountSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            return;
        }

        $name = 'MySpaces Default Landlord';
        $slug = Str::slug($name);

        $account = Account::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'type' => Account::TYPE_INDIVIDUAL_LANDLORD,
                'status' => 'active',
                'created_by' => $user->id,
            ]
        );

        $account->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'is_active' => true],
        ]);

        if (! $user->current_account_id) {
            $user->update(['current_account_id' => $account->id]);
        }
    }
}
