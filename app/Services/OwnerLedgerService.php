<?php

namespace App\Services;

use App\Models\OwnerLedgerEntry;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class OwnerLedgerService
{
    public function __construct(private readonly FinancialPeriodGuard $periods) {}

    public function post(array $source, array $attributes): OwnerLedgerEntry
    {
        $amountMinor = Money::toMinor($attributes['amount'] ?? null);

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Ledger amounts must be greater than zero.']);
        }

        $identity = [
            'account_id' => $attributes['account_id'],
            'source_type' => $source['source_type'],
            'source_id' => $source['source_id'],
            'source_key' => $source['source_key'],
        ];

        $entry = OwnerLedgerEntry::withoutGlobalScopes()->where($identity)->first();
        $values = [
            ...$attributes,
            'amount' => Money::fromMinor($amountMinor),
            'posted_at' => $attributes['posted_at'] ?? now(),
        ];

        if ($entry?->locked_at) {
            foreach ($values as $key => $value) {
                if ($key === 'posted_at') {
                    continue;
                }

                $current = $entry->{$key};
                $current = is_array($current) ? $current : (string) $current;
                $incoming = is_array($value) ? $value : (string) $value;

                if ($current !== $incoming) {
                    throw ValidationException::withMessages([
                        'ledger' => 'A finalized statement has locked this ledger entry. Post an adjustment instead.',
                    ]);
                }
            }

            return $entry;
        }

        $this->periods->ensureOpen(
            (int) $attributes['account_id'],
            (int) $attributes['property_owner_id'],
            $attributes['occurred_on'],
        );

        if ($entry) {
            $entry->fill($values)->save();

            return $entry->refresh();
        }

        return OwnerLedgerEntry::withoutGlobalScopes()->create([
            ...$identity,
            ...$values,
        ]);
    }

    public function removeUnlockedComponent(int $accountId, string $sourceType, int $sourceId, string $sourceKey): void
    {
        $entry = OwnerLedgerEntry::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('source_key', $sourceKey)
            ->first();

        if (! $entry) {
            return;
        }

        if ($entry->locked_at) {
            throw ValidationException::withMessages([
                'ledger' => 'A finalized statement has locked this allocation. Post an adjustment instead.',
            ]);
        }

        $entry->delete();
    }
}
