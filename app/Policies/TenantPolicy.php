<?php

namespace App\Policies;

use App\Support\AccountAccess;

class TenantPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_TENANTS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_TENANTS;
}
