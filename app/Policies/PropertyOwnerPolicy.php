<?php

namespace App\Policies;

use App\Support\AccountAccess;

class PropertyOwnerPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_OWNERS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_OWNERS;
}
