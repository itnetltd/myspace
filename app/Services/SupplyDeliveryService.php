<?php

namespace App\Services;

use App\Models\ProviderCompany;
use App\Models\ProviderCompanyMembership;
use App\Models\ServiceRequest;
use App\Models\SupplyDelivery;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\MarketplaceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyDeliveryService
{
    public function __construct(
        private readonly WorkOrderAccessService $access,
        private readonly WorkOrderActivityService $activities,
        private readonly WorkOrderService $workOrders,
    ) {}

    public function create(WorkOrder $workOrder, array $attributes, User $user): SupplyDelivery
    {
        $this->access->authorizeProviderOperation($user, $workOrder);

        return DB::transaction(function () use ($workOrder, $attributes, $user) {
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $this->access->authorizeProviderOperation($user, $workOrder);
            $request = ServiceRequest::withoutGlobalScopes()->findOrFail($workOrder->service_request_id);
            $this->ensureOperationalWorkOrder($workOrder, 'New deliveries');
            if ($request->request_type !== ServiceRequest::TYPE_PRODUCT_SUPPLY) {
                throw ValidationException::withMessages(['work_order' => 'Deliveries are only available for product-supply work orders.']);
            }
            $membershipId = $attributes['assigned_membership_id'] ?? null;
            if ($membershipId) {
                $membership = ProviderCompanyMembership::withoutGlobalScopes()->find($membershipId);
                if (! $membership || ! $membership->is_active
                    || (int) $membership->provider_company_id !== (int) $workOrder->provider_company_id
                    || ! in_array($membership->role, $this->access->eligibleRoles($request->request_type), true)) {
                    throw ValidationException::withMessages(['assigned_membership_id' => 'The delivery assignee is not an eligible active provider member.']);
                }
            }

            return SupplyDelivery::withoutGlobalScopes()->create([
                'work_order_id' => $workOrder->getKey(), 'provider_company_id' => $workOrder->provider_company_id,
                'status' => SupplyDelivery::STATUS_PREPARING,
                'scheduled_for' => $attributes['scheduled_for'] ?? null,
                'delivery_reference' => $attributes['delivery_reference'] ?? null,
                'assigned_membership_id' => $membershipId, 'notes' => $attributes['notes'] ?? null,
            ]);
        });
    }

    public function transition(SupplyDelivery $delivery, string $status, array $attributes, User $user): SupplyDelivery
    {
        $workOrder = WorkOrder::withoutGlobalScopes()->findOrFail($delivery->work_order_id);
        $this->access->authorizeProviderOperation($user, $workOrder);

        return DB::transaction(function () use ($delivery, $status, $attributes, $user) {
            ProviderCompany::lockForUpdate()->findOrFail($delivery->provider_company_id);
            $delivery = SupplyDelivery::withoutGlobalScopes()->lockForUpdate()->findOrFail($delivery->getKey());
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($delivery->work_order_id);
            $this->access->authorizeProviderOperation($user, $workOrder);
            $this->ensureOperationalWorkOrder($workOrder, 'Delivery transitions');
            $allowed = match ($delivery->status) {
                SupplyDelivery::STATUS_PREPARING => [SupplyDelivery::STATUS_READY, SupplyDelivery::STATUS_CANCELLED],
                SupplyDelivery::STATUS_READY => [SupplyDelivery::STATUS_DISPATCHED, SupplyDelivery::STATUS_CANCELLED],
                SupplyDelivery::STATUS_DISPATCHED => [SupplyDelivery::STATUS_DELIVERED],
                default => [],
            };
            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'This delivery transition is not allowed.']);
            }

            $changes = ['status' => $status];
            if ($status === SupplyDelivery::STATUS_DISPATCHED) {
                $changes['dispatched_at'] = now();
            }
            if ($status === SupplyDelivery::STATUS_DELIVERED) {
                $changes['delivered_at'] = now();
                $changes['recipient_name'] = $attributes['recipient_name'] ?? null;
            }
            $delivery->forceFill($changes)->save();

            if (in_array($status, [SupplyDelivery::STATUS_DISPATCHED, SupplyDelivery::STATUS_DELIVERED], true)) {
                $type = $status === SupplyDelivery::STATUS_DISPATCHED ? 'delivery_dispatched' : 'delivery_delivered';
                $title = $status === SupplyDelivery::STATUS_DISPATCHED ? 'Delivery dispatched' : 'Delivery delivered';
                $this->activities->record($workOrder, $type, $title.'.', $user, ['delivery_id' => $delivery->getKey()]);
                ServiceRequest::withoutGlobalScopes()->find($workOrder->service_request_id)?->creator?->notify(
                    new MarketplaceNotification(['title' => $title, 'work_order_id' => $workOrder->getKey(), 'delivery_id' => $delivery->getKey()]),
                );
            }

            return $delivery->refresh();
        });
    }

    public function addEvidence(SupplyDelivery $delivery, array $evidence, User $user): SupplyDelivery
    {
        $workOrder = WorkOrder::withoutGlobalScopes()->findOrFail($delivery->work_order_id);
        $this->access->authorizeProviderOperation($user, $workOrder);
        foreach ($evidence as $index => $item) {
            if (! in_array($item['evidence_type'] ?? null, ['delivery_receipt', 'delivery_photo', 'serial_number', 'model_received', 'other'], true)) {
                throw ValidationException::withMessages(["evidence.{$index}" => 'Unsupported delivery evidence type.']);
            }
        }

        DB::transaction(function () use ($delivery, $workOrder, $evidence, $user) {
            $delivery = SupplyDelivery::withoutGlobalScopes()->lockForUpdate()->findOrFail($delivery->getKey());
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $this->access->authorizeProviderOperation($user, $workOrder);
            $this->ensureOperationalWorkOrder($workOrder, 'Delivery evidence');
            if (! in_array($delivery->status, [SupplyDelivery::STATUS_DISPATCHED, SupplyDelivery::STATUS_DELIVERED], true)) {
                throw ValidationException::withMessages(['delivery' => 'Delivery evidence can only be recorded after dispatch.']);
            }
            $ids = $this->workOrders->storeEvidence($workOrder, null, $evidence, $user);
            $this->activities->record($workOrder, 'delivery_evidence_added', 'Delivery evidence added.', $user, [
                'delivery_id' => $delivery->getKey(), 'evidence_ids' => $ids,
            ]);
        });

        return $delivery->refresh();
    }

    private function ensureOperationalWorkOrder(WorkOrder $workOrder, string $operation): void
    {
        if (in_array($workOrder->status, [
            WorkOrder::STATUS_COMPLETION_SUBMITTED,
            WorkOrder::STATUS_COMPLETED,
            WorkOrder::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'work_order' => $operation.' are unavailable after completion review begins or work is terminal.',
            ]);
        }
    }
}
