<?php

namespace App\Policies;

use App\Support\AccountAccess;

class PropertyPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_PROPERTIES;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_PROPERTIES;
}
