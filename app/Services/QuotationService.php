<?php

namespace App\Services;

use App\Models\ProviderCompany;
use App\Models\ProviderInvitation;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\ProviderAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly MarketplaceNumberGenerator $numbers,
        private readonly QuotationCalculator $calculator,
    ) {}

    public function saveDraft(ServiceRequest $request, ProviderCompany $provider, array $attributes, array $lines, User $user): Quotation
    {
        $this->authorizeProvider($provider, $user);
        $invitation = $this->eligibleInvitation($request, $provider);
        $calculation = $this->calculator->calculate($lines, $attributes['delivery_amount'] ?? null);

        return DB::transaction(function () use ($request, $provider, $attributes, $calculation, $user, $invitation) {
            $quotation = Quotation::withoutGlobalScopes()->firstOrNew([
                'service_request_id' => $request->getKey(), 'provider_company_id' => $provider->getKey(),
            ]);
            if ($quotation->exists && $quotation->status !== Quotation::STATUS_DRAFT) {
                throw ValidationException::withMessages(['quotation' => 'Only draft quotations can be edited.']);
            }

            $quotation->fill([
                ...$attributes, ...collect($calculation)->except('lines')->all(),
                'quotation_number' => $quotation->quotation_number ?: $this->numbers->next('quotation'),
                'status' => Quotation::STATUS_DRAFT,
                'currency' => $attributes['currency'] ?? $request->account->currency,
                'created_by' => $quotation->created_by ?: $user->getKey(),
            ])->save();
            $quotation->lines()->delete();

            foreach ($calculation['lines'] as $line) {
                $this->validateLine($request, $provider, $line);
                $quotation->lines()->create($line);
            }

            if ($invitation->status === ProviderInvitation::STATUS_INVITED) {
                $invitation->forceFill(['status' => ProviderInvitation::STATUS_VIEWED, 'viewed_at' => now()])->save();
            }

            return $quotation->fresh('lines');
        });
    }

    public function submit(Quotation $quotation, User $user): Quotation
    {
        $provider = ProviderCompany::findOrFail($quotation->provider_company_id);
        $this->authorizeProvider($provider, $user);

        return DB::transaction(function () use ($quotation, $user) {
            $quotation = Quotation::withoutGlobalScopes()->lockForUpdate()->findOrFail($quotation->getKey());
            if ($quotation->status !== Quotation::STATUS_DRAFT || ! $quotation->lines()->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Only a complete draft quotation can be submitted.']);
            }
            $request = ServiceRequest::withoutGlobalScopes()->findOrFail($quotation->service_request_id);
            $this->eligibleInvitation($request, ProviderCompany::findOrFail($quotation->provider_company_id));

            $quotation->forceFill(['status' => Quotation::STATUS_SUBMITTED, 'submitted_at' => now(), 'submitted_by' => $user->getKey()])->save();
            ProviderInvitation::where('service_request_id', $request->getKey())
                ->where('provider_company_id', $quotation->provider_company_id)
                ->update(['status' => ProviderInvitation::STATUS_QUOTED, 'responded_at' => now()]);
            ServiceRequest::withoutGlobalScopes()->whereKey($request->getKey())->update(['status' => ServiceRequest::STATUS_QUOTES_RECEIVED]);
            $request->creator?->notify(new MarketplaceNotification([
                'title' => 'New quotation received', 'service_request_id' => $request->getKey(),
                'quotation_id' => $quotation->getKey(),
            ]));

            return $quotation->fresh(['lines', 'serviceRequest']);
        });
    }

    private function eligibleInvitation(ServiceRequest $request, ProviderCompany $provider): ProviderInvitation
    {
        $invitation = ProviderInvitation::query()
            ->where('service_request_id', $request->getKey())
            ->where('provider_company_id', $provider->getKey())->first();

        if (! $invitation || $invitation->status === ProviderInvitation::STATUS_DECLINED
            || ($invitation->expires_at && $invitation->expires_at->isPast())) {
            throw ValidationException::withMessages(['invitation' => 'This provider is not invited or eligible to quote for the request.']);
        }

        return $invitation;
    }

    private function authorizeProvider(ProviderCompany $provider, User $user): void
    {
        if (! app(ProviderAccess::class)->hasRole($user, $provider, ProviderAccess::QUOTE_ROLES)) {
            abort(403);
        }
    }

    private function validateLine(ServiceRequest $request, ProviderCompany $provider, array $line): void
    {
        $requestLine = ! empty($line['service_request_line_id'])
            ? $request->lines()->whereKey($line['service_request_line_id'])->first()
            : null;
        if (! empty($line['service_request_line_id']) && ! $requestLine) {
            throw ValidationException::withMessages(['lines' => 'A quotation line references another request.']);
        }
        if (! empty($line['is_alternative']) && blank($line['alternative_reason'] ?? null)) {
            throw ValidationException::withMessages(['lines' => 'Alternative products require a reason.']);
        }
        if (! empty($line['is_alternative']) && $requestLine && ! $requestLine->allow_alternative) {
            throw ValidationException::withMessages(['lines' => 'Alternatives are not allowed for this request line.']);
        }
        if (! empty($line['supplier_product_id'])) {
            $belongsToProvider = SupplierProduct::withoutGlobalScopes()
                ->whereKey($line['supplier_product_id'])->where('provider_company_id', $provider->getKey())->exists();
            if (! $belongsToProvider) {
                throw ValidationException::withMessages(['lines' => 'The selected product belongs to another provider.']);
            }
        }
    }
}
