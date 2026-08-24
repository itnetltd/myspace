<?php

namespace App\Services;

use App\Models\OwnerLedgerEntry;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerLedgerAdjustmentService
{
    public function record(
        PropertyOwner $owner,
        string $amount,
        string $direction,
        string $reason,
        ?string $reference,
        User $user,
    ): OwnerLedgerEntry {
        if (! in_array($direction, [OwnerLedgerEntry::DIRECTION_CREDIT, OwnerLedgerEntry::DIRECTION_DEBIT], true)) {
            throw ValidationException::withMessages(['direction' => 'Select a valid adjustment direction.']);
        }

        if (Money::toMinor($amount) <= 0 || trim($reason) === '') {
            throw ValidationException::withMessages([
                'amount' => 'A positive amount and adjustment reason are required.',
            ]);
        }

        return OwnerLedgerEntry::withoutGlobalScopes()->create([
            'account_id' => $owner->account_id,
            'property_owner_id' => $owner->getKey(),
            'entry_number' => 'LE-ADJ-'.Str::upper((string) Str::ulid()),
            'entry_type' => $direction === OwnerLedgerEntry::DIRECTION_CREDIT
                ? OwnerLedgerEntry::TYPE_CREDIT_ADJUSTMENT
                : OwnerLedgerEntry::TYPE_DEBIT_ADJUSTMENT,
            'direction' => $direction,
            'amount' => Money::fromMinor(Money::toMinor($amount)),
            'currency' => $owner->account->currency,
            'occurred_on' => now()->toDateString(),
            'description' => trim($reason),
            'source_type' => 'manual_adjustment',
            'metadata' => ['reference' => $reference, 'reason' => trim($reason)],
            'created_by' => $user->getKey(),
            'posted_at' => now(),
        ]);
    }
}
