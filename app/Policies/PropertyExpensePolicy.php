<?php

namespace App\Policies;

use App\Models\PropertyExpense;
use App\Models\User;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Model;

class PropertyExpensePolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_EXPENSES;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_EXPENSES;

    public function create(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && (
            app(AccountAccess::class)->can($user, $account, AccountAccess::MANAGE_EXPENSES)
            || app(AccountAccess::class)->can($user, $account, AccountAccess::INITIATE_MAINTENANCE_EXPENSE)
        );
    }

    public function update(User $user, Model $record): bool
    {
        if (! parent::view($user, $record) || in_array($record->status, [PropertyExpense::STATUS_POSTED, PropertyExpense::STATUS_VOID], true)) {
            return false;
        }

        if (app(AccountAccess::class)->can($user, $record->account_id, AccountAccess::MANAGE_EXPENSES)) {
            return true;
        }

        return $record->category === 'maintenance'
            && $record->status === PropertyExpense::STATUS_DRAFT
            && app(AccountAccess::class)->can($user, $record->account_id, AccountAccess::INITIATE_MAINTENANCE_EXPENSE);
    }

    public function post(User $user, Model $record): bool
    {
        return $this->hasCapabilityForRecord($user, $record, AccountAccess::POST_EXPENSES);
    }

    public function approve(User $user, Model $record): bool
    {
        return $this->post($user, $record);
    }

    public function void(User $user, Model $record): bool
    {
        return $this->post($user, $record);
    }
}
