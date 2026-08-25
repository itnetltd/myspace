<?php

namespace App\Policies;

use App\Models\User;
use App\Services\WorkOrderAccessService;
use Illuminate\Database\Eloquent\Model;

class WorkOrderChildPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Model $record): bool
    {
        $workOrder = $record->workOrder()->withoutGlobalScopes()->first();

        return $workOrder && app(WorkOrderAccessService::class)->canView($user, $workOrder);
    }

    public function download(User $user, Model $record): bool
    {
        return $this->view($user, $record);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
