<?php

namespace App\Policies;

use App\Support\AccountAccess;

class RentPaymentPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_PAYMENTS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_PAYMENTS;
}
