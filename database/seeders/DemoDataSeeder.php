<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AssetItem;
use App\Models\Inspection;
use App\Models\InspectionLine;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAsset;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::query()->firstOrCreate(
            ['slug' => 'legacy-demo-account'],
            [
                'name' => 'Legacy Demo Account',
                'type' => Account::TYPE_INDIVIDUAL_LANDLORD,
                'status' => Account::STATUS_ACTIVE,
                'currency' => 'RWF',
                'timezone' => 'Africa/Kigali',
            ],
        );
        $owner = PropertyOwner::withoutGlobalScopes()->firstOrCreate(
            ['account_id' => $account->id, 'name' => 'Jean Paul N.'],
            ['type' => PropertyOwner::TYPE_INDIVIDUAL, 'phone' => '0788 000 111'],
        );

        /** -----------------------------
         * PROPERTY & UNIT
         * ----------------------------- */
        $property = Property::create([
            'account_id' => $account->id,
            'property_owner_id' => $owner->id,
            'name' => 'MySpaces Apartments – Kicukiro',
            'type' => 'apartment',
            'address' => 'KK 15 Ave, Kicukiro',
            'owner_name' => 'Jean Paul N.',
            'owner_phone' => '0788 000 111',
        ]);

        $unit = Unit::create([
            'account_id' => $account->id,
            'property_id' => $property->id,
            'unit_code' => 'A-101',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'monthly_rent' => 300000,
            'status' => 'occupied',
        ]);

        /** -----------------------------
         * ASSET CATALOG
         * ----------------------------- */
        $bed = AssetItem::create([
            'account_id' => $account->id,
            'name' => 'Bed',
            'category' => 'Furniture',
            'purchase_cost' => 150000,
            'replacement_value' => 200000,
        ]);

        $mattress = AssetItem::create([
            'account_id' => $account->id,
            'name' => 'Mattress',
            'category' => 'Furniture',
            'purchase_cost' => 120000,
            'replacement_value' => 180000,
        ]);

        $tv = AssetItem::create([
            'account_id' => $account->id,
            'name' => 'Television',
            'category' => 'Electronics',
            'purchase_cost' => 350000,
            'replacement_value' => 420000,
        ]);

        $fridge = AssetItem::create([
            'account_id' => $account->id,
            'name' => 'Fridge',
            'category' => 'Appliance',
            'purchase_cost' => 450000,
            'replacement_value' => 520000,
        ]);

        /** -----------------------------
         * UNIT INVENTORY
         * ----------------------------- */
        UnitAsset::insert([
            [
                'account_id' => $account->id,
                'unit_id' => $unit->id,
                'asset_item_id' => $bed->id,
                'quantity' => 2,
                'condition_status' => 'Good',
            ],
            [
                'account_id' => $account->id,
                'unit_id' => $unit->id,
                'asset_item_id' => $mattress->id,
                'quantity' => 2,
                'condition_status' => 'Good',
            ],
            [
                'account_id' => $account->id,
                'unit_id' => $unit->id,
                'asset_item_id' => $tv->id,
                'quantity' => 1,
                'condition_status' => 'Good',
            ],
            [
                'account_id' => $account->id,
                'unit_id' => $unit->id,
                'asset_item_id' => $fridge->id,
                'quantity' => 1,
                'condition_status' => 'Good',
            ],
        ]);

        /** -----------------------------
         * TENANT & LEASE
         * ----------------------------- */
        $tenant = Tenant::create([
            'account_id' => $account->id,
            'full_name' => 'Eric Mugabo',
            'phone' => '0722 123 456',
            'email' => 'eric@test.com',
            'id_number' => '1199XXXXXXXX',
            'emergency_contact_name' => 'Marie Claire',
            'emergency_contact_phone' => '0788 999 888',
        ]);

        $lease = Lease::create([
            'account_id' => $account->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => Carbon::now()->subYear(),
            'end_date' => Carbon::now(),
            'monthly_rent' => 300000,
            'deposit' => 600000,
            'status' => 'ended',
        ]);

        /** -----------------------------
         * MOVE-IN INSPECTION
         * ----------------------------- */
        $moveIn = Inspection::create([
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'lease_id' => $lease->id,
            'type' => 'move_in',
            'inspected_on' => Carbon::now()->subYear(),
            'inspected_by' => 'Property Manager',
            'summary_status' => 'All items in good condition',
        ]);

        foreach (UnitAsset::where('unit_id', $unit->id)->get() as $ua) {
            InspectionLine::create([
                'inspection_id' => $moveIn->id,
                'asset_item_id' => $ua->asset_item_id,
                'expected_qty' => $ua->quantity,
                'found_qty' => $ua->quantity,
                'condition_status' => 'Good',
                'issue_type' => 'none',
            ]);
        }

        /** -----------------------------
         * MOVE-OUT INSPECTION
         * ----------------------------- */
        $moveOut = Inspection::create([
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'lease_id' => $lease->id,
            'type' => 'move_out',
            'inspected_on' => Carbon::now(),
            'inspected_by' => 'Property Manager',
            'summary_status' => 'Missing and damaged items found',
        ]);

        // Bed: 1 missing
        InspectionLine::create([
            'inspection_id' => $moveOut->id,
            'asset_item_id' => $bed->id,
            'expected_qty' => 2,
            'found_qty' => 1,
            'condition_status' => 'Good',
            'issue_type' => 'missing',
            'remarks' => 'One bed frame missing',
        ]);

        // Mattress: damaged with manual override
        InspectionLine::create([
            'inspection_id' => $moveOut->id,
            'asset_item_id' => $mattress->id,
            'expected_qty' => 2,
            'found_qty' => 2,
            'condition_status' => 'Damaged',
            'issue_type' => 'damaged',
            'deduction_override' => 50000,
            'deduction_reason' => 'Minor damage agreed with tenant',
        ]);

        // TV: OK
        InspectionLine::create([
            'inspection_id' => $moveOut->id,
            'asset_item_id' => $tv->id,
            'expected_qty' => 1,
            'found_qty' => 1,
            'condition_status' => 'Good',
            'issue_type' => 'none',
        ]);

        // Fridge: damaged (auto calculation)
        InspectionLine::create([
            'inspection_id' => $moveOut->id,
            'asset_item_id' => $fridge->id,
            'expected_qty' => 1,
            'found_qty' => 1,
            'condition_status' => 'Damaged',
            'issue_type' => 'damaged',
            'remarks' => 'Broken freezer compartment',
        ]);
    }
}
