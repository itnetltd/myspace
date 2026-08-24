<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public $withinTransaction = true;

    public function up(): void
    {
        DB::table('accounts')->where('type', 'landlord')->update([
            'type' => 'individual_landlord',
        ]);

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        $legacySlug = $this->uniqueAccountSlug('legacy-myspaces-account');
        $legacyAccountId = DB::table('accounts')->where('slug', 'legacy-myspaces-account')->value('id');

        if (! $legacyAccountId) {
            $legacyAccountId = DB::table('accounts')->insertGetId([
                'name' => 'Legacy MySpaces Account',
                'slug' => $legacySlug,
                'type' => 'individual_landlord',
                'status' => 'active',
                'currency' => 'RWF',
                'timezone' => 'Africa/Kigali',
                'created_by' => $firstUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $position = 0;
        DB::table('users')->orderBy('id')->each(function ($user) use ($legacyAccountId, &$position) {
            DB::table('account_user')->updateOrInsert(
                ['account_id' => $legacyAccountId, 'user_id' => $user->id],
                [
                    'role' => $position === 0 ? 'owner' : 'administrator',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $hasValidCurrentAccount = $user->current_account_id
                && DB::table('account_user')
                    ->where('account_id', $user->current_account_id)
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->exists();

            if (! $hasValidCurrentAccount) {
                DB::table('users')->where('id', $user->id)->update([
                    'current_account_id' => $legacyAccountId,
                ]);
            }

            $position++;
        });

        DB::table('properties')->whereNull('account_id')->update(['account_id' => $legacyAccountId]);

        DB::table('properties')->whereNull('property_owner_id')->orderBy('id')->each(function ($property) {
            $name = trim((string) ($property->owner_name ?: 'Legacy Property Owner'));
            $phone = $property->owner_phone ?: null;

            $ownerId = DB::table('property_owners')
                ->where('account_id', $property->account_id)
                ->where('name', $name)
                ->where(function ($query) use ($phone) {
                    $phone === null ? $query->whereNull('phone') : $query->where('phone', $phone);
                })
                ->value('id');

            if (! $ownerId) {
                $ownerId = DB::table('property_owners')->insertGetId([
                    'account_id' => $property->account_id,
                    'type' => 'individual',
                    'name' => $name,
                    'phone' => $phone,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('properties')->where('id', $property->id)->update([
                'property_owner_id' => $ownerId,
            ]);
        });

        $this->backfillFromParent('units', 'property_id', 'properties', $legacyAccountId);
        $this->backfillFromParent('leases', 'unit_id', 'units', $legacyAccountId);

        DB::table('tenants')->whereNull('account_id')->orderBy('id')->each(function ($tenant) use ($legacyAccountId) {
            $accountId = DB::table('leases')->where('tenant_id', $tenant->id)->value('account_id');
            DB::table('tenants')->where('id', $tenant->id)->update([
                'account_id' => $accountId ?: $legacyAccountId,
            ]);
        });

        DB::table('asset_items')->whereNull('account_id')->orderBy('id')->each(function ($asset) use ($legacyAccountId) {
            $accountId = DB::table('unit_assets')
                ->join('units', 'units.id', '=', 'unit_assets.unit_id')
                ->where('unit_assets.asset_item_id', $asset->id)
                ->value('units.account_id');

            DB::table('asset_items')->where('id', $asset->id)->update([
                'account_id' => $accountId ?: $legacyAccountId,
            ]);
        });

        $this->backfillFromParent('unit_assets', 'unit_id', 'units', $legacyAccountId);
        $this->backfillFromParent('inspections', 'unit_id', 'units', $legacyAccountId);
        $this->backfillFromParent('rent_invoices', 'lease_id', 'leases', $legacyAccountId);
        $this->backfillFromParent('rent_payments', 'rent_invoice_id', 'rent_invoices', $legacyAccountId);
        $this->backfillFromParent('maintenance_tickets', 'unit_id', 'units', $legacyAccountId);
        $this->backfillFromParent('lease_contracts', 'lease_id', 'leases', $legacyAccountId);

        DB::table('contract_templates')->whereNull('account_id')->update(['account_id' => $legacyAccountId]);
        DB::table('settings')->whereNull('account_id')->update(['account_id' => $legacyAccountId]);
    }

    public function down(): void
    {
        // Backfilled ownership is intentionally retained on rollback to avoid data loss.
    }

    private function backfillFromParent(
        string $table,
        string $foreignKey,
        string $parentTable,
        int $fallbackAccountId,
    ): void {
        DB::table($table)->whereNull('account_id')->orderBy('id')->each(
            function ($record) use ($table, $foreignKey, $parentTable, $fallbackAccountId) {
                $accountId = DB::table($parentTable)
                    ->where('id', $record->{$foreignKey})
                    ->value('account_id');

                DB::table($table)->where('id', $record->id)->update([
                    'account_id' => $accountId ?: $fallbackAccountId,
                ]);
            },
        );
    }

    private function uniqueAccountSlug(string $base): string
    {
        $slug = Str::slug($base);
        $suffix = 1;

        while (DB::table('accounts')->where('slug', $slug)->exists()) {
            $slug = Str::slug($base).'-'.$suffix++;
        }

        return $slug;
    }
};
