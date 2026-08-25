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
        $calculation = $this->calculator->calculate($lines, $attributes['delivery_amount'] ?? null);

        return DB::transaction(function () use ($request, $provider, $attributes, $calculation, $user) {
            $provider = ProviderCompany::lockForUpdate()->findOrFail($provider->getKey());
            $this->ensureActiveProvider($provider);
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertRequestAcceptsQuotes($request);
            $invitation = $this->eligibleInvitation($request, $provider, true);
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
            $provider = ProviderCompany::lockForUpdate()->findOrFail($quotation->provider_company_id);
            $this->ensureActiveProvider($provider);
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($quotation->service_request_id);
            $this->assertRequestAcceptsQuotes($request);
            $quotation = Quotation::withoutGlobalScopes()->lockForUpdate()->findOrFail($quotation->getKey());
            if ($quotation->status !== Quotation::STATUS_DRAFT || ! $quotation->lines()->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Only a complete draft quotation can be submitted.']);
            }
            $this->eligibleInvitation($request, ProviderCompany::findOrFail($quotation->provider_company_id), true);

            $quotation->forceFill(['status' => Quotation::STATUS_SUBMITTED, 'submitted_at' => now(), 'submitted_by' => $user->getKey()])->save();
            ProviderInvitation::where('service_request_id', $request->getKey())
                ->where('provider_company_id', $quotation->provider_company_id)
                ->update(['status' => ProviderInvitation::STATUS_QUOTED, 'responded_at' => now()]);
            $request->forceFill(['status' => ServiceRequest::STATUS_QUOTES_RECEIVED])->saveQuietly();
            $request->creator?->notify(new MarketplaceNotification([
                'title' => 'New quotation received', 'service_request_id' => $request->getKey(),
                'quotation_id' => $quotation->getKey(),
            ]));

            return $quotation->fresh(['lines', 'serviceRequest']);
        });
    }

    private function eligibleInvitation(ServiceRequest $request, ProviderCompany $provider, bool $lock = false): ProviderInvitation
    {
        $query = ProviderInvitation::query()
            ->where('service_request_id', $request->getKey())
            ->where('provider_company_id', $provider->getKey());
        $invitation = ($lock ? $query->lockForUpdate() : $query)->first();

        if ($invitation?->expires_at && $invitation->expires_at->isPast()) {
            $invitation->forceFill(['status' => ProviderInvitation::STATUS_EXPIRED])->save();
        }
        if (! $invitation || ! in_array($invitation->status, [
            ProviderInvitation::STATUS_INVITED, ProviderInvitation::STATUS_VIEWED,
        ], true)) {
            throw ValidationException::withMessages(['invitation' => 'This provider is not invited or eligible to quote for the request.']);
        }

        return $invitation;
    }

    private function authorizeProvider(ProviderCompany $provider, User $user): void
    {
        $provider = ProviderCompany::findOrFail($provider->getKey());
        $this->ensureActiveProvider($provider);
        if (! app(ProviderAccess::class)->hasRole($user, $provider, ProviderAccess::QUOTE_ROLES)) {
            abort(403);
        }
    }

    private function ensureActiveProvider(ProviderCompany $provider): void
    {
        if (! $provider->isActive()) {
            throw ValidationException::withMessages(['provider' => 'Only active providers may prepare or submit quotations.']);
        }
    }

    private function assertRequestAcceptsQuotes(ServiceRequest $request): void
    {
        if (! in_array($request->status, [ServiceRequest::STATUS_REQUESTED, ServiceRequest::STATUS_QUOTES_RECEIVED], true)) {
            throw ValidationException::withMessages(['service_request' => 'This service request no longer accepts quotations.']);
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
