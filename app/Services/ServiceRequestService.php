<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProviderCompany;
use App\Models\ProviderInvitation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\AccountAccess;
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
                ...$attributes,
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

    public function recordOwnerApproval(ServiceRequest $request, User $user, string $reference): ServiceRequest
    {
        $account = Account::findOrFail($request->account_id);
        $this->authorize($account, $user);
        $request->forceFill([
            'owner_approved_at' => now(), 'owner_approved_by' => $user->getKey(),
            'owner_approval_reference' => $reference,
        ])->save();

        return $request->refresh();
    }

    private function authorize(Account $account, User $user): void
    {
        if (! app(AccountAccess::class)->can($user, $account, AccountAccess::MANAGE_MARKETPLACE)) {
            abort(403);
        }
    }
}
