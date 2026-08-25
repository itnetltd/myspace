<?php

namespace App\Filament\Provider\Widgets;

use App\Models\ProviderCompanyMembership;
use App\Models\ServiceAppointment;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Support\CurrentProviderCompany;
use App\Support\ProviderAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ProviderOperationsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $query = $this->visibleWorkOrders();

        return [
            Stat::make('My assigned jobs', (clone $query)->count()),
            Stat::make("Today's appointments", ServiceAppointment::query()
                ->whereHas('workOrder', fn (Builder $workOrder) => $workOrder->whereIn('id', (clone $query)->select('id')))
                ->whereDate('scheduled_start', today())->count()),
            Stat::make('Pending', (clone $query)->whereIn('status', [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED])->count()),
            Stat::make('In progress', (clone $query)->whereIn('status', [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_REVISION_REQUESTED])->count()),
            Stat::make('Awaiting customer review', (clone $query)->where('status', WorkOrder::STATUS_COMPLETION_SUBMITTED)->count()),
            Stat::make('Completed recently', (clone $query)->where('status', WorkOrder::STATUS_COMPLETED)
                ->where('completed_at', '>=', now()->subDays(30))->count()),
        ];
    }

    private function visibleWorkOrders(): Builder
    {
        $query = WorkOrder::query();
        $company = app(CurrentProviderCompany::class)->company();
        $role = $company ? app(ProviderAccess::class)->role(auth()->user(), $company) : null;
        if (in_array($role, [...ProviderAccess::MANAGE_COMPANY_ROLES, 'viewer'], true)) {
            return $query;
        }

        $membershipId = ProviderCompanyMembership::withoutGlobalScopes()
            ->where('provider_company_id', $company?->getKey())->where('user_id', auth()->id())
            ->where('is_active', true)->value('id');

        return $query->whereHas('assignments', fn (Builder $assignment) => $assignment
            ->where('provider_company_membership_id', $membershipId ?: 0)
            ->whereNotIn('status', [WorkOrderAssignment::STATUS_CANCELLED, WorkOrderAssignment::STATUS_DECLINED]));
    }
}
