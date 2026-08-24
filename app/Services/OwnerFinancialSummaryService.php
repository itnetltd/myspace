<?php

namespace App\Services;

use App\Models\Account;
use App\Models\OwnerLedgerEntry;
use App\Models\PropertyOwner;
use App\Support\Money;
use Carbon\Carbon;

class OwnerFinancialSummaryService
{
    public function __construct(private readonly OwnerBalanceService $balances) {}

    public function forOwner(PropertyOwner $owner, ?string $month = null): array
    {
        $start = Carbon::parse($month ?? now())->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $properties = $owner->properties()->withCount('units')->get();
        $unitQuery = \App\Models\Unit::withoutGlobalScopes()
            ->where('account_id', $owner->account_id)
            ->whereIn('property_id', $properties->modelKeys());
        $units = (clone $unitQuery)->count();
        $occupied = (clone $unitQuery)->where('status', \App\Models\Unit::STATUS_OCCUPIED)->count();
        $amounts = $this->ledgerAmounts($owner->account_id, $owner->getKey(), $start, $end);

        return [
            'properties' => $properties->count(),
            'units' => $units,
            'occupied_units' => $occupied,
            'vacant_units' => max(0, $units - $occupied),
            'occupancy_percent' => $units === 0 ? 0 : (int) round(($occupied * 100) / $units),
            ...$amounts,
            'current_balance' => $this->balances->balance($owner),
            'recent_activity' => OwnerLedgerEntry::withoutGlobalScopes()
                ->where('account_id', $owner->account_id)
                ->where('property_owner_id', $owner->getKey())
                ->latest('occurred_on')->latest('id')->limit(10)->get(),
            'recent_expenses' => $owner->expenses()->latest('occurred_on')->limit(5)->get(),
            'statements' => $owner->statements()->latest('period_end')->limit(6)->get(),
        ];
    }

    public function forAccount(Account $account, string $periodStart, string $periodEnd): array
    {
        $owners = $account->propertyOwners()->count();
        $properties = $account->properties()->count();
        $units = $account->units()->count();
        $occupied = $account->units()->where('status', \App\Models\Unit::STATUS_OCCUPIED)->count();
        $amounts = $this->ledgerAmounts($account->getKey(), null, Carbon::parse($periodStart), Carbon::parse($periodEnd));
        $payableMinor = 0;

        $account->propertyOwners()->orderBy('id')->each(function (PropertyOwner $owner) use (&$payableMinor) {
            $balanceMinor = Money::toMinor($this->balances->balance($owner));
            $payableMinor += max(0, $balanceMinor);
        });

        return [
            'managed_owners' => $owners,
            'properties' => $properties,
            'units' => $units,
            'occupied_units' => $occupied,
            'occupancy_percent' => $units === 0 ? 0 : (int) round(($occupied * 100) / $units),
            'amounts_payable_to_owners' => Money::fromMinor($payableMinor),
            ...$amounts,
        ];
    }

    private function ledgerAmounts(int $accountId, ?int $ownerId, Carbon $start, Carbon $end): array
    {
        $minor = [
            'rent_collected' => 0,
            'late_fees_collected' => 0,
            'expenses' => 0,
            'management_fees' => 0,
            'owner_disbursements' => 0,
        ];

        OwnerLedgerEntry::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->when($ownerId, fn ($query) => $query->where('property_owner_id', $ownerId))
            ->whereDate('occurred_on', '>=', $start->toDateString())
            ->whereDate('occurred_on', '<=', $end->toDateString())
            ->orderBy('id')
            ->each(function (OwnerLedgerEntry $entry) use (&$minor) {
                $key = match ($entry->entry_type) {
                    OwnerLedgerEntry::TYPE_RENT_INCOME => 'rent_collected',
                    OwnerLedgerEntry::TYPE_LATE_FEE_INCOME => 'late_fees_collected',
                    OwnerLedgerEntry::TYPE_PROPERTY_EXPENSE => 'expenses',
                    OwnerLedgerEntry::TYPE_MANAGEMENT_FEE => 'management_fees',
                    OwnerLedgerEntry::TYPE_OWNER_DISBURSEMENT => 'owner_disbursements',
                    default => null,
                };

                if ($key) {
                    $minor[$key] += Money::toMinor($entry->amount);
                }
            });

        return array_map(fn (int $value) => Money::fromMinor($value), $minor);
    }
}
