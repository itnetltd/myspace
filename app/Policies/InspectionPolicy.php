<?php

namespace App\Policies;

use App\Support\AccountAccess;

class InspectionPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_INSPECTIONS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_INSPECTIONS;
}
