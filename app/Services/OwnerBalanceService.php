<?php

namespace App\Services;

use App\Models\OwnerLedgerEntry;
use App\Models\PropertyOwner;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class OwnerBalanceService
{
    public function balance(
        PropertyOwner $owner,
        ?int $propertyId = null,
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
    ): string {
        $query = $this->query($owner, $propertyId, $from, $to);
        $creditMinor = 0;
        $debitMinor = 0;

        $query->orderBy('id')->each(function (OwnerLedgerEntry $entry) use (&$creditMinor, &$debitMinor) {
            if ($entry->direction === OwnerLedgerEntry::DIRECTION_CREDIT) {
                $creditMinor += Money::toMinor($entry->amount);
            } else {
                $debitMinor += Money::toMinor($entry->amount);
            }
        });

        return Money::fromMinor($creditMinor - $debitMinor);
    }

    public function query(
        PropertyOwner $owner,
        ?int $propertyId = null,
        CarbonInterface|string|null $from = null,
        CarbonInterface|string|null $to = null,
    ): Builder {
        return OwnerLedgerEntry::withoutGlobalScopes()
            ->where('account_id', $owner->account_id)
            ->where('property_owner_id', $owner->getKey())
            ->when($propertyId, fn (Builder $query) => $query->where('property_id', $propertyId))
            ->when($from, fn (Builder $query) => $query->whereDate('occurred_on', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('occurred_on', '<=', $to));
    }
}
