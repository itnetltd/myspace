<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AssetItem;
use App\Models\Lease;
use App\Models\ManagementAgreement;
use App\Models\OwnerLedgerEntry;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyOwner;
use App\Models\ProviderCapability;
use App\Models\ProviderCompany;
use App\Models\ProviderCompanyMembership;
use App\Models\ProviderInvoice;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\SupplierProduct;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\OwnerStatementService;
use App\Services\ProviderInvoiceService;
use App\Services\QuotationAcceptanceService;
use App\Services\QuotationService;
use App\Services\ServiceRequestService;
use App\Services\SupplierProductMatchingService;
use App\Services\WorkOrderService;
use App\Support\CurrentAccount;
use App\Support\CurrentProviderCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProviderMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_companies_support_capabilities_staff_and_strict_provider_isolation(): void
    {
        $first = $this->provider('Alpha Services', ['maintenance', 'supplier']);
        $second = $this->provider('Beta Inspections', ['inspection', 'supplier']);

        $this->assertCount(2, $first['company']->capabilities);
        $this->assertSame('owner', $first['company']->users()->first()->pivot->role);

        $this->useProvider($first['user'], $first['company']);
        $firstProduct = SupplierProduct::create([
            'name' => 'Private pump', 'unit_price' => '100000.00', 'stock_status' => 'in_stock',
        ]);
        $this->useProvider($second['user'], $second['company']);
        SupplierProduct::create(['name' => 'Other product', 'unit_price' => '200000.00', 'stock_status' => 'in_stock']);

        $this->useProvider($first['user'], $first['company']);
        $this->assertSame([$firstProduct->id], SupplierProduct::pluck('id')->all());
        $this->assertFalse($first['user']->can('update', SupplierProduct::withoutGlobalScopes()->where('provider_company_id', $second['company']->id)->first()));
        $this->get('/provider/supplier-products')->assertOk();

        [$accountUser, $account] = $this->workspace();
        $this->useAccount($accountUser, $account);
        $this->assertFalse($accountUser->can('update', $firstProduct));
        $this->get('/provider/supplier-products')->assertForbidden();
        $asset = AssetItem::create(['name' => 'Water pump', 'purchase_cost' => '80000.00', 'replacement_value' => '90000.00']);
        app(SupplierProductMatchingService::class)->match($asset, $firstProduct, 'compatible', $accountUser);
        $this->assertSame('compatible', $asset->supplierProducts()->first()->pivot->match_type);
        $this->assertEquals(90000, $asset->fresh()->replacement_value);
    }

    public function test_requests_are_account_scoped_and_only_invited_providers_can_quote(): void
    {
        [$ownerUser, $account, $portfolio] = $this->workspace();
        $invited = $this->provider('Invited Maintenance', ['maintenance']);
        $uninvited = $this->provider('Uninvited Maintenance', ['maintenance']);
        $request = $this->request($account, $portfolio, $ownerUser);
        [$otherAccountUser, $otherAccount] = $this->workspace();
        $this->useAccount($otherAccountUser, $otherAccount);
        $this->assertSame(0, ServiceRequest::count());
        $this->useAccount($ownerUser, $account);
        app(ServiceRequestService::class)->invite($request, [$invited['company']->id], $ownerUser);

        $this->useProvider($uninvited['user'], $uninvited['company']);
        $this->expectException(ValidationException::class);
        app(QuotationService::class)->saveDraft($request, $uninvited['company'], [], $this->quoteLines(), $uninvited['user']);
    }

    public function test_invited_provider_submits_server_calculated_alternative_quote_and_cannot_modify_another_quote(): void
    {
        [$ownerUser, $account, $portfolio] = $this->workspace();
        $first = $this->provider('Supplier One', ['supplier']);
        $second = $this->provider('Supplier Two', ['supplier']);
        $request = $this->request($account, $portfolio, $ownerUser, ServiceRequest::TYPE_PRODUCT_SUPPLY, true);
        app(ServiceRequestService::class)->invite($request, [$first['company']->id, $second['company']->id], $ownerUser);

        $this->useProvider($first['user'], $first['company']);
        $product = SupplierProduct::create(['name' => 'Alternative refrigerator', 'unit_price' => '500000.00', 'stock_status' => 'in_stock']);
        $quote = app(QuotationService::class)->saveDraft($request, $first['company'], [
            'subtotal' => '1.00', 'total_amount' => '1.00', 'delivery_amount' => '10000.00',
        ], [[
            'service_request_line_id' => $request->lines->first()->id,
            'supplier_product_id' => $product->id, 'description' => 'Samsung alternative',
            'quantity' => '1', 'unit_price' => '500000.00', 'tax_amount' => '90000.00',
            'discount_amount' => '10000.00', 'is_alternative' => true,
            'alternative_reason' => 'Equivalent capacity and specifications',
        ]], $first['user']);
        $quote = app(QuotationService::class)->submit($quote, $first['user']);

        $this->assertSame('500000.00', $quote->subtotal);
        $this->assertSame('590000.00', $quote->total_amount);
        $this->assertTrue($quote->lines->first()->is_alternative);

        $this->useProvider($second['user'], $second['company']);
        $this->assertFalse($second['user']->can('update', $quote));
        $this->assertSame(0, Quotation::count());
    }

    public function test_quote_acceptance_is_authorized_transactional_unique_and_creates_work_order(): void
    {
        [$ownerUser, $account, $portfolio] = $this->workspace();
        $provider = $this->provider('Accepted Provider', ['maintenance']);
        $other = $this->provider('Other Provider', ['maintenance']);
        $request = $this->request($account, $portfolio, $ownerUser);
        app(ServiceRequestService::class)->invite($request, [$provider['company']->id, $other['company']->id], $ownerUser);
        $firstQuote = $this->submittedQuote($request, $provider);
        $secondQuote = $this->submittedQuote($request, $other);

        $viewer = User::factory()->create();
        $account->users()->attach($viewer, ['role' => Account::ROLE_VIEWER, 'is_active' => true]);
        $this->useAccount($viewer, $account);
        try {
            app(QuotationAcceptanceService::class)->accept($firstQuote, $viewer);
            $this->fail('Viewer accepted a quotation.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->useAccount($ownerUser, $account);
        $accepted = app(QuotationAcceptanceService::class)->accept($firstQuote, $ownerUser);
        $this->assertSame(Quotation::STATUS_ACCEPTED, $accepted->status);
        $this->assertSame(Quotation::STATUS_REJECTED, $secondQuote->fresh()->status);
        $this->assertSame(1, WorkOrder::withoutGlobalScopes()->where('service_request_id', $request->id)->count());
        $this->get('/admin/quotations')->assertOk();

        $this->useProvider($other['user'], $other['company']);
        try {
            app(ProviderInvoiceService::class)->saveDraft($secondQuote, [], null, $other['user']);
            $this->fail('Unselected provider created an invoice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quotation', $exception->errors());
        }

        $this->useAccount($ownerUser, $account);
        $this->expectException(ValidationException::class);
        app(QuotationAcceptanceService::class)->accept($secondQuote, $ownerUser);
    }

    public function test_company_approval_limit_blocks_large_quote_until_manual_approval_is_recorded(): void
    {
        [$ownerUser, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        ManagementAgreement::create([
            'property_owner_id' => $portfolio['owner']->id, 'property_id' => $portfolio['property']->id,
            'reference_number' => 'AGR-MARKET', 'start_date' => now()->subYear()->toDateString(),
            'management_fee_type' => ManagementAgreement::FEE_FIXED, 'management_fee_fixed_amount' => '0.00',
            'maintenance_approval_limit' => '400000.00', 'status' => ManagementAgreement::STATUS_ACTIVE,
        ]);
        $provider = $this->provider('Approval Provider', ['maintenance']);
        $below = $this->request($account, $portfolio, $ownerUser);
        app(ServiceRequestService::class)->invite($below, [$provider['company']->id], $ownerUser);
        $belowQuote = $this->submittedQuote($below, $provider, '300000.00');
        $this->useAccount($ownerUser, $account);
        $this->assertSame(Quotation::STATUS_ACCEPTED, app(QuotationAcceptanceService::class)->accept($belowQuote, $ownerUser)->status);
        $this->assertFalse($below->fresh()->owner_approval_required);

        $request = $this->request($account, $portfolio, $ownerUser);
        app(ServiceRequestService::class)->invite($request, [$provider['company']->id], $ownerUser);
        $quote = $this->submittedQuote($request, $provider, '500000.00');
        $this->useAccount($ownerUser, $account);

        try {
            app(QuotationAcceptanceService::class)->accept($quote, $ownerUser);
            $this->fail('Large quote accepted without owner approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('owner_approval', $exception->errors());
        }
        $this->assertTrue($request->fresh()->owner_approval_required);

        app(ServiceRequestService::class)->recordOwnerApproval($request->fresh(), $quote, $ownerUser, 'Email approval REF-123');
        $this->assertSame(Quotation::STATUS_ACCEPTED, app(QuotationAcceptanceService::class)->accept($quote, $ownerUser)->status);
    }

    public function test_selected_provider_invoice_variation_and_idempotent_expense_ledger_statement_integration(): void
    {
        [$ownerUser, $account, $portfolio] = $this->workspace();
        $provider = $this->provider('Invoice Provider', ['maintenance']);
        $request = $this->request($account, $portfolio, $ownerUser);
        app(ServiceRequestService::class)->invite($request, [$provider['company']->id], $ownerUser);
        $quote = $this->submittedQuote($request, $provider, '500000.00');
        $this->useAccount($ownerUser, $account);
        app(QuotationAcceptanceService::class)->accept($quote, $ownerUser);

        $this->useProvider($provider['user'], $provider['company']);
        $workOrder = WorkOrder::first();
        $wrongProvider = $this->provider('Wrong Fulfilment Provider', ['maintenance']);
        $this->useProvider($wrongProvider['user'], $wrongProvider['company']);
        try {
            app(WorkOrderService::class)->transition($workOrder, WorkOrder::STATUS_IN_PROGRESS, [], $wrongProvider['user']);
            $this->fail('Wrong provider changed the work order.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->useProvider($provider['user'], $provider['company']);
        app(WorkOrderService::class)->transition($workOrder, WorkOrder::STATUS_IN_PROGRESS, ['started_at' => now()], $provider['user']);
        app(WorkOrderService::class)->transition($workOrder->fresh(), WorkOrder::STATUS_COMPLETED, [
            'completed_at' => now(), 'completion_notes' => 'Completed',
            'completion_evidence' => ['after_photos' => ['private/work-orders/evidence.jpg']],
        ], $provider['user']);

        $invoices = app(ProviderInvoiceService::class);
        $invoice = $invoices->saveDraft($quote, ['invoice_date' => now()->toDateString()], null, $provider['user']);
        $this->assertSame('500000.00', $invoice->total_amount);
        $invoice = $invoices->submit($invoice, $provider['user']);

        $this->useAccount($ownerUser, $account);
        $invoice = $invoices->approve($invoice, $ownerUser);
        $posted = $invoices->postAsExpense($invoice, $ownerUser);
        $retried = $invoices->postAsExpense($posted, $ownerUser);

        $this->assertSame($posted->property_expense_id, $retried->property_expense_id);
        $this->assertSame(1, PropertyExpense::where('provider_invoice_id', $posted->id)->count());
        $this->assertSame('500000.00', PropertyExpense::find($posted->property_expense_id)->amount);
        $this->assertSame(1, OwnerLedgerEntry::where('source_type', 'property_expense')->where('source_id', $posted->property_expense_id)->count());

        $this->useProvider($wrongProvider['user'], $wrongProvider['company']);
        $this->assertSame(0, ProviderInvoice::count());
        $ledgerEntry = OwnerLedgerEntry::withoutGlobalScopes()->where('source_id', $posted->property_expense_id)->firstOrFail();
        $this->assertFalse($wrongProvider['user']->can('view', $ledgerEntry));

        $this->useAccount($ownerUser, $account);
        $statement = app(OwnerStatementService::class)->generateDraft($portfolio['owner'], now()->format('Y-m'), $ownerUser);
        $this->assertSame('500000.00', $statement->expenses);
        $this->assertSame('-500000.00', $statement->closing_balance);
    }

    public function test_invoice_increase_requires_a_reason_and_explicit_account_variation_approval(): void
    {
        [$ownerUser, $account, $portfolio] = $this->workspace();
        $provider = $this->provider('Variation Provider', ['maintenance']);
        $request = $this->request($account, $portfolio, $ownerUser);
        app(ServiceRequestService::class)->invite($request, [$provider['company']->id], $ownerUser);
        $quote = $this->submittedQuote($request, $provider, '500000.00');
        $this->useAccount($ownerUser, $account);
        app(QuotationAcceptanceService::class)->accept($quote, $ownerUser);
        $this->useProvider($provider['user'], $provider['company']);
        $work = WorkOrder::first();
        app(WorkOrderService::class)->transition($work, WorkOrder::STATUS_IN_PROGRESS, [], $provider['user']);
        app(WorkOrderService::class)->transition($work->fresh(), WorkOrder::STATUS_COMPLETED, ['completed_at' => now()], $provider['user']);

        $service = app(ProviderInvoiceService::class);
        $invoice = $service->saveDraft($quote, ['variation_reason' => 'Additional approved materials'], [[
            'description' => 'Changed scope', 'quantity' => 1, 'unit_price' => '600000.00',
            'tax_amount' => 0, 'discount_amount' => 0,
        ]], $provider['user']);
        $invoice = $service->submit($invoice, $provider['user']);
        $this->useAccount($ownerUser, $account);

        try {
            $service->approve($invoice, $ownerUser);
            $this->fail('Invoice variation approved implicitly.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variation', $exception->errors());
        }
        $approved = $service->approve($invoice, $ownerUser, true);
        $this->assertNotNull($approved->variation_approved_at);
        $this->assertSame('600000.00', $approved->total_amount);
    }

    private function workspace(string $type = Account::TYPE_INDIVIDUAL_LANDLORD): array
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Marketplace '.str()->random(5), 'slug' => 'market-'.str()->lower(str()->random(8)),
            'type' => $type, 'status' => Account::STATUS_ACTIVE, 'currency' => 'RWF', 'timezone' => 'Africa/Kigali',
        ]);
        $account->users()->attach($user, ['role' => Account::ROLE_OWNER, 'is_active' => true]);
        $this->useAccount($user, $account);
        $owner = PropertyOwner::create(['name' => 'Marketplace Owner']);
        if ($type === Account::TYPE_INDIVIDUAL_LANDLORD) {
            $account->forceFill(['self_property_owner_id' => $owner->id])->saveQuietly();
        }
        $property = Property::create(['property_owner_id' => $owner->id, 'name' => 'Marketplace Property', 'type' => 'apartment']);
        $unit = Unit::create(['property_id' => $property->id, 'unit_code' => 'MKT-1', 'monthly_rent' => '500000.00', 'status' => Unit::STATUS_OCCUPIED]);
        $tenant = Tenant::create(['full_name' => 'Marketplace Tenant', 'id_number' => 'MKT-ID']);
        $lease = Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'start_date' => now()->subYear()->toDateString(),
            'monthly_rent' => '500000.00', 'deposit' => '500000.00', 'status' => Lease::STATUS_DRAFT,
        ]);

        return [$user, $account, compact('owner', 'property', 'unit', 'tenant', 'lease')];
    }

    private function provider(string $name, array $capabilities): array
    {
        auth()->logout();
        app(CurrentAccount::class)->forget();
        app(CurrentProviderCompany::class)->forget();
        $company = ProviderCompany::create([
            'name' => $name, 'slug' => str()->slug($name).'-'.str()->lower(str()->random(5)),
            'status' => ProviderCompany::STATUS_ACTIVE, 'email' => str()->random(5).'@provider.test',
        ]);
        foreach ($capabilities as $capability) {
            ProviderCapability::withoutGlobalScopes()->create(['provider_company_id' => $company->id, 'capability' => $capability]);
        }
        $user = User::factory()->create();
        ProviderCompanyMembership::withoutGlobalScopes()->create([
            'provider_company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true,
        ]);

        return compact('company', 'user');
    }

    private function request(Account $account, array $portfolio, User $user, string $type = ServiceRequest::TYPE_MAINTENANCE, bool $alternative = false): ServiceRequest
    {
        $this->useAccount($user, $account);

        return app(ServiceRequestService::class)->create($account, [
            'property_owner_id' => $portfolio['owner']->id, 'property_id' => $portfolio['property']->id,
            'unit_id' => $portfolio['unit']->id, 'lease_id' => $portfolio['lease']->id,
            'request_type' => $type, 'title' => 'Repair or supply request',
            'description' => 'Private job details', 'priority' => 'normal',
        ], [[
            'description' => 'Requested work or product', 'quantity' => '1',
            'allow_alternative' => $alternative,
        ]], $user);
    }

    private function submittedQuote(ServiceRequest $request, array $provider, string $amount = '500000.00'): Quotation
    {
        $this->useProvider($provider['user'], $provider['company']);
        $quote = app(QuotationService::class)->saveDraft($request, $provider['company'], [], $this->quoteLines($amount), $provider['user']);

        return app(QuotationService::class)->submit($quote, $provider['user']);
    }

    private function quoteLines(string $amount = '500000.00'): array
    {
        return [[
            'description' => 'Labour and materials', 'quantity' => '1', 'unit_price' => $amount,
            'tax_amount' => '0', 'discount_amount' => '0', 'is_alternative' => false,
        ]];
    }

    private function useAccount(User $user, Account $account): void
    {
        $this->actingAs($user);
        app(CurrentProviderCompany::class)->forget();
        app(CurrentAccount::class)->forget();
        app(CurrentAccount::class)->switch($user, $account->id);
    }

    private function useProvider(User $user, ProviderCompany $company): void
    {
        $this->actingAs($user);
        app(CurrentAccount::class)->forget();
        app(CurrentProviderCompany::class)->forget();
        app(CurrentProviderCompany::class)->switch($user, $company->id);
    }
}
