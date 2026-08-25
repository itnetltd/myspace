<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ProviderCapability;
use App\Models\ProviderCompany;
use App\Models\ProviderService;
use App\Models\Setting;
use App\Models\SupplierProduct;
use Illuminate\Database\Seeder;

class ProviderMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        Account::query()->each(function (Account $account) {
            Setting::set('marketplace.commission_enabled', '0', $account);
            Setting::set('marketplace.default_commission_percent', '3.00', $account);
        });

        $maintenance = $this->company('Kigali Home Services Ltd', 'kigali-home-services', 'maintenance');
        foreach (['Plumbing', 'Electrical', 'Appliance Repair'] as $name) {
            ProviderService::withoutGlobalScopes()->updateOrCreate(
                ['provider_company_id' => $maintenance->id, 'name' => $name],
                ['service_type' => 'maintenance', 'category' => 'General Maintenance', 'service_area' => 'Kigali', 'is_active' => true],
            );
        }

        $supplier = $this->company('Home Equipment Rwanda Ltd', 'home-equipment-rwanda', 'supplier');
        foreach (['Refrigerator', 'Television', 'Water Heater'] as $index => $name) {
            SupplierProduct::withoutGlobalScopes()->updateOrCreate(
                ['provider_company_id' => $supplier->id, 'sku' => 'DEMO-'.($index + 1)],
                ['name' => $name, 'category' => 'Home Equipment', 'unit_price' => (string) (($index + 3) * 100000), 'currency' => 'RWF', 'stock_status' => 'in_stock', 'is_active' => true],
            );
        }

        $inspection = $this->company('Rwanda Property Inspectors Ltd', 'rwanda-property-inspectors', 'inspection');
        ProviderService::withoutGlobalScopes()->updateOrCreate(
            ['provider_company_id' => $inspection->id, 'name' => 'Property Inspection'],
            ['service_type' => 'inspection', 'category' => 'Property Inspection', 'service_area' => 'Rwanda', 'is_active' => true],
        );
    }

    private function company(string $name, string $slug, string $capability): ProviderCompany
    {
        $company = ProviderCompany::updateOrCreate(['slug' => $slug], [
            'name' => $name, 'country' => 'Rwanda', 'status' => ProviderCompany::STATUS_ACTIVE,
        ]);
        ProviderCapability::withoutGlobalScopes()->updateOrCreate([
            'provider_company_id' => $company->id, 'capability' => $capability,
        ]);

        return $company;
    }
}
