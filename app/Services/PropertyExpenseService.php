<?php

namespace App\Services;

use App\Models\OwnerLedgerEntry;
use App\Models\PropertyExpense;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyExpenseService
{
    public function __construct(
        private readonly ManagementAgreementResolver $agreementResolver,
        private readonly OwnerLedgerService $ledger,
    ) {}

    public function applyApprovalRequirement(PropertyExpense $expense): void
    {
        $expense->loadMissing(['account', 'propertyOwner', 'property']);

        if (! $expense->account->isPropertyManagementCompany()) {
            $expense->owner_approval_required = false;

            return;
        }

        if ($expense->category !== 'maintenance') {
            $expense->owner_approval_required = false;

            return;
        }

        $agreement = $this->agreementResolver->resolve(
            $expense->account,
            $expense->propertyOwner,
            $expense->property,
            $expense->occurred_on,
            $expense->occurred_on,
        );

        $limitMinor = $agreement?->maintenance_approval_limit !== null
            ? Money::toMinor($agreement->maintenance_approval_limit)
            : null;

        $expense->owner_approval_required = $agreement === null
            || $limitMinor === null
            || Money::toMinor($expense->amount) > $limitMinor;

        if ($expense->owner_approval_required && ! $expense->owner_approved_at) {
            $expense->status = PropertyExpense::STATUS_AWAITING_APPROVAL;
        }
    }

    public function approve(PropertyExpense $expense, User $user): PropertyExpense
    {
        $expense->forceFill([
            'approved_at' => now(),
            'approved_by' => $user->getKey(),
            'status' => $expense->owner_approval_required && ! $expense->owner_approved_at
                ? PropertyExpense::STATUS_AWAITING_APPROVAL
                : PropertyExpense::STATUS_APPROVED,
        ])->save();

        return $expense->refresh();
    }

    public function recordOwnerApproval(PropertyExpense $expense, User $user, ?string $note = null): PropertyExpense
    {
        $expense->forceFill([
            'owner_approved_at' => now(),
            'owner_approved_by' => $user->getKey(),
            'notes' => $note ? trim(($expense->notes ? $expense->notes."\n" : '').'Owner approval recorded: '.$note) : $expense->notes,
            'status' => $expense->approved_at ? PropertyExpense::STATUS_APPROVED : $expense->status,
        ])->save();

        return $expense->refresh();
    }

    public function post(PropertyExpense $expense, User $user): PropertyExpense
    {
        return DB::transaction(function () use ($expense, $user) {
            $expense = PropertyExpense::withoutGlobalScopes()->lockForUpdate()->findOrFail($expense->getKey());

            if ($expense->owner_approval_required && ! $expense->owner_approved_at) {
                throw ValidationException::withMessages([
                    'owner_approval' => 'Owner approval must be recorded before this expense can be posted.',
                ]);
            }

            if (! $expense->approved_at) {
                throw ValidationException::withMessages([
                    'approval' => 'Internal approval is required before this expense can be posted.',
                ]);
            }

            $this->ledger->post(
                ['source_type' => 'property_expense', 'source_id' => $expense->getKey(), 'source_key' => 'expense'],
                [
                    'account_id' => $expense->account_id,
                    'property_owner_id' => $expense->property_owner_id,
                    'property_id' => $expense->property_id,
                    'unit_id' => $expense->unit_id,
                    'lease_id' => $expense->lease_id,
                    'entry_number' => 'LE-EXP-'.$expense->getKey(),
                    'entry_type' => OwnerLedgerEntry::TYPE_PROPERTY_EXPENSE,
                    'direction' => OwnerLedgerEntry::DIRECTION_DEBIT,
                    'amount' => $expense->amount,
                    'currency' => $expense->currency,
                    'occurred_on' => $expense->occurred_on,
                    'description' => $expense->description,
                    'metadata' => ['category' => $expense->category, 'expense_number' => $expense->expense_number],
                    'created_by' => $user->getKey(),
                ],
            );

            $expense->forceFill(['status' => PropertyExpense::STATUS_POSTED])->saveQuietly();

            return $expense->refresh();
        });
    }

    public function void(PropertyExpense $expense, User $user, string $reason): PropertyExpense
    {
        return DB::transaction(function () use ($expense, $user, $reason) {
            if ($expense->status !== PropertyExpense::STATUS_POSTED) {
                throw ValidationException::withMessages(['status' => 'Only posted expenses can be voided.']);
            }

            $this->ledger->post(
                ['source_type' => 'property_expense', 'source_id' => $expense->getKey(), 'source_key' => 'void'],
                [
                    'account_id' => $expense->account_id,
                    'property_owner_id' => $expense->property_owner_id,
                    'property_id' => $expense->property_id,
                    'unit_id' => $expense->unit_id,
                    'lease_id' => $expense->lease_id,
                    'entry_number' => 'LE-EXP-'.$expense->getKey().'-VOID',
                    'entry_type' => OwnerLedgerEntry::TYPE_CREDIT_ADJUSTMENT,
                    'direction' => OwnerLedgerEntry::DIRECTION_CREDIT,
                    'amount' => $expense->amount,
                    'currency' => $expense->currency,
                    'occurred_on' => now()->toDateString(),
                    'description' => 'Void expense '.$expense->expense_number.': '.$reason,
                    'metadata' => ['reverses' => 'expense', 'reason' => $reason],
                    'created_by' => $user->getKey(),
                ],
            );

            $expense->forceFill([
                'status' => PropertyExpense::STATUS_VOID,
                'notes' => trim(($expense->notes ? $expense->notes."\n" : '').'Void reason: '.$reason),
            ])->saveQuietly();

            return $expense->refresh();
        });
    }
}
