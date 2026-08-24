<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ContractTemplate;
use App\Models\ManagementAgreement;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PhaseTwoDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $user = User::query()->firstOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test User', 'password' => Hash::make('password')],
            );

            $landlord = Account::query()->updateOrCreate(
                ['slug' => 'mugisha-properties'],
                [
                    'name' => 'Mugisha Properties',
                    'type' => Account::TYPE_INDIVIDUAL_LANDLORD,
                    'status' => Account::STATUS_ACTIVE,
                    'currency' => 'RWF',
                    'timezone' => 'Africa/Kigali',
                    'created_by' => $user->id,
                ],
            );
            $this->attachOwner($user, $landlord);

            $landlordOwner = PropertyOwner::withoutGlobalScopes()->updateOrCreate(
                ['account_id' => $landlord->id, 'name' => 'Mugisha Jean'],
                ['type' => PropertyOwner::TYPE_INDIVIDUAL, 'status' => PropertyOwner::STATUS_ACTIVE],
            );
            $landlord->forceFill(['self_property_owner_id' => $landlordOwner->id])->saveQuietly();
            $landlordProperty = Property::withoutGlobalScopes()->updateOrCreate(
                ['account_id' => $landlord->id, 'name' => 'Mugisha Residence'],
                [
                    'property_owner_id' => $landlordOwner->id,
                    'type' => 'house',
                    'address' => 'Kigali',
                ],
            );
            Unit::withoutGlobalScopes()->updateOrCreate(
                ['property_id' => $landlordProperty->id, 'unit_code' => 'HOUSE-01'],
                ['account_id' => $landlord->id, 'monthly_rent' => 350000, 'status' => Unit::STATUS_VACANT],
            );

            $company = Account::query()->updateOrCreate(
                ['slug' => 'demo-property-management'],
                [
                    'name' => 'Demo Property Management Ltd',
                    'type' => Account::TYPE_PROPERTY_MANAGEMENT_COMPANY,
                    'status' => Account::STATUS_ACTIVE,
                    'currency' => 'RWF',
                    'timezone' => 'Africa/Kigali',
                    'created_by' => $user->id,
                ],
            );
            $this->attachOwner($user, $company);

            $clientA = $this->companyOwner($company, 'Client Owner A');
            $clientB = $this->companyOwner($company, 'Client Owner B');
            $propertyA = $this->companyProperty($company, $clientA, 'Client A Apartments');
            $this->companyProperty($company, $clientB, 'Client B Residence');

            ManagementAgreement::withoutGlobalScopes()->updateOrCreate(
                ['account_id' => $company->id, 'reference_number' => 'DMA-001'],
                [
                    'property_owner_id' => $clientA->id,
                    'property_id' => $propertyA->id,
                    'start_date' => now()->startOfYear()->toDateString(),
                    'management_fee_type' => ManagementAgreement::FEE_PERCENTAGE,
                    'management_fee_value' => '10.00',
                    'rent_collection_enabled' => true,
                    'deposit_management_enabled' => true,
                    'status' => ManagementAgreement::STATUS_ACTIVE,
                ],
            );

            foreach ([$landlord, $company] as $account) {
                ContractTemplate::withoutGlobalScopes()->updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'name' => 'Standard Residential Lease',
                        'language' => 'en',
                        'version' => '1.0',
                    ],
                    [
                        'is_active' => true,
                        'body_html' => '<h1>Lease Agreement</h1><p>Owner: {{owner_name}}</p><p>Tenant: {{tenant_full_name}}</p>',
                    ],
                );
            }

            $user->forceFill(['current_account_id' => $landlord->id])->saveQuietly();
        });
    }

    private function attachOwner(User $user, Account $account): void
    {
        $account->users()->syncWithoutDetaching([
            $user->id => ['role' => Account::ROLE_OWNER, 'is_active' => true],
        ]);
    }

    private function companyOwner(Account $account, string $name): PropertyOwner
    {
        return PropertyOwner::withoutGlobalScopes()->updateOrCreate(
            ['account_id' => $account->id, 'name' => $name],
            ['type' => PropertyOwner::TYPE_INDIVIDUAL, 'status' => PropertyOwner::STATUS_ACTIVE],
        );
    }

    private function companyProperty(Account $account, PropertyOwner $owner, string $name): Property
    {
        return Property::withoutGlobalScopes()->updateOrCreate(
            ['account_id' => $account->id, 'name' => $name],
            [
                'property_owner_id' => $owner->id,
                'type' => 'apartment',
                'address' => 'Kigali',
            ],
        );
    }
}
