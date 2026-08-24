<?php

namespace App\Policies;

use App\Support\AccountAccess;

class ContractPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_CONTRACTS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_CONTRACTS;
}
