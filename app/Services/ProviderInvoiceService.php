<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PropertyExpense;
use App\Models\ProviderCompany;
use App\Models\ProviderInvoice;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\AccountAccess;
use App\Support\Money;
use App\Support\ProviderAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderInvoiceService
{
    public function __construct(
        private readonly MarketplaceNumberGenerator $numbers,
        private readonly QuotationCalculator $calculator,
        private readonly PropertyExpenseService $expenses,
        private readonly WorkOrderActivityService $activities,
    ) {}

    public function saveDraft(Quotation $quotation, array $attributes, ?array $lines, User $user): ProviderInvoice
    {
        $quotation = Quotation::withoutGlobalScopes()->with([
            'lines',
            'serviceRequest' => fn ($query) => $query->withoutGlobalScopes()->with([
                'workOrder' => fn ($workOrder) => $workOrder->withoutGlobalScopes()->with('acceptedCompletionSubmission'),
            ]),
        ])->findOrFail($quotation->getKey());
        $provider = ProviderCompany::findOrFail($quotation->provider_company_id);
        $this->ensureActiveProvider($provider);
        if (! app(ProviderAccess::class)->hasRole($user, $provider, ProviderAccess::INVOICE_ROLES)) {
            abort(403);
        }
        if ($quotation->status !== Quotation::STATUS_ACCEPTED
            || (int) $quotation->serviceRequest->accepted_quotation_id !== (int) $quotation->getKey()) {
            throw ValidationException::withMessages(['quotation' => 'Only the selected provider can invoice an accepted quotation.']);
        }
        $workOrder = $quotation->serviceRequest->workOrder;
        if ($workOrder?->status !== \App\Models\WorkOrder::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['work_order' => 'The work order must be completed before invoicing.']);
        }
        if ($workOrder->completion_review_required
            && (! $workOrder->accepted_completion_submission_id
                || $workOrder->acceptedCompletionSubmission?->status !== \App\Models\WorkOrderCompletionSubmission::STATUS_ACCEPTED
                || (int) $workOrder->acceptedCompletionSubmission?->work_order_id !== (int) $workOrder->getKey())) {
            throw ValidationException::withMessages([
                'work_order' => 'Account acceptance of the completion submission is required before invoicing.',
            ]);
        }

        $copyingQuotation = $lines === null;
        $lines ??= $quotation->lines->map(fn ($line) => [
            'quotation_line_id' => $line->getKey(), 'description' => $line->description,
            'quantity' => $line->quantity, 'unit_price' => $line->unit_price,
            'tax_amount' => $line->tax_amount, 'discount_amount' => $line->discount_amount,
        ])->all();
        $deliveryAmount = $copyingQuotation
            ? $quotation->delivery_amount
            : ($attributes['delivery_amount'] ?? 0);
        $calculation = $this->calculator->calculate($lines, $deliveryAmount);

        return DB::transaction(function () use ($quotation, $attributes, $calculation) {
            $provider = ProviderCompany::lockForUpdate()->findOrFail($quotation->provider_company_id);
            $this->ensureActiveProvider($provider);
            $request = $quotation->serviceRequest;
            $invoice = ProviderInvoice::withoutGlobalScopes()->firstOrNew(['service_request_id' => $request->getKey()]);
            if ($invoice->exists && $invoice->status !== ProviderInvoice::STATUS_DRAFT) {
                throw ValidationException::withMessages(['invoice' => 'Only a draft provider invoice can be edited.']);
            }

            $invoice->fill([
                ...$attributes, ...collect($calculation)->except('lines')->all(),
                'work_order_id' => $request->workOrder?->getKey(), 'quotation_id' => $quotation->getKey(),
                'provider_company_id' => $quotation->provider_company_id, 'account_id' => $request->account_id,
                'property_owner_id' => $request->property_owner_id, 'property_id' => $request->property_id,
                'unit_id' => $request->unit_id,
                'invoice_number' => $invoice->invoice_number ?: $this->numbers->next('provider_invoice'),
                'invoice_date' => $attributes['invoice_date'] ?? now()->toDateString(),
                'currency' => $quotation->currency, 'status' => ProviderInvoice::STATUS_DRAFT,
            ])->save();
            $invoice->lines()->delete();
            foreach ($calculation['lines'] as $line) {
                if (! empty($line['quotation_line_id']) && ! $quotation->lines->contains('id', $line['quotation_line_id'])) {
                    throw ValidationException::withMessages(['lines' => 'An invoice line references another quotation.']);
                }
                $invoice->lines()->create($line);
            }

            return $invoice->fresh('lines');
        });
    }

    public function submit(ProviderInvoice $invoice, User $user): ProviderInvoice
    {
        $provider = ProviderCompany::findOrFail($invoice->provider_company_id);
        $this->ensureActiveProvider($provider);
        if (! app(ProviderAccess::class)->hasRole($user, $provider, ProviderAccess::INVOICE_ROLES)) {
            abort(403);
        }

        return DB::transaction(function () use ($invoice, $user) {
            $provider = ProviderCompany::lockForUpdate()->findOrFail($invoice->provider_company_id);
            $this->ensureActiveProvider($provider);
            $invoice = ProviderInvoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->getKey());
            if ($invoice->status !== ProviderInvoice::STATUS_DRAFT || ! $invoice->lines()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Only a complete draft invoice can be submitted.']);
            }
            $quoteTotal = Money::toMinor(Quotation::withoutGlobalScopes()->findOrFail($invoice->quotation_id)->total_amount);
            if (Money::toMinor($invoice->total_amount) > $quoteTotal && blank($invoice->variation_reason)) {
                throw ValidationException::withMessages(['variation_reason' => 'A reason is required when the invoice exceeds the accepted quotation.']);
            }

            $invoice->forceFill([
                'status' => ProviderInvoice::STATUS_SUBMITTED, 'submitted_at' => now(), 'submitted_by' => $user->getKey(),
            ])->save();
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->service_request_id);
            $request->forceFill(['status' => ServiceRequest::STATUS_INVOICED])->saveQuietly();
            $request->creator?->notify(new MarketplaceNotification([
                'title' => 'Provider invoice submitted', 'provider_invoice_id' => $invoice->getKey(),
            ]));
            $workOrder = \App\Models\WorkOrder::withoutGlobalScopes()->findOrFail($invoice->work_order_id);
            $this->activities->record($workOrder, 'invoice_submitted', 'Provider invoice submitted.', $user, [
                'provider_invoice_id' => $invoice->getKey(),
            ]);

            return $invoice->refresh();
        });
    }

    public function approve(ProviderInvoice $invoice, User $user, bool $approveVariation = false): ProviderInvoice
    {
        $account = Account::findOrFail($invoice->account_id);
        if (! app(AccountAccess::class)->can($user, $account, AccountAccess::APPROVE_PROVIDER_INVOICES)) {
            abort(403);
        }
        if ($invoice->status !== ProviderInvoice::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['invoice' => 'Only submitted provider invoices can be approved.']);
        }

        $isVariation = Money::toMinor($invoice->total_amount)
            > Money::toMinor(Quotation::withoutGlobalScopes()->findOrFail($invoice->quotation_id)->total_amount);
        if ($isVariation && (! $approveVariation || blank($invoice->variation_reason))) {
            throw ValidationException::withMessages(['variation' => 'The invoice variation requires explicit Account approval.']);
        }

        $invoice->forceFill([
            'status' => ProviderInvoice::STATUS_APPROVED, 'approved_at' => now(), 'approved_by' => $user->getKey(),
            'variation_approved_at' => $isVariation ? now() : null,
            'variation_approved_by' => $isVariation ? $user->getKey() : null,
        ])->save();
        ProviderCompany::find($invoice->provider_company_id)?->users()->wherePivot('is_active', true)->get()
            ->each->notify(new MarketplaceNotification([
                'title' => 'Provider invoice approved', 'provider_invoice_id' => $invoice->getKey(),
            ]));

        return $invoice->refresh();
    }

    public function postAsExpense(ProviderInvoice $invoice, User $user): ProviderInvoice
    {
        $account = Account::findOrFail($invoice->account_id);
        if (! app(AccountAccess::class)->can($user, $account, AccountAccess::APPROVE_PROVIDER_INVOICES)
            || ! app(AccountAccess::class)->can($user, $account, AccountAccess::POST_EXPENSES)) {
            abort(403);
        }

        return DB::transaction(function () use ($invoice, $user) {
            $invoice = ProviderInvoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->getKey());
            if ($invoice->property_expense_id) {
                return $invoice;
            }
            if ($invoice->status !== ProviderInvoice::STATUS_APPROVED) {
                throw ValidationException::withMessages(['invoice' => 'The provider invoice must be approved before posting.']);
            }

            $request = ServiceRequest::withoutGlobalScopes()->findOrFail($invoice->service_request_id);
            if (! $invoice->property_owner_id || ! $invoice->property_id) {
                throw ValidationException::withMessages(['property' => 'An owner and property are required before posting an expense.']);
            }
            $category = match ($request->request_type) {
                ServiceRequest::TYPE_MAINTENANCE => 'maintenance',
                ServiceRequest::TYPE_PRODUCT_SUPPLY => 'supplier_purchase',
                ServiceRequest::TYPE_INSPECTION => 'inspection',
            };
            $provider = ProviderCompany::findOrFail($invoice->provider_company_id);
            $expense = PropertyExpense::withoutGlobalScopes()->create([
                'account_id' => $invoice->account_id, 'property_owner_id' => $invoice->property_owner_id,
                'property_id' => $invoice->property_id, 'unit_id' => $invoice->unit_id,
                'lease_id' => $request->lease_id, 'maintenance_ticket_id' => $request->maintenance_ticket_id,
                'provider_invoice_id' => $invoice->getKey(), 'category' => $category,
                'vendor_name' => $provider->name, 'description' => 'Provider invoice '.$invoice->invoice_number,
                'amount' => $invoice->total_amount, 'currency' => $invoice->currency,
                'occurred_on' => $invoice->invoice_date, 'reference' => $invoice->invoice_number,
                'document_path' => $invoice->document_path, 'notes' => $invoice->notes,
                'source_type' => 'provider_invoice', 'source_id' => $invoice->getKey(), 'created_by' => $user->getKey(),
            ]);

            if ($expense->owner_approval_required
                && (int) $request->owner_approved_quotation_id === (int) $invoice->quotation_id
                && Money::toMinor($request->owner_approved_amount) === Money::toMinor($invoice->total_amount)
                && $request->owner_approved_currency === $invoice->currency) {
                $this->expenses->recordOwnerApproval($expense, $user, $request->owner_approval_reference ?: 'Recorded on service request');
            }
            $this->expenses->approve($expense, $user);
            $this->expenses->post($expense, $user);

            $invoice->forceFill(['status' => ProviderInvoice::STATUS_POSTED, 'property_expense_id' => $expense->getKey()])->save();
            ServiceRequest::withoutGlobalScopes()->whereKey($request->getKey())->update(['status' => ServiceRequest::STATUS_CLOSED]);

            return $invoice->fresh('propertyExpense');
        });
    }

    private function ensureActiveProvider(ProviderCompany $provider): void
    {
        if (! $provider->isActive()) {
            throw ValidationException::withMessages(['provider' => 'Only active providers may manage provider invoices.']);
        }
    }
}
