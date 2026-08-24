<?php

namespace App\Policies;

use App\Support\AccountAccess;

class UnitPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_UNITS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_UNITS;
}
