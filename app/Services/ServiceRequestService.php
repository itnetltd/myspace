<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AssetItem;
use App\Models\Inspection;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\ProviderCompany;
use App\Models\ProviderInvitation;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\AccountAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRequestService
{
    public function __construct(private readonly MarketplaceNumberGenerator $numbers) {}

    public function create(Account $account, array $attributes, array $lines, User $user): ServiceRequest
    {
        $this->authorize($account, $user);

        return DB::transaction(function () use ($account, $attributes, $lines, $user) {
            if (! in_array($attributes['request_type'] ?? null, ServiceRequest::TYPES, true)) {
                throw ValidationException::withMessages(['request_type' => 'Unsupported service request type.']);
            }

            $this->validateHierarchy($account, $attributes, $lines);

            if (! empty($attributes['maintenance_ticket_id'])) {
                $duplicate = ServiceRequest::withoutGlobalScopes()
                    ->where('account_id', $account->getKey())
                    ->where('maintenance_ticket_id', $attributes['maintenance_ticket_id'])
                    ->whereNotIn('status', [ServiceRequest::STATUS_CLOSED, ServiceRequest::STATUS_CANCELLED])
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['maintenance_ticket_id' => 'This maintenance ticket already has an active service request.']);
                }
            }

            $request = ServiceRequest::withoutGlobalScopes()->create([
                ...Arr::only($attributes, [
                    'property_owner_id', 'property_id', 'unit_id', 'lease_id',
                    'maintenance_ticket_id', 'inspection_id', 'request_type', 'title',
                    'description', 'priority', 'required_by',
                ]),
                'account_id' => $account->getKey(),
                'request_number' => $this->numbers->next('service_request'),
                'status' => ServiceRequest::STATUS_DRAFT,
                'created_by' => $user->getKey(),
            ]);

            foreach ($lines as $line) {
                $request->lines()->create($line);
            }

            return $request->fresh('lines');
        });
    }

    public function invite(ServiceRequest $request, array $providerCompanyIds, User $user, ?string $expiresAt = null): ServiceRequest
    {
        $account = Account::findOrFail($request->account_id);
        $this->authorize($account, $user);

        return DB::transaction(function () use ($request, $providerCompanyIds, $user, $expiresAt) {
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($request->getKey());
            if (! in_array($request->status, [ServiceRequest::STATUS_DRAFT, ServiceRequest::STATUS_REQUESTED], true)) {
                throw ValidationException::withMessages(['status' => 'Providers can only be invited before quotations are received.']);
            }

            $capability = match ($request->request_type) {
                ServiceRequest::TYPE_MAINTENANCE => 'maintenance',
                ServiceRequest::TYPE_PRODUCT_SUPPLY => 'supplier',
                ServiceRequest::TYPE_INSPECTION => 'inspection',
            };

            $providers = ProviderCompany::query()
                ->whereKey($providerCompanyIds)
                ->where('status', ProviderCompany::STATUS_ACTIVE)
                ->whereHas('capabilities', fn ($query) => $query->withoutGlobalScopes()->where('capability', $capability))
                ->get();

            if ($providers->count() !== count(array_unique($providerCompanyIds))) {
                throw ValidationException::withMessages(['providers' => 'Every invited provider must be active and eligible for this request type.']);
            }

            foreach ($providers as $provider) {
                ProviderInvitation::updateOrCreate(
                    ['service_request_id' => $request->getKey(), 'provider_company_id' => $provider->getKey()],
                    ['status' => ProviderInvitation::STATUS_INVITED, 'invited_at' => now(), 'expires_at' => $expiresAt, 'invited_by' => $user->getKey()],
                );
                $provider->users()->wherePivot('is_active', true)->get()->each->notify(new MarketplaceNotification([
                    'title' => 'New RFQ invitation', 'service_request_id' => $request->getKey(),
                    'request_number' => $request->request_number,
                ]));
            }

            $request->forceFill(['status' => ServiceRequest::STATUS_REQUESTED])->saveQuietly();

            return $request->fresh(['invitations.providerCompany', 'lines']);
        });
    }

    public function recordOwnerApproval(ServiceRequest $request, Quotation $quotation, User $user, string $reference): ServiceRequest
    {
        $account = Account::findOrFail($request->account_id);
        if (! app(AccountAccess::class)->can($user, $account, AccountAccess::RECORD_MARKETPLACE_OWNER_APPROVAL)) {
            abort(403);
        }

        return DB::transaction(function () use ($request, $quotation, $user, $reference) {
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($request->getKey());
            $quotation = Quotation::withoutGlobalScopes()->lockForUpdate()->findOrFail($quotation->getKey());

            if ((int) $quotation->service_request_id !== (int) $request->getKey()) {
                throw ValidationException::withMessages(['quotation' => 'The quotation belongs to another service request.']);
            }
            if ($quotation->status !== Quotation::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['quotation' => 'Owner approval can only be recorded for a submitted quotation.']);
            }

            $request->forceFill([
                'owner_approval_required' => true,
                'owner_approved_quotation_id' => $quotation->getKey(),
                'owner_approved_amount' => $quotation->total_amount,
                'owner_approved_currency' => $quotation->currency,
                'owner_approved_at' => now(), 'owner_approved_by' => $user->getKey(),
                'owner_approval_reference' => $reference,
            ])->save();

            return $request->refresh();
        });
    }

    private function authorize(Account $account, User $user): void
    {
        if (! app(AccountAccess::class)->can($user, $account, AccountAccess::MANAGE_MARKETPLACE)) {
            abort(403);
        }
    }

    private function validateHierarchy(Account $account, array $attributes, array $lines): void
    {
        $accountId = $account->getKey();
        $find = static function (string $model, string $key) use ($attributes, $accountId) {
            if (empty($attributes[$key])) {
                return null;
            }

            $record = $model::withoutGlobalScopes()->whereKey($attributes[$key])->where('account_id', $accountId)->first();
            if (! $record) {
                throw ValidationException::withMessages([$key => 'The selected record belongs to another account or does not exist.']);
            }

            return $record;
        };

        $owner = $find(PropertyOwner::class, 'property_owner_id');
        $property = $find(Property::class, 'property_id');
        $unit = $find(Unit::class, 'unit_id');
        $lease = $find(Lease::class, 'lease_id');
        $ticket = $find(MaintenanceTicket::class, 'maintenance_ticket_id');
        $inspection = $find(Inspection::class, 'inspection_id');

        if ($property && (! $owner || (int) $property->property_owner_id !== (int) $owner->getKey())) {
            throw ValidationException::withMessages(['property_id' => 'The property does not belong to the selected owner.']);
        }
        if ($unit && (! $property || (int) $unit->property_id !== (int) $property->getKey())) {
            throw ValidationException::withMessages(['unit_id' => 'The unit does not belong to the selected property.']);
        }
        if ($lease && (! $unit || (int) $lease->unit_id !== (int) $unit->getKey())) {
            throw ValidationException::withMessages(['lease_id' => 'The lease does not belong to the selected unit.']);
        }
        if ($ticket && (! $unit || (int) $ticket->unit_id !== (int) $unit->getKey()
            || ($ticket->lease_id && (int) $ticket->lease_id !== (int) ($lease?->getKey())))) {
            throw ValidationException::withMessages(['maintenance_ticket_id' => 'The maintenance ticket does not match the selected unit and lease.']);
        }
        if ($inspection && (! $unit || (int) $inspection->unit_id !== (int) $unit->getKey()
            || ($inspection->lease_id && (int) $inspection->lease_id !== (int) ($lease?->getKey())))) {
            throw ValidationException::withMessages(['inspection_id' => 'The inspection does not match the selected unit and lease.']);
        }
        if ($ticket && ($attributes['request_type'] ?? null) !== ServiceRequest::TYPE_MAINTENANCE) {
            throw ValidationException::withMessages(['maintenance_ticket_id' => 'Maintenance tickets may only source maintenance requests.']);
        }
        if ($inspection && ($attributes['request_type'] ?? null) !== ServiceRequest::TYPE_INSPECTION) {
            throw ValidationException::withMessages(['inspection_id' => 'Inspections may only source inspection requests.']);
        }

        foreach ($lines as $index => $line) {
            if (! empty($line['asset_item_id']) && ! AssetItem::withoutGlobalScopes()
                ->whereKey($line['asset_item_id'])->where('account_id', $accountId)->exists()) {
                throw ValidationException::withMessages(["lines.{$index}.asset_item_id" => 'The asset item belongs to another account.']);
            }
        }
    }
}
