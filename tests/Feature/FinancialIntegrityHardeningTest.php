<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\ManagementAgreement;
use App\Models\OwnerDisbursement;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerStatement;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyOwner;
use App\Models\RentInvoice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\OwnerLedgerAdjustmentService;
use App\Services\OwnerLedgerService;
use App\Services\OwnerStatementService;
use App\Services\PaymentAllocationService;
use App\Services\PropertyExpenseService;
use App\Services\RentPaymentService;
use App\Support\CurrentAccount;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class FinancialIntegrityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fully_paid_before_due_date_never_receives_a_late_fee(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->lateFeePolicy(3);
        $invoice = $this->invoice($portfolio, '2026-08-10');
        Carbon::setTestNow('2026-08-20');

        app(RentPaymentService::class)->create([
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-09',
            'amount' => '500000.00',
        ]);
        $invoice->refreshPaymentTotals();

        $this->assertSame('0.00', $invoice->fresh()->late_fee);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_fully_paid_during_grace_period_never_receives_a_late_fee(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->lateFeePolicy(3);
        $invoice = $this->invoice($portfolio, '2026-08-10');
        Carbon::setTestNow('2027-01-01');

        app(RentPaymentService::class)->create([
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-13',
            'amount' => '500000.00',
        ]);
        $invoice->refreshPaymentTotals();

        $this->assertSame('0.00', $invoice->fresh()->late_fee);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_partial_principal_at_grace_cutoff_receives_late_fee(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->lateFeePolicy(3);
        $invoice = $this->invoice($portfolio, '2026-08-10');
        Carbon::setTestNow('2026-08-14');

        app(RentPaymentService::class)->create([
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-13',
            'amount' => '300000.00',
        ]);

        $this->assertSame('50000.00', $invoice->fresh()->late_fee);
        $this->assertSame('overdue', $invoice->fresh()->status);
    }

    public function test_unpaid_principal_at_grace_cutoff_receives_late_fee(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->lateFeePolicy(3);
        $invoice = $this->invoice($portfolio, '2026-08-10');
        Carbon::setTestNow('2026-08-14');

        $invoice->refreshPaymentTotals();

        $this->assertSame('50000.00', $invoice->fresh()->late_fee);
        $this->assertSame('overdue', $invoice->fresh()->status);
    }

    public function test_overdue_status_begins_only_after_grace_period(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->lateFeePolicy(3);
        $invoice = $this->invoice($portfolio, '2026-08-10');

        Carbon::setTestNow('2026-08-13');
        $invoice->refreshPaymentTotals();
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->late_fee);

        Carbon::setTestNow('2026-08-14');
        $invoice->refreshPaymentTotals();
        $this->assertSame('overdue', $invoice->fresh()->status);
    }

    public function test_advancing_current_date_does_not_penalize_an_on_time_payment(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->lateFeePolicy(5);
        $invoice = $this->invoice($portfolio, '2026-08-10');
        Carbon::setTestNow('2026-08-12');
        app(RentPaymentService::class)->create([
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-12',
            'amount' => '500000.00',
        ]);

        Carbon::setTestNow('2030-01-01');
        $invoice->refreshPaymentTotals();

        $this->assertSame('0.00', $invoice->fresh()->late_fee);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_overpayment_is_reported_but_not_credited_to_owner(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->lateFeePolicy(0);
        $invoice = $this->invoice($portfolio, '2026-08-01');
        Carbon::setTestNow('2026-08-10');
        $invoice->refreshPaymentTotals();

        app(RentPaymentService::class)->create([
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-10',
            'amount' => '600000.00',
        ]);

        $credited = OwnerLedgerEntry::query()->get()->sum(
            fn (OwnerLedgerEntry $entry) => (int) str_replace('.', '', $entry->amount)
        );
        $allocation = app(PaymentAllocationService::class)->allocate($invoice->fresh());

        $this->assertSame(55000000, $credited);
        $this->assertSame(5000000, $allocation[array_key_first($allocation)]['unallocated_minor']);
        $this->assertFalse(OwnerLedgerEntry::query()->where('source_key', 'unallocated')->exists());
    }

    public function test_statement_model_rejects_partial_month_and_database_prevents_duplicate_month(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);

        try {
            OwnerStatement::create([
                'property_owner_id' => $portfolio['owner']->id,
                'statement_number' => 'OS-PARTIAL',
                'statement_month' => '2026-08',
                'period_start' => '2026-08-10',
                'period_end' => '2026-08-31',
                'currency' => 'RWF',
                'generated_at' => now(),
            ]);
            $this->fail('A partial-month statement should be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('owner_statements', 0);
        }

        $statement = app(OwnerStatementService::class)->generateDraft($portfolio['owner'], '2026-08', $user);
        $regenerated = app(OwnerStatementService::class)->generateDraft($portfolio['owner'], '2026-08-20', $user);
        $this->assertSame($statement->id, $regenerated->id);

        $this->expectException(QueryException::class);
        DB::table('owner_statements')->insert([
            'account_id' => $account->id,
            'property_owner_id' => $portfolio['owner']->id,
            'statement_number' => 'OS-DUPLICATE',
            'statement_month' => '2026-08',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'draft',
            'currency' => 'RWF',
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ledger_entry_cannot_be_reassigned_from_another_statement(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $entry = $this->adjust($portfolio['owner'], $user, '1000.00', '2026-08-15');
        $other = app(OwnerStatementService::class)->generateDraft($portfolio['owner'], '2026-09', $user);
        DB::table('owner_ledger_entries')->where('id', $entry->id)->update(['owner_statement_id' => $other->id]);

        $this->expectException(ValidationException::class);
        app(OwnerStatementService::class)->generateDraft($portfolio['owner'], '2026-08', $user);
    }

    public function test_finalized_august_closes_period_and_preserves_september_continuity(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->adjust($portfolio['owner'], $user, '1000000.00', '2026-08-15');
        $statements = app(OwnerStatementService::class);
        $august = $statements->generateDraft($portfolio['owner'], '2026-08', $user);
        $this->assertSame('0.00', $august->opening_balance);
        $this->assertSame('1000000.00', $august->closing_balance);
        $statements->finalize($august, $user);

        $september = $statements->generateDraft($portfolio['owner'], '2026-09', $user);
        $this->assertSame('1000000.00', $september->opening_balance);

        try {
            $this->adjust($portfolio['owner'], $user, '100.00', '2026-08-20');
            $this->fail('Backdated August activity should be rejected.');
        } catch (ValidationException) {
            $this->assertSame('1000000.00', $statements
                ->generateDraft($portfolio['owner'], '2026-09', $user)->opening_balance);
        }

        $this->adjust($portfolio['owner'], $user, '250.00', '2026-09-10');
        $this->assertSame('1000250.00', $statements
            ->generateDraft($portfolio['owner'], '2026-09', $user)->closing_balance);
    }

    public function test_finalized_period_rejects_backdated_expense_payout_rent_and_ledger_move(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $statements = app(OwnerStatementService::class);
        $statements->finalize($statements->generateDraft($portfolio['owner'], '2026-08', $user), $user);

        $expense = $this->expense($portfolio, '2026-08-15');
        app(PropertyExpenseService::class)->approve($expense, $user);
        try {
            app(PropertyExpenseService::class)->post($expense->fresh(), $user);
            $this->fail('Backdated expense should be rejected.');
        } catch (ValidationException) {
            $this->assertFalse(OwnerLedgerEntry::query()->where('source_type', 'property_expense')->exists());
        }

        try {
            OwnerDisbursement::create([
                'property_owner_id' => $portfolio['owner']->id,
                'amount' => '100.00',
                'paid_on' => '2026-08-20',
            ]);
            $this->fail('Backdated payout should be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('owner_disbursements', 0);
        }

        $invoice = $this->invoice($portfolio, '2026-08-10');
        try {
            app(RentPaymentService::class)->create([
                'rent_invoice_id' => $invoice->id,
                'paid_on' => '2026-08-10',
                'amount' => '100.00',
            ]);
            $this->fail('Backdated rent payment should be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rent_payments', 0);
        }

        $september = $this->adjust($portfolio['owner'], $user, '100.00', '2026-09-10');
        $this->expectException(ValidationException::class);
        $september->update(['occurred_on' => '2026-08-10']);
    }

    public function test_payment_and_ledger_sync_roll_back_atomically_when_ledger_fails(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $invoice = $this->invoice($portfolio, '2026-08-31');
        $ledger = Mockery::mock(OwnerLedgerService::class);
        $ledger->shouldReceive('post')->once()->andThrow(
            ValidationException::withMessages(['ledger' => 'Forced ledger failure.'])
        );
        $this->app->instance(OwnerLedgerService::class, $ledger);

        try {
            app(RentPaymentService::class)->create([
                'rent_invoice_id' => $invoice->id,
                'paid_on' => '2026-08-10',
                'amount' => '100000.00',
            ]);
            $this->fail('The forced ledger failure should escape the transaction.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rent_payments', 0);
            $this->assertSame('0.00', $invoice->fresh()->amount_paid);
            $this->assertDatabaseCount('owner_ledger_entries', 0);
        }
    }

    public function test_expense_rejects_property_with_foreign_same_account_unit(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $other = $this->secondPortfolio($account, $portfolio['owner']);

        $this->expectException(ValidationException::class);
        PropertyExpense::create([
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'unit_id' => $other['unit']->id,
            'category' => 'repair', 'description' => 'Forged unit',
            'amount' => '100.00', 'occurred_on' => '2026-09-01',
        ]);
    }

    public function test_expense_rejects_unit_with_foreign_same_account_lease(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $other = $this->secondPortfolio($account, $portfolio['owner']);

        $this->expectException(ValidationException::class);
        PropertyExpense::create([
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'unit_id' => $portfolio['unit']->id,
            'lease_id' => $other['lease']->id,
            'category' => 'repair', 'description' => 'Forged lease',
            'amount' => '100.00', 'occurred_on' => '2026-09-01',
        ]);
    }

    public function test_expense_rejects_foreign_same_account_maintenance_ticket(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $other = $this->secondPortfolio($account, $portfolio['owner']);
        $ticket = MaintenanceTicket::create([
            'unit_id' => $other['unit']->id,
            'lease_id' => $other['lease']->id,
            'title' => 'Other unit ticket',
        ]);

        $this->expectException(ValidationException::class);
        PropertyExpense::create([
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'unit_id' => $portfolio['unit']->id,
            'lease_id' => $portfolio['lease']->id,
            'maintenance_ticket_id' => $ticket->id,
            'category' => 'maintenance', 'description' => 'Forged ticket',
            'amount' => '100.00', 'occurred_on' => '2026-09-01',
        ]);
    }

    public function test_only_management_agreement_managers_may_confirm_legacy_fee_components(): void
    {
        [$ownerUser, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->useAccount($ownerUser, $account);
        $agreement = \App\Models\ManagementAgreement::create([
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'reference_number' => 'AGR-REVIEW',
            'start_date' => '2026-01-01',
            'management_fee_type' => 'percentage_plus_fixed',
            'management_fee_percentage' => '10.0000',
            'management_fee_fixed_amount' => '50000.00',
            'fee_migration_review_required' => true,
            'status' => 'active',
        ]);

        foreach ([Account::ROLE_VIEWER, Account::ROLE_ACCOUNTANT, Account::ROLE_MAINTENANCE] as $role) {
            $user = User::factory()->create();
            $account->users()->attach($user, ['role' => $role, 'is_active' => true]);
            $this->useAccount($user, $account);
            $this->assertFalse(Gate::allows('update', $agreement), $role);
        }

        $this->useAccount($ownerUser, $account);
        $this->assertTrue(Gate::allows('update', $agreement));
    }

    public function test_new_income_makes_a_draft_stale_and_regeneration_restores_statement_continuity(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $service = app(OwnerStatementService::class);

        $this->adjust($portfolio['owner'], $user, '1000000.00', '2026-08-10');
        $draft = $service->generateDraft($portfolio['owner'], '2026-08', $user);
        $this->assertSame('1000000.00', $draft->closing_balance);

        $this->adjust($portfolio['owner'], $user, '500000.00', '2026-08-20');
        $this->assertDraftIsStale(fn () => $service->finalize($draft, $user));
        $this->assertSame(OwnerStatement::STATUS_DRAFT, $draft->fresh()->status);

        $regenerated = $service->generateDraft($portfolio['owner'], '2026-08', $user);
        $this->assertSame('1500000.00', $regenerated->closing_balance);
        $finalized = $service->finalize($regenerated, $user);
        $september = $service->generateDraft($portfolio['owner'], '2026-09', $user);

        $this->assertSame('1500000.00', $finalized->closing_balance);
        $this->assertSame($finalized->closing_balance, $september->opening_balance);
    }

    public function test_new_expense_makes_an_existing_draft_stale(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $service = app(OwnerStatementService::class);
        $draft = $service->generateDraft($portfolio['owner'], '2026-08', $user);

        $expense = $this->expense($portfolio, '2026-08-15');
        $expenses = app(PropertyExpenseService::class);
        $expenses->approve($expense, $user);
        $expenses->post($expense, $user);

        $this->assertDraftIsStale(fn () => $service->finalize($draft, $user));
    }

    public function test_new_rent_makes_a_company_draft_and_its_management_fee_stale(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->useAccount($user, $account);
        ManagementAgreement::create([
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'reference_number' => 'AGR-FRESHNESS',
            'start_date' => '2026-01-01',
            'management_fee_type' => ManagementAgreement::FEE_PERCENTAGE,
            'management_fee_percentage' => '10.0000',
            'management_fee_fixed_amount' => '0.00',
            'status' => ManagementAgreement::STATUS_ACTIVE,
        ]);
        $invoice = $this->invoice($portfolio, '2026-08-31');
        $payments = app(RentPaymentService::class);
        $payments->create([
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-10',
            'amount' => '100000.00',
        ]);
        $service = app(OwnerStatementService::class);
        $draft = $service->generateDraft($portfolio['owner'], '2026-08', $user);
        $this->assertSame('10000.00', $draft->management_fees);

        $payments->create([
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-20',
            'amount' => '100000.00',
        ]);

        $this->assertDraftIsStale(fn () => $service->finalize($draft, $user));
        $regenerated = $service->generateDraft($portfolio['owner'], '2026-08', $user);

        $this->assertSame('20000.00', $regenerated->management_fees);
    }

    public function test_unchanged_draft_finalizes_and_locks_its_entries(): void
    {
        [$user, $account, $portfolio] = $this->workspace();
        $this->useAccount($user, $account);
        $this->adjust($portfolio['owner'], $user, '1000.00', '2026-08-10');
        $service = app(OwnerStatementService::class);
        $draft = $service->generateDraft($portfolio['owner'], '2026-08', $user);

        $finalized = $service->finalize($draft, $user);

        $this->assertSame(OwnerStatement::STATUS_FINALIZED, $finalized->status);
        $this->assertNotNull($finalized->finalized_at);
        $this->assertSame(1, OwnerLedgerEntry::where('owner_statement_id', $finalized->id)->whereNotNull('locked_at')->count());
    }

    private function workspace(string $type = Account::TYPE_INDIVIDUAL_LANDLORD): array
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Integrity '.str()->random(5),
            'slug' => 'integrity-'.str()->lower(str()->random(8)),
            'type' => $type,
            'status' => Account::STATUS_ACTIVE,
            'currency' => 'RWF',
            'timezone' => 'Africa/Kigali',
        ]);
        $account->users()->attach($user, ['role' => Account::ROLE_OWNER, 'is_active' => true]);
        $this->useAccount($user, $account);
        $owner = PropertyOwner::create(['name' => 'Owner']);
        if ($type === Account::TYPE_INDIVIDUAL_LANDLORD) {
            $account->forceFill(['self_property_owner_id' => $owner->id])->saveQuietly();
        }
        $property = Property::create(['property_owner_id' => $owner->id, 'name' => 'Property', 'type' => 'apartment']);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_code' => 'UNIT-A',
            'monthly_rent' => '500000.00', 'status' => Unit::STATUS_OCCUPIED,
        ]);
        $tenant = Tenant::create(['full_name' => 'Tenant', 'id_number' => 'ID-'.str()->random(5)]);
        $lease = Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01', 'monthly_rent' => '500000.00',
            'deposit' => '500000.00', 'status' => Lease::STATUS_DRAFT,
        ]);

        return [$user, $account, compact('owner', 'property', 'unit', 'tenant', 'lease')];
    }

    private function secondPortfolio(Account $account, PropertyOwner $owner): array
    {
        $property = Property::create(['property_owner_id' => $owner->id, 'name' => 'Property B', 'type' => 'house']);
        $unit = Unit::create([
            'property_id' => $property->id, 'unit_code' => 'UNIT-B',
            'monthly_rent' => '300000.00', 'status' => Unit::STATUS_OCCUPIED,
        ]);
        $tenant = Tenant::create(['full_name' => 'Tenant B', 'id_number' => 'ID-'.str()->random(5)]);
        $lease = Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01', 'monthly_rent' => '300000.00',
            'deposit' => '300000.00', 'status' => Lease::STATUS_DRAFT,
        ]);

        return compact('property', 'unit', 'tenant', 'lease');
    }

    private function useAccount(User $user, Account $account): void
    {
        $this->actingAs($user);
        app(CurrentAccount::class)->forget();
        app(CurrentAccount::class)->switch($user, $account->id);
    }

    private function lateFeePolicy(int $graceDays): void
    {
        Setting::set('rent.late_fee_enabled', '1');
        Setting::set('rent.late_fee_type', 'fixed');
        Setting::set('rent.late_fee_value', '50000');
        Setting::set('rent.late_fee_grace_days', (string) $graceDays);
    }

    private function invoice(array $portfolio, string $dueDate): RentInvoice
    {
        return RentInvoice::create([
            'lease_id' => $portfolio['lease']->id,
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'due_date' => $dueDate, 'amount_due' => '500000.00',
            'amount_paid' => '0.00', 'late_fee' => '0.00',
            'total_due' => '500000.00', 'status' => 'unpaid',
        ]);
    }

    private function adjust(PropertyOwner $owner, User $user, string $amount, string $date): OwnerLedgerEntry
    {
        return app(OwnerLedgerAdjustmentService::class)->record(
            $owner,
            $amount,
            OwnerLedgerEntry::DIRECTION_CREDIT,
            'Integrity adjustment',
            null,
            $user,
            $date,
        );
    }

    private function expense(array $portfolio, string $date): PropertyExpense
    {
        return PropertyExpense::create([
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'unit_id' => $portfolio['unit']->id,
            'lease_id' => $portfolio['lease']->id,
            'category' => 'repair', 'description' => 'Backdated repair',
            'amount' => '100.00', 'occurred_on' => $date,
        ]);
    }

    private function assertDraftIsStale(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected stale owner statement finalization to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This statement has new or changed financial activity. Regenerate the draft before finalizing.',
                $exception->errors()['statement'][0] ?? null,
            );
        }
    }
}
