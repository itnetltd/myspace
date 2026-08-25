<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use App\Support\ProviderAccess;

class QuotationPolicy extends ProviderOwnedPolicy
{
    protected const ROLES = ProviderAccess::QUOTE_ROLES;

    public function viewAny(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return parent::viewAny($user)
            || ($account && app(AccountAccess::class)->can($user, $account, AccountAccess::VIEW_MARKETPLACE));
    }

    public function view(User $user, $record): bool
    {
        if (parent::view($user, $record)) {
            return true;
        }
        $account = app(CurrentAccount::class)->forUser($user);
        $request = $record->serviceRequest()->withoutGlobalScopes()->first();

        return $account && $request && (int) $request->account_id === (int) $account->getKey()
            && app(AccountAccess::class)->can($user, $account, AccountAccess::VIEW_MARKETPLACE);
    }

    public function update(User $user, $record): bool
    {
        return $record->status === Quotation::STATUS_DRAFT && parent::update($user, $record);
    }

    public function accept(User $user, $record): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);
        $request = $record->serviceRequest()->withoutGlobalScopes()->first();

        return $account && $request && (int) $request->account_id === (int) $account->getKey()
            && app(AccountAccess::class)->can($user, $account, AccountAccess::ACCEPT_MARKETPLACE_QUOTES);
    }

    public function delete(User $user, $record): bool
    {
        return false;
    }
}
