<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Support\Facades\DB;

class AccountOnboarding
{
    public function create(array $attributes, User $creator): Account
    {
        return DB::transaction(function () use ($attributes, $creator) {
            $attributes['created_by'] = $creator->getKey();
            $account = Account::create($attributes);

            $account->users()->attach($creator, [
                'role' => Account::ROLE_OWNER,
                'is_active' => true,
            ]);

            $this->ensureSelfOwner($account);

            app(CurrentAccount::class)->switch($creator, $account->getKey());

            return $account->fresh('selfPropertyOwner');
        });
    }

    public function ensureSelfOwner(Account $account): ?PropertyOwner
    {
        if (! $account->isIndividualLandlord()) {
            return null;
        }

        if ($account->self_property_owner_id) {
            return PropertyOwner::withoutGlobalScopes()->find($account->self_property_owner_id);
        }

        $owner = PropertyOwner::withoutGlobalScopes()->firstOrCreate(
            ['account_id' => $account->getKey(), 'name' => $account->name],
            [
                'type' => PropertyOwner::TYPE_INDIVIDUAL,
                'phone' => $account->phone,
                'email' => $account->email,
                'tin' => $account->tin,
                'registration_number' => $account->registration_number,
                'address' => $account->address,
                'status' => PropertyOwner::STATUS_ACTIVE,
            ],
        );

        $account->forceFill(['self_property_owner_id' => $owner->getKey()])->save();

        return $owner;
    }
}
