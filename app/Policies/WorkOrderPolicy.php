<?php

namespace App\Policies;

use App\Support\ProviderAccess;

class WorkOrderPolicy extends ProviderOwnedPolicy
{
    protected const ROLES = ProviderAccess::FULFILMENT_ROLES;

    public function delete($user, $record): bool
    {
        return false;
    }
}
