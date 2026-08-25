<?php

namespace App\Services;

use App\Models\ProviderCompany;
use App\Models\ProviderCompanyMembership;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Notifications\MarketplaceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderAssignmentService
{
    public function __construct(
        private readonly WorkOrderAccessService $access,
        private readonly WorkOrderActivityService $activities,
    ) {}

    public function assign(WorkOrder $workOrder, ProviderCompanyMembership $membership, string $type, User $user, bool $primary = false, ?string $notes = null): WorkOrderAssignment
    {
        $this->access->authorizeProviderManager($user, $workOrder);

        return DB::transaction(function () use ($workOrder, $membership, $type, $user, $primary, $notes) {
            ProviderCompany::lockForUpdate()->findOrFail($workOrder->provider_company_id);
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $membership = ProviderCompanyMembership::withoutGlobalScopes()->lockForUpdate()->findOrFail($membership->getKey());
            $this->access->authorizeProviderManager($user, $workOrder);
            $request = ServiceRequest::withoutGlobalScopes()->findOrFail($workOrder->service_request_id);

            if ((int) $membership->provider_company_id !== (int) $workOrder->provider_company_id) {
                throw ValidationException::withMessages(['membership' => 'The assignee belongs to another provider company.']);
            }
            if (! $membership->is_active) {
                throw ValidationException::withMessages(['membership' => 'The assignee membership is inactive.']);
            }
            if (! in_array($membership->role, $this->access->eligibleRoles($request->request_type), true)) {
                throw ValidationException::withMessages(['membership' => 'The provider role is not eligible for this request type.']);
            }
            if (! in_array($type, WorkOrderAssignment::TYPES, true)) {
                throw ValidationException::withMessages(['assignment_type' => 'Unsupported assignment type.']);
            }
            if (WorkOrderAssignment::withoutGlobalScopes()->where('work_order_id', $workOrder->getKey())
                ->where('provider_company_membership_id', $membership->getKey())
                ->whereIn('status', WorkOrderAssignment::ACTIVE_STATUSES)->exists()) {
                throw ValidationException::withMessages(['membership' => 'This member already has an active assignment on the work order.']);
            }
            if ($primary) {
                WorkOrderAssignment::withoutGlobalScopes()->where('work_order_id', $workOrder->getKey())->update(['is_primary' => false]);
            }

            $assignment = WorkOrderAssignment::withoutGlobalScopes()->create([
                'work_order_id' => $workOrder->getKey(), 'provider_company_id' => $workOrder->provider_company_id,
                'provider_company_membership_id' => $membership->getKey(), 'assignment_type' => $type,
                'is_primary' => $primary, 'status' => WorkOrderAssignment::STATUS_ASSIGNED,
                'assigned_by' => $user->getKey(), 'assigned_at' => now(), 'notes' => $notes,
            ]);
            $this->activities->record($workOrder, 'staff_assigned', 'Provider staff assigned as '.$type.'.', $user, [
                'assignment_id' => $assignment->getKey(), 'assignment_type' => $type,
            ]);
            $membership->user?->notify(new MarketplaceNotification([
                'title' => 'Work order assigned', 'work_order_id' => $workOrder->getKey(),
                'work_order_number' => $workOrder->work_order_number, 'job_type' => $request->request_type,
                'scheduled_start' => $workOrder->scheduled_start?->toIso8601String(),
            ]));

            return $assignment->fresh(['membership.user', 'workOrder']);
        });
    }
}
