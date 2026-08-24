<?php

namespace App\Policies;

use App\Support\AccountAccess;

class MaintenanceTicketPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_MAINTENANCE;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_MAINTENANCE;
}
