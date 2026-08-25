<?php

namespace App\Services;

use App\Models\ProviderCompany;
use App\Models\ServiceAppointment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Notifications\MarketplaceNotification;
use App\Support\AccountAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceAppointmentService
{
    public function __construct(
        private readonly WorkOrderAccessService $access,
        private readonly WorkOrderActivityService $activities,
    ) {}

    public function propose(WorkOrder $workOrder, array $attributes, User $user): ServiceAppointment
    {
        $this->access->authorizeProviderOperation($user, $workOrder);
        if (empty($attributes['scheduled_start']) || empty($attributes['scheduled_end'])) {
            throw ValidationException::withMessages(['scheduled_start' => 'Appointment start and end are required.']);
        }
        $start = CarbonImmutable::parse($attributes['scheduled_start'] ?? null);
        $end = CarbonImmutable::parse($attributes['scheduled_end'] ?? null);
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['scheduled_end' => 'Appointment end must be after its start.']);
        }

        return DB::transaction(function () use ($workOrder, $attributes, $user, $start, $end) {
            ProviderCompany::lockForUpdate()->findOrFail($workOrder->provider_company_id);
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $this->access->authorizeProviderOperation($user, $workOrder);
            if (! in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_REVISION_REQUESTED], true)) {
                throw ValidationException::withMessages(['work_order' => 'Appointments cannot be proposed in the current work-order state.']);
            }
            if (ServiceAppointment::withoutGlobalScopes()->where('work_order_id', $workOrder->getKey())
                ->whereIn('status', [ServiceAppointment::STATUS_PROPOSED, ServiceAppointment::STATUS_CONFIRMED])->exists()) {
                throw ValidationException::withMessages(['appointment' => 'Resolve the current appointment before proposing another.']);
            }

            $appointment = ServiceAppointment::withoutGlobalScopes()->create([
                'work_order_id' => $workOrder->getKey(), 'provider_company_id' => $workOrder->provider_company_id,
                'scheduled_start' => $start, 'scheduled_end' => $end,
                'status' => ServiceAppointment::STATUS_PROPOSED,
                'location_notes' => $attributes['location_notes'] ?? null,
                'access_instructions' => $attributes['access_instructions'] ?? null,
                'proposed_by' => $user->getKey(), 'proposed_at' => now(),
                'reschedule_notes' => $attributes['reschedule_notes'] ?? null,
            ]);
            $this->activities->record($workOrder, 'appointment_proposed', 'A service appointment was proposed.', $user, [
                'appointment_id' => $appointment->getKey(), 'scheduled_start' => $start->toIso8601String(),
                'scheduled_end' => $end->toIso8601String(),
            ]);
            $request = ServiceRequest::withoutGlobalScopes()->findOrFail($workOrder->service_request_id);
            $request->creator?->notify(new MarketplaceNotification([
                'title' => 'Appointment proposed', 'work_order_id' => $workOrder->getKey(),
                'appointment_id' => $appointment->getKey(), 'scheduled_start' => $start->toIso8601String(),
                'scheduled_end' => $end->toIso8601String(),
            ]));

            return $appointment->fresh();
        });
    }

    public function confirm(ServiceAppointment $appointment, User $user): ServiceAppointment
    {
        $workOrder = WorkOrder::withoutGlobalScopes()->findOrFail($appointment->work_order_id);
        $this->access->authorizeAccount($user, $workOrder, AccountAccess::CONFIRM_MARKETPLACE_APPOINTMENTS);

        return DB::transaction(function () use ($appointment, $user) {
            $appointment = ServiceAppointment::withoutGlobalScopes()->lockForUpdate()->findOrFail($appointment->getKey());
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($appointment->work_order_id);
            $this->access->authorizeAccount($user, $workOrder, AccountAccess::CONFIRM_MARKETPLACE_APPOINTMENTS);
            if ($appointment->status !== ServiceAppointment::STATUS_PROPOSED
                || ! in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED], true)) {
                throw ValidationException::withMessages(['appointment' => 'Only a current proposed appointment can be confirmed.']);
            }

            $appointment->forceFill([
                'status' => ServiceAppointment::STATUS_CONFIRMED,
                'confirmed_by' => $user->getKey(), 'confirmed_at' => now(),
            ])->save();
            $workOrder->forceFill([
                'status' => WorkOrder::STATUS_SCHEDULED,
                'scheduled_start' => $appointment->scheduled_start,
                'scheduled_completion' => $appointment->scheduled_end,
            ])->save();
            $this->activities->record($workOrder, 'appointment_confirmed', 'The Account confirmed the service appointment.', $user, [
                'appointment_id' => $appointment->getKey(),
            ]);
            $this->notifyAssigned($workOrder, 'Appointment confirmed', $appointment);

            return $appointment->refresh();
        });
    }

    public function requestReschedule(ServiceAppointment $appointment, User $user, string $notes): ServiceAppointment
    {
        if (blank($notes)) {
            throw ValidationException::withMessages(['reschedule_notes' => 'Explain why the appointment needs rescheduling.']);
        }
        $workOrder = WorkOrder::withoutGlobalScopes()->findOrFail($appointment->work_order_id);
        $this->access->authorizeAccount($user, $workOrder, AccountAccess::CONFIRM_MARKETPLACE_APPOINTMENTS);

        return DB::transaction(function () use ($appointment, $user, $notes) {
            $appointment = ServiceAppointment::withoutGlobalScopes()->lockForUpdate()->findOrFail($appointment->getKey());
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($appointment->work_order_id);
            $this->access->authorizeAccount($user, $workOrder, AccountAccess::CONFIRM_MARKETPLACE_APPOINTMENTS);
            if (! in_array($appointment->status, [ServiceAppointment::STATUS_PROPOSED, ServiceAppointment::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages(['appointment' => 'This appointment can no longer be rescheduled.']);
            }
            $appointment->forceFill([
                'status' => ServiceAppointment::STATUS_RESCHEDULE_REQUESTED,
                'cancelled_by' => $user->getKey(), 'cancelled_at' => now(), 'reschedule_notes' => $notes,
            ])->save();
            if ($workOrder->status === WorkOrder::STATUS_SCHEDULED) {
                $workOrder->forceFill(['status' => WorkOrder::STATUS_PENDING])->save();
            }
            $this->activities->record($workOrder, 'appointment_reschedule_requested', 'The Account requested an appointment change.', $user, [
                'appointment_id' => $appointment->getKey(), 'notes' => $notes,
            ]);
            $this->notifyAssigned($workOrder, 'Appointment reschedule requested', $appointment);

            return $appointment->refresh();
        });
    }

    private function notifyAssigned(WorkOrder $workOrder, string $title, ServiceAppointment $appointment): void
    {
        WorkOrderAssignment::withoutGlobalScopes()->where('work_order_id', $workOrder->getKey())
            ->whereIn('status', WorkOrderAssignment::ACTIVE_STATUSES)->with('membership.user')->get()
            ->pluck('membership.user')->filter()->unique('id')->each->notify(new MarketplaceNotification([
                'title' => $title, 'work_order_id' => $workOrder->getKey(),
                'appointment_id' => $appointment->getKey(),
                'scheduled_start' => $appointment->scheduled_start->toIso8601String(),
            ]));
    }
}
