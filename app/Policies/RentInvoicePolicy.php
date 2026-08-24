<?php

namespace App\Policies;

use App\Support\AccountAccess;

class RentInvoicePolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_INVOICES;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_INVOICES;
}
