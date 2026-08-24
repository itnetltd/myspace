<?php

namespace App\Policies;

use App\Support\AccountAccess;

class AssetPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_ASSETS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_ASSETS;
}
