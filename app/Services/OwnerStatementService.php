<?php

namespace App\Services;

use App\Models\OwnerLedgerEntry;
use App\Models\OwnerStatement;
use App\Models\OwnerStatementLine;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerStatementService
{
    public function __construct(
        private readonly ManagementFeeService $managementFees,
        private readonly OwnerBalanceService $balances,
    ) {}

    public function generateDraft(PropertyOwner $owner, string $statementMonth, User $user): OwnerStatement
    {
        $start = Carbon::parse(preg_match('/^\d{4}-\d{2}$/', $statementMonth) ? $statementMonth.'-01' : $statementMonth)
            ->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return DB::transaction(function () use ($owner, $start, $end, $user) {
            $statement = OwnerStatement::withoutGlobalScopes()
                ->where('account_id', $owner->account_id)
                ->where('property_owner_id', $owner->getKey())
                ->where('statement_month', $start->format('Y-m'))
                ->lockForUpdate()
                ->first();

            if ($statement?->status === OwnerStatement::STATUS_FINALIZED) {
                throw ValidationException::withMessages([
                    'statement' => 'A finalized statement cannot be regenerated. Use a later adjustment entry.',
                ]);
            }

            $warnings = $this->managementFees->synchronize($owner, $start, $end);
            $entries = OwnerLedgerEntry::withoutGlobalScopes()
                ->with(['property', 'unit'])
                ->where('account_id', $owner->account_id)
                ->where('property_owner_id', $owner->getKey())
                ->whereDate('occurred_on', '>=', $start->toDateString())
                ->whereDate('occurred_on', '<=', $end->toDateString())
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->get();

            $foreignAssignment = $entries->first(fn (OwnerLedgerEntry $entry) => $entry->owner_statement_id
                && (int) $entry->owner_statement_id !== (int) $statement?->getKey());

            if ($foreignAssignment) {
                throw ValidationException::withMessages([
                    'ledger' => "Ledger entry {$foreignAssignment->entry_number} is already assigned to another owner statement.",
                ]);
            }

            $openingMinor = Money::toMinor($this->balances->balance($owner, null, null, $start->copy()->subDay()));
            $totals = $this->totals($entries);
            $netMinor = $totals['credits'] - $totals['debits'];
            $attributes = [
                'account_id' => $owner->account_id,
                'property_owner_id' => $owner->getKey(),
                'statement_number' => 'OS-'.$owner->getKey().'-'.$start->format('Ymd').'-'.$end->format('Ymd'),
                'statement_month' => $start->format('Y-m'),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => OwnerStatement::STATUS_DRAFT,
                'currency' => $owner->account->currency,
                'opening_balance' => Money::fromMinor($openingMinor),
                'rent_collected' => Money::fromMinor($totals['rent']),
                'late_fees_collected' => Money::fromMinor($totals['late']),
                'other_income' => Money::fromMinor($totals['other_income']),
                'expenses' => Money::fromMinor($totals['expenses']),
                'management_fees' => Money::fromMinor($totals['management_fees']),
                'owner_disbursements' => Money::fromMinor($totals['disbursements']),
                'net_activity' => Money::fromMinor($netMinor),
                'closing_balance' => Money::fromMinor($openingMinor + $netMinor),
                'generated_at' => now(),
                'generated_by' => $user->getKey(),
                'notes' => $warnings === [] ? $statement?->notes : implode("\n", $warnings),
            ];

            if ($statement) {
                $statement->fill($attributes)->save();
                $statement->lines()->delete();
                OwnerLedgerEntry::withoutGlobalScopes()
                    ->where('owner_statement_id', $statement->getKey())
                    ->whereNull('locked_at')
                    ->where(function ($query) use ($start, $end) {
                        $query->whereDate('occurred_on', '<', $start->toDateString())
                            ->orWhereDate('occurred_on', '>', $end->toDateString());
                    })
                    ->update(['owner_statement_id' => null]);
            } else {
                $statement = OwnerStatement::withoutGlobalScopes()->create($attributes);
            }

            foreach ($entries as $entry) {
                $statement->lines()->create([
                    'property_id' => $entry->property_id,
                    'unit_id' => $entry->unit_id,
                    'line_type' => $entry->entry_type,
                    'description' => $entry->description,
                    'credit' => $entry->direction === OwnerLedgerEntry::DIRECTION_CREDIT ? $entry->amount : '0.00',
                    'debit' => $entry->direction === OwnerLedgerEntry::DIRECTION_DEBIT ? $entry->amount : '0.00',
                    'occurred_on' => $entry->occurred_on,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                    'metadata' => [
                        'entry_number' => $entry->entry_number,
                        'property_name' => $entry->property?->name,
                        'unit_code' => $entry->unit?->unit_code,
                        'source_key' => $entry->source_key,
                        'entry_metadata' => $entry->metadata,
                    ],
                ]);
            }

            OwnerLedgerEntry::withoutGlobalScopes()
                ->whereKey($entries->modelKeys())
                ->whereNull('locked_at')
                ->where(function ($query) use ($statement) {
                    $query->whereNull('owner_statement_id')
                        ->orWhere('owner_statement_id', $statement->getKey());
                })
                ->update(['owner_statement_id' => $statement->getKey()]);

            return $statement->fresh(['lines', 'propertyOwner']);
        });
    }

    public function finalize(OwnerStatement $statement, User $user): OwnerStatement
    {
        return DB::transaction(function () use ($statement, $user) {
            $statement = OwnerStatement::withoutGlobalScopes()->lockForUpdate()->findOrFail($statement->getKey());

            if ($statement->status === OwnerStatement::STATUS_FINALIZED) {
                return $statement;
            }

            $owner = PropertyOwner::withoutGlobalScopes()->findOrFail($statement->property_owner_id);
            $start = Carbon::parse($statement->period_start)->startOfDay();
            $end = Carbon::parse($statement->period_end)->endOfDay();

            // This may update or create the current fee entry. If that makes the
            // draft stale, the exception below rolls the synchronization back so
            // finalization never silently changes the statement being reviewed.
            $this->managementFees->synchronize($owner, $start, $end);

            $entries = OwnerLedgerEntry::withoutGlobalScopes()
                ->where('account_id', $statement->account_id)
                ->where('property_owner_id', $statement->property_owner_id)
                ->whereDate('occurred_on', '>=', $start->toDateString())
                ->whereDate('occurred_on', '<=', $end->toDateString())
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lines = OwnerStatementLine::query()
                ->where('owner_statement_id', $statement->getKey())
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->get();

            $this->ensureDraftIsFresh($statement, $owner, $entries, $lines, $start);

            $statement->forceFill([
                'status' => OwnerStatement::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by' => $user->getKey(),
            ])->save();

            OwnerLedgerEntry::withoutGlobalScopes()
                ->whereKey($entries->modelKeys())
                ->update(['locked_at' => now()]);

            return $statement->fresh(['lines', 'propertyOwner']);
        });
    }

    private function ensureDraftIsFresh(
        OwnerStatement $statement,
        PropertyOwner $owner,
        $entries,
        $lines,
        Carbon $start,
    ): void {
        if ($entries->contains(fn (OwnerLedgerEntry $entry) => (int) $entry->owner_statement_id !== (int) $statement->getKey())
            || $entries->count() !== $lines->count()) {
            $this->throwStaleDraft();
        }

        $linesByEntryNumber = $lines->keyBy(fn (OwnerStatementLine $line) => data_get($line->metadata, 'entry_number'));

        if ($linesByEntryNumber->count() !== $lines->count()) {
            $this->throwStaleDraft();
        }

        foreach ($entries as $entry) {
            $line = $linesByEntryNumber->get($entry->entry_number);

            if (! $line || ! $this->lineMatchesEntry($line, $entry)) {
                $this->throwStaleDraft();
            }
        }

        $openingMinor = Money::toMinor($this->balances->balance($owner, null, null, $start->copy()->subDay()));
        $totals = $this->totals($entries);
        $netMinor = $totals['credits'] - $totals['debits'];
        $expected = [
            'opening_balance' => $openingMinor,
            'rent_collected' => $totals['rent'],
            'late_fees_collected' => $totals['late'],
            'other_income' => $totals['other_income'],
            'expenses' => $totals['expenses'],
            'management_fees' => $totals['management_fees'],
            'owner_disbursements' => $totals['disbursements'],
            'net_activity' => $netMinor,
            'closing_balance' => $openingMinor + $netMinor,
        ];

        foreach ($expected as $attribute => $minor) {
            if (Money::toMinor($statement->{$attribute}) !== $minor) {
                $this->throwStaleDraft();
            }
        }
    }

    private function lineMatchesEntry(OwnerStatementLine $line, OwnerLedgerEntry $entry): bool
    {
        $creditMinor = $entry->direction === OwnerLedgerEntry::DIRECTION_CREDIT
            ? Money::toMinor($entry->amount)
            : 0;
        $debitMinor = $entry->direction === OwnerLedgerEntry::DIRECTION_DEBIT
            ? Money::toMinor($entry->amount)
            : 0;

        return $this->sameNullableId($line->property_id, $entry->property_id)
            && $this->sameNullableId($line->unit_id, $entry->unit_id)
            && $line->line_type === $entry->entry_type
            && $line->description === $entry->description
            && Money::toMinor($line->credit) === $creditMinor
            && Money::toMinor($line->debit) === $debitMinor
            && $line->occurred_on->toDateString() === $entry->occurred_on->toDateString()
            && $line->source_type === $entry->source_type
            && $this->sameNullableId($line->source_id, $entry->source_id)
            && data_get($line->metadata, 'source_key') === $entry->source_key;
    }

    private function sameNullableId(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return (int) $left === (int) $right;
    }

    private function throwStaleDraft(): never
    {
        throw ValidationException::withMessages([
            'statement' => 'This statement has new or changed financial activity. Regenerate the draft before finalizing.',
        ]);
    }

    private function totals($entries): array
    {
        $totals = [
            'credits' => 0, 'debits' => 0, 'rent' => 0, 'late' => 0,
            'other_income' => 0, 'expenses' => 0, 'management_fees' => 0,
            'disbursements' => 0,
        ];

        foreach ($entries as $entry) {
            $minor = Money::toMinor($entry->amount);
            $totals[$entry->direction === OwnerLedgerEntry::DIRECTION_CREDIT ? 'credits' : 'debits'] += $minor;

            match ($entry->entry_type) {
                OwnerLedgerEntry::TYPE_RENT_INCOME => $totals['rent'] += $minor,
                OwnerLedgerEntry::TYPE_LATE_FEE_INCOME => $totals['late'] += $minor,
                OwnerLedgerEntry::TYPE_PROPERTY_EXPENSE => $totals['expenses'] += $minor,
                OwnerLedgerEntry::TYPE_MANAGEMENT_FEE => $totals['management_fees'] += $minor,
                OwnerLedgerEntry::TYPE_OWNER_DISBURSEMENT => $totals['disbursements'] += $minor,
                OwnerLedgerEntry::TYPE_CREDIT_ADJUSTMENT => $totals['other_income'] += $minor,
                default => null,
            };
        }

        return $totals;
    }
}
