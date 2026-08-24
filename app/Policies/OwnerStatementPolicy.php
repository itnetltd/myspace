<?php

namespace App\Policies;

use App\Models\OwnerStatement;
use App\Models\User;
use App\Support\AccountAccess;
use Illuminate\Database\Eloquent\Model;

class OwnerStatementPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_OWNER_STATEMENTS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_OWNER_STATEMENTS;

    public function update(User $user, Model $record): bool
    {
        return $record->status === OwnerStatement::STATUS_DRAFT && parent::update($user, $record);
    }

    public function finalize(User $user, Model $record): bool
    {
        return $record->status === OwnerStatement::STATUS_DRAFT
            && $this->hasCapabilityForRecord($user, $record, AccountAccess::FINALIZE_OWNER_STATEMENTS);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
