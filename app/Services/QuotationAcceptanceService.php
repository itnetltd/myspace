<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProviderCompany;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\MarketplaceNotification;
use App\Support\AccountAccess;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationAcceptanceService
{
    public function __construct(
        private readonly ManagementAgreementResolver $agreements,
        private readonly MarketplaceNumberGenerator $numbers,
    ) {}

    public function accept(Quotation $quotation, User $user): Quotation
    {
        $quotation = Quotation::withoutGlobalScopes()->with('serviceRequest')->findOrFail($quotation->getKey());
        $request = $quotation->serviceRequest;
        $account = Account::findOrFail($request->account_id);

        if (! app(AccountAccess::class)->can($user, $account, AccountAccess::ACCEPT_MARKETPLACE_QUOTES)) {
            abort(403);
        }

        $requiresApproval = $this->requiresOwnerApproval($request, $quotation, $account);
        if ($requiresApproval && ! $request->owner_approved_at) {
            ServiceRequest::withoutGlobalScopes()->whereKey($request->getKey())->update(['owner_approval_required' => true]);
            throw ValidationException::withMessages([
                'owner_approval' => 'Owner approval must be recorded before this quotation can be accepted.',
            ]);
        }

        return DB::transaction(function () use ($quotation, $user, $requiresApproval) {
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($quotation->service_request_id);
            $quotation = Quotation::withoutGlobalScopes()->lockForUpdate()->findOrFail($quotation->getKey());

            if ($request->accepted_quotation_id) {
                if ((int) $request->accepted_quotation_id === (int) $quotation->getKey()) {
                    return $quotation;
                }
                throw ValidationException::withMessages(['quotation' => 'This request already has an accepted quotation.']);
            }
            if ($quotation->status !== Quotation::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['quotation' => 'Only a submitted quotation can be accepted.']);
            }

            $quotation->forceFill([
                'status' => Quotation::STATUS_ACCEPTED, 'accepted_at' => now(), 'accepted_by' => $user->getKey(),
            ])->save();
            Quotation::withoutGlobalScopes()
                ->where('service_request_id', $request->getKey())->whereKeyNot($quotation->getKey())
                ->where('status', Quotation::STATUS_SUBMITTED)
                ->update(['status' => Quotation::STATUS_REJECTED, 'rejected_at' => now()]);

            Quotation::withoutGlobalScopes()->where('service_request_id', $request->getKey())
                ->whereKeyNot($quotation->getKey())->where('status', Quotation::STATUS_REJECTED)
                ->pluck('provider_company_id')->unique()->each(function ($providerCompanyId) use ($request) {
                    ProviderCompany::find($providerCompanyId)?->users()->wherePivot('is_active', true)->get()
                        ->each->notify(new MarketplaceNotification([
                            'title' => 'Quotation not selected', 'service_request_id' => $request->getKey(),
                        ]));
                });

            $request->forceFill([
                'accepted_quotation_id' => $quotation->getKey(),
                'status' => ServiceRequest::STATUS_QUOTE_ACCEPTED,
                'owner_approval_required' => $requiresApproval,
            ])->saveQuietly();

            WorkOrder::withoutGlobalScopes()->create([
                'service_request_id' => $request->getKey(), 'quotation_id' => $quotation->getKey(),
                'provider_company_id' => $quotation->provider_company_id,
                'work_order_number' => $this->numbers->next('work_order'),
                'status' => WorkOrder::STATUS_PENDING, 'created_by' => $user->getKey(),
            ]);

            ProviderCompany::find($quotation->provider_company_id)?->users()
                ->wherePivot('is_active', true)->get()->each->notify(new MarketplaceNotification([
                    'title' => 'Quotation accepted', 'quotation_id' => $quotation->getKey(),
                    'service_request_id' => $request->getKey(),
                ]));

            return $quotation->fresh(['serviceRequest.workOrder', 'lines']);
        });
    }

    private function requiresOwnerApproval(ServiceRequest $request, Quotation $quotation, Account $account): bool
    {
        if (! $account->isPropertyManagementCompany()
            || ! in_array($request->request_type, [ServiceRequest::TYPE_MAINTENANCE, ServiceRequest::TYPE_PRODUCT_SUPPLY], true)
            || ! $request->property_owner_id || ! $request->property_id) {
            return false;
        }

        $agreement = $this->agreements->resolve(
            $account,
            $request->propertyOwner()->withoutGlobalScopes()->firstOrFail(),
            $request->property()->withoutGlobalScopes()->firstOrFail(),
            now(),
            now(),
        );

        return $agreement?->maintenance_approval_limit === null
            || Money::toMinor($quotation->total_amount) > Money::toMinor($agreement->maintenance_approval_limit);
    }
}
