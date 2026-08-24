<?php

namespace App\Services;

use App\Models\OwnerLedgerEntry;
use App\Models\OwnerStatement;
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

    public function generateDraft(PropertyOwner $owner, string $periodStart, string $periodEnd, User $user): OwnerStatement
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        if ($start->gt($end)) {
            throw ValidationException::withMessages(['period_end' => 'The statement end date must follow its start date.']);
        }

        return DB::transaction(function () use ($owner, $start, $end, $user) {
            $statement = OwnerStatement::withoutGlobalScopes()
                ->where('account_id', $owner->account_id)
                ->where('property_owner_id', $owner->getKey())
                ->whereDate('period_start', $start)
                ->whereDate('period_end', $end)
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

            $openingMinor = Money::toMinor($this->balances->balance($owner, null, null, $start->copy()->subDay()));
            $totals = $this->totals($entries);
            $netMinor = $totals['credits'] - $totals['debits'];
            $attributes = [
                'account_id' => $owner->account_id,
                'property_owner_id' => $owner->getKey(),
                'statement_number' => 'OS-'.$owner->getKey().'-'.$start->format('Ymd').'-'.$end->format('Ymd'),
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

            $statement->forceFill([
                'status' => OwnerStatement::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by' => $user->getKey(),
            ])->save();

            OwnerLedgerEntry::withoutGlobalScopes()
                ->where('owner_statement_id', $statement->getKey())
                ->update(['locked_at' => now()]);

            return $statement->fresh(['lines', 'propertyOwner']);
        });
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
