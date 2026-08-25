<?php

namespace App\Policies;

use App\Models\ProviderInvoice;
use App\Models\User;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use App\Support\ProviderAccess;

class ProviderInvoicePolicy extends ProviderOwnedPolicy
{
    protected const ROLES = ProviderAccess::INVOICE_ROLES;

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

        return $account && (int) $record->account_id === (int) $account->getKey()
            && app(AccountAccess::class)->can($user, $account, AccountAccess::VIEW_MARKETPLACE);
    }

    public function update($user, $record): bool
    {
        return $record->status === ProviderInvoice::STATUS_DRAFT && parent::update($user, $record);
    }

    public function approve(User $user, $record): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && (int) $record->account_id === (int) $account->getKey()
            && app(AccountAccess::class)->can($user, $account, AccountAccess::APPROVE_PROVIDER_INVOICES);
    }

    public function post(User $user, $record): bool
    {
        return $this->approve($user, $record)
            && app(AccountAccess::class)->can($user, $record->account_id, AccountAccess::POST_EXPENSES);
    }

    public function delete($user, $record): bool
    {
        return false;
    }
}
