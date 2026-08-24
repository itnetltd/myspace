<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;

use App\Models\Property;
use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Lease;
use App\Models\AssetItem;
use App\Models\UnitAsset;
use App\Models\Inspection;
use App\Models\InspectionLine;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        /** -----------------------------
         * PROPERTY & UNIT
         * ----------------------------- */
        $property = Property::create([
            'name' => 'MySpaces Apartments – Kicukiro',
            'type' => 'apartment',
            'address' => 'KK 15 Ave, Kicukiro',
            'owner_name' => 'Jean Paul N.',
            'owner_phone' => '0788 000 111',
        ]);

        $unit = Unit::create([
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
            'name' => 'Bed',
            'category' => 'Furniture',
            'purchase_cost' => 150000,
            'replacement_value' => 200000,
        ]);

        $mattress = AssetItem::create([
            'name' => 'Mattress',
            'category' => 'Furniture',
            'purchase_cost' => 120000,
            'replacement_value' => 180000,
        ]);

        $tv = AssetItem::create([
            'name' => 'Television',
            'category' => 'Electronics',
            'purchase_cost' => 350000,
            'replacement_value' => 420000,
        ]);

        $fridge = AssetItem::create([
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
                'unit_id' => $unit->id,
                'asset_item_id' => $bed->id,
                'quantity' => 2,
                'condition_status' => 'Good',
            ],
            [
                'unit_id' => $unit->id,
                'asset_item_id' => $mattress->id,
                'quantity' => 2,
                'condition_status' => 'Good',
            ],
            [
                'unit_id' => $unit->id,
                'asset_item_id' => $tv->id,
                'quantity' => 1,
                'condition_status' => 'Good',
            ],
            [
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
            'full_name' => 'Eric Mugabo',
            'phone' => '0722 123 456',
            'email' => 'eric@test.com',
            'id_number' => '1199XXXXXXXX',
            'emergency_contact_name' => 'Marie Claire',
            'emergency_contact_phone' => '0788 999 888',
        ]);

        $lease = Lease::create([
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