<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Lease;
use App\Models\ManagementAgreement;
use App\Models\OwnerDisbursement;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerStatement;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyOwner;
use App\Models\RentInvoice;
use App\Models\RentPayment;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\OwnerBalanceService;
use App\Services\OwnerFinancialSummaryService;
use App\Services\OwnerStatementService;
use App\Services\PropertyExpenseService;
use App\Services\RentPaymentLedgerService;
use App\Support\CurrentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OwnerFinancialEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_rent_payment_creates_cash_basis_owner_credit_idempotently(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD);
        $this->useAccount($user, $account);
        $invoice = $this->invoice($portfolio, '500000.00');

        RentPayment::create([
            'account_id' => $account->id,
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-10',
            'amount' => '300000.00',
        ]);

        $entry = OwnerLedgerEntry::query()->sole();
        $this->assertSame(OwnerLedgerEntry::TYPE_RENT_INCOME, $entry->entry_type);
        $this->assertSame('300000.00', $entry->amount);
        $this->assertSame('300000.00', app(OwnerBalanceService::class)->balance($portfolio['owner']));

        app(RentPaymentLedgerService::class)->syncInvoice($invoice);
        $this->assertSame(1, OwnerLedgerEntry::query()->count());
    }

    public function test_payment_allocation_separates_principal_and_late_fee_in_chronological_order(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD);
        $this->useAccount($user, $account);
        Setting::set('rent.late_fee_enabled', '1');
        Setting::set('rent.late_fee_type', 'fixed');
        Setting::set('rent.late_fee_value', '50000');
        Setting::set('rent.late_fee_grace_days', '0');
        $invoice = $this->invoice($portfolio, '500000.00', '2026-08-01');

        RentPayment::create([
            'account_id' => $account->id,
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-10',
            'amount' => '520000.00',
        ]);

        $this->assertSame('500000.00', OwnerLedgerEntry::query()->where('source_key', 'principal')->sole()->amount);
        $this->assertSame('20000.00', OwnerLedgerEntry::query()->where('source_key', 'late_fee')->sole()->amount);
        $this->assertSame(2, OwnerLedgerEntry::query()->count());
    }

    public function test_draft_expense_does_not_affect_balance_and_posting_is_idempotent(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD);
        $this->useAccount($user, $account);
        $expense = $this->expense($portfolio, '125000.00');

        $this->assertSame('0.00', app(OwnerBalanceService::class)->balance($portfolio['owner']));
        $service = app(PropertyExpenseService::class);
        $service->approve($expense, $user);
        $service->post($expense->refresh(), $user);
        $service->post($expense->refresh(), $user);

        $this->assertSame(1, OwnerLedgerEntry::query()->where('source_type', 'property_expense')->count());
        $this->assertSame('-125000.00', app(OwnerBalanceService::class)->balance($portfolio['owner']));
    }

    public function test_cross_account_expense_relationships_are_rejected(): void
    {
        [$userA, $accountA, $portfolioA] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD, 'A');
        [, , $portfolioB] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD, 'B');
        $this->useAccount($userA, $accountA);

        $this->expectException(ValidationException::class);
        PropertyExpense::create([
            'property_owner_id' => $portfolioA['owner']->id,
            'property_id' => $portfolioB['property']->id,
            'category' => 'repair',
            'description' => 'Forged relationship',
            'amount' => '1000.00',
            'occurred_on' => '2026-08-10',
        ]);
    }

    #[DataProvider('managementFeeCases')]
    public function test_management_fee_types_use_actual_principal_and_do_not_duplicate(
        string $type,
        string $percentage,
        string $fixed,
        string $expected,
    ): void {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->useAccount($user, $account);
        $this->agreement($account, $portfolio, $type, $percentage, $fixed);
        $invoice = $this->invoice($portfolio, '3000000.00');
        RentPayment::create([
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-08-10',
            'amount' => '2000000.00',
        ]);

        $service = app(OwnerStatementService::class);
        $service->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);
        $service->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);

        $fee = OwnerLedgerEntry::query()->where('entry_type', OwnerLedgerEntry::TYPE_MANAGEMENT_FEE)->sole();
        $this->assertSame($expected, $fee->amount);
        $this->assertSame(1, OwnerLedgerEntry::query()->where('entry_type', OwnerLedgerEntry::TYPE_MANAGEMENT_FEE)->count());
    }

    public static function managementFeeCases(): array
    {
        return [
            'percentage' => [ManagementAgreement::FEE_PERCENTAGE, '10.0000', '0.00', '200000.00'],
            'fixed' => [ManagementAgreement::FEE_FIXED, '0.0000', '50000.00', '50000.00'],
            'combined' => [ManagementAgreement::FEE_PERCENTAGE_PLUS_FIXED, '8.0000', '50000.00', '210000.00'],
        ];
    }

    public function test_individual_landlord_statement_has_zero_management_fee(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD);
        $this->useAccount($user, $account);
        $invoice = $this->invoice($portfolio, '500000.00');
        RentPayment::create(['rent_invoice_id' => $invoice->id, 'paid_on' => '2026-08-10', 'amount' => '500000.00']);

        $statement = app(OwnerStatementService::class)
            ->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);

        $this->assertSame('0.00', $statement->management_fees);
        $this->assertFalse(OwnerLedgerEntry::query()->where('entry_type', OwnerLedgerEntry::TYPE_MANAGEMENT_FEE)->exists());
    }

    public function test_fixed_fee_applies_once_for_an_overlapping_month_without_rent_collection(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->useAccount($user, $account);
        $this->agreement($account, $portfolio, ManagementAgreement::FEE_FIXED, '0.0000', '50000.00');
        $service = app(OwnerStatementService::class);

        $service->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);
        $service->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);

        $this->assertSame('50000.00', OwnerLedgerEntry::query()
            ->where('entry_type', OwnerLedgerEntry::TYPE_MANAGEMENT_FEE)->sole()->amount);
    }

    public function test_maintenance_approval_limit_controls_owner_approval_requirement(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->useAccount($user, $account);
        $this->agreement($account, $portfolio, ManagementAgreement::FEE_FIXED, '0.0000', '0.00');

        $withinLimit = $this->expense($portfolio, '500000.00', 'maintenance');
        $aboveLimit = $this->expense($portfolio, '500000.01', 'maintenance');

        $this->assertFalse($withinLimit->owner_approval_required);
        $this->assertSame(PropertyExpense::STATUS_DRAFT, $withinLimit->status);
        $this->assertTrue($aboveLimit->owner_approval_required);
        $this->assertSame(PropertyExpense::STATUS_AWAITING_APPROVAL, $aboveLimit->status);
    }

    public function test_ambiguous_equally_applicable_agreements_are_rejected(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->useAccount($user, $account);
        $this->agreement($account, $portfolio, ManagementAgreement::FEE_FIXED, '0.0000', '50000.00');
        $this->agreement($account, $portfolio, ManagementAgreement::FEE_FIXED, '0.0000', '25000.00');

        $this->expectException(ValidationException::class);
        app(OwnerStatementService::class)
            ->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);
    }

    public function test_balance_and_statement_totals_include_income_expense_fee_and_disbursement(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->useAccount($user, $account);
        $this->agreement($account, $portfolio, ManagementAgreement::FEE_PERCENTAGE, '10.0000', '0.00');
        $invoice = $this->invoice($portfolio, '1000000.00');
        RentPayment::create(['rent_invoice_id' => $invoice->id, 'paid_on' => '2026-08-10', 'amount' => '1000000.00']);
        $expense = $this->expense($portfolio, '100000.00');
        $expense->forceFill(['owner_approved_at' => now(), 'owner_approved_by' => $user->id])->save();
        app(PropertyExpenseService::class)->approve($expense, $user);
        app(PropertyExpenseService::class)->post($expense->refresh(), $user);
        OwnerDisbursement::create([
            'property_owner_id' => $portfolio['owner']->id,
            'amount' => '300000.00',
            'paid_on' => '2026-08-20',
            'method' => 'bank_transfer',
        ]);

        $disbursementEntry = OwnerLedgerEntry::query()
            ->where('entry_type', OwnerLedgerEntry::TYPE_OWNER_DISBURSEMENT)
            ->sole();
        $this->assertSame(OwnerLedgerEntry::DIRECTION_DEBIT, $disbursementEntry->direction);
        $this->assertSame('300000.00', $disbursementEntry->amount);

        $statement = app(OwnerStatementService::class)
            ->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);

        $this->assertSame('1000000.00', $statement->rent_collected);
        $this->assertSame('100000.00', $statement->expenses);
        $this->assertSame('100000.00', $statement->management_fees);
        $this->assertSame('300000.00', $statement->owner_disbursements);
        $this->assertSame('500000.00', $statement->closing_balance);
        $this->assertSame('500000.00', app(OwnerBalanceService::class)->balance($portfolio['owner']));
        $this->assertSame('500000.00', app(OwnerFinancialSummaryService::class)
            ->forAccount($account, '2026-08-01', '2026-08-31')['amounts_payable_to_owners']);
    }

    public function test_statement_opening_closing_regeneration_and_finalization_are_safe(): void
    {
        [$user, $account, $portfolio] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD);
        $this->useAccount($user, $account);
        OwnerLedgerEntry::create([
            'property_owner_id' => $portfolio['owner']->id,
            'entry_number' => 'LE-OPENING',
            'entry_type' => OwnerLedgerEntry::TYPE_CREDIT_ADJUSTMENT,
            'direction' => OwnerLedgerEntry::DIRECTION_CREDIT,
            'amount' => '200000.00',
            'currency' => 'RWF',
            'occurred_on' => '2026-07-31',
            'description' => 'Opening adjustment',
            'posted_at' => now(),
        ]);
        $invoice = $this->invoice($portfolio, '500000.00');
        RentPayment::create(['rent_invoice_id' => $invoice->id, 'paid_on' => '2026-08-10', 'amount' => '500000.00']);
        $service = app(OwnerStatementService::class);
        $statement = $service->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);
        $lineCount = $statement->lines()->count();
        $statement = $service->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);

        $this->assertSame('200000.00', $statement->opening_balance);
        $this->assertSame('700000.00', $statement->closing_balance);
        $this->assertSame($lineCount, $statement->lines()->count());
        $this->get(route('reports.owner-statement', $statement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $service->finalize($statement, $user);
        $snapshot = $statement->lines()->pluck('description')->all();

        try {
            $service->generateDraft($portfolio['owner'], '2026-08-01', '2026-08-31', $user);
            $this->fail('Finalized statement regeneration should fail.');
        } catch (ValidationException) {
            $this->assertSame($snapshot, $statement->fresh()->lines()->pluck('description')->all());
        }
    }

    public function test_financial_role_authorization_and_cross_workspace_pdf_security(): void
    {
        [$ownerUser, $accountA, $portfolioA] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD, 'A');
        [, $accountB, $portfolioB] = $this->workspace(Account::TYPE_INDIVIDUAL_LANDLORD, 'B');
        $this->useAccount($ownerUser, $accountA);
        $statementB = OwnerStatement::withoutEvents(fn () => OwnerStatement::withoutGlobalScopes()->create([
            'account_id' => $accountB->id,
            'property_owner_id' => $portfolioB['owner']->id,
            'statement_number' => 'OS-FOREIGN',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'currency' => 'RWF',
            'generated_at' => now(),
        ]));
        $ledgerB = OwnerLedgerEntry::withoutEvents(fn () => OwnerLedgerEntry::withoutGlobalScopes()->create([
            'account_id' => $accountB->id,
            'property_owner_id' => $portfolioB['owner']->id,
            'entry_number' => 'LE-FOREIGN',
            'entry_type' => OwnerLedgerEntry::TYPE_CREDIT_ADJUSTMENT,
            'direction' => OwnerLedgerEntry::DIRECTION_CREDIT,
            'amount' => '100.00',
            'currency' => 'RWF',
            'occurred_on' => '2026-08-01',
            'description' => 'Foreign entry',
            'posted_at' => now(),
        ]));

        $this->assertNull(PropertyExpense::find($this->expenseWithoutContext($portfolioB)->id));
        $this->assertNull(OwnerLedgerEntry::find($ledgerB->id));
        $this->assertNull(OwnerStatement::find($statementB->id));
        $this->get(route('reports.owner-statement', $statementB))->assertNotFound();

        $draft = OwnerStatement::create([
            'property_owner_id' => $portfolioA['owner']->id,
            'statement_number' => 'OS-ROLE',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'currency' => 'RWF',
            'generated_at' => now(),
        ]);

        foreach ([
            Account::ROLE_ACCOUNTANT => [true, true, true],
            Account::ROLE_PROPERTY_MANAGER => [true, false, false],
            Account::ROLE_MAINTENANCE => [true, false, false],
            Account::ROLE_VIEWER => [false, false, false],
            Account::ROLE_ADMINISTRATOR => [true, true, true],
            Account::ROLE_OWNER => [true, true, true],
        ] as $role => [$manageExpense, $finalize, $payout]) {
            $user = User::factory()->create();
            $accountA->users()->attach($user, ['role' => $role, 'is_active' => true]);
            $this->useAccount($user, $accountA);

            $this->assertSame($manageExpense, Gate::allows('create', PropertyExpense::class), $role.' expense');
            $this->assertSame($finalize, Gate::allows('finalize', $draft), $role.' finalize');
            $this->assertSame($payout, Gate::allows('create', OwnerDisbursement::class), $role.' payout');
            if ($role === Account::ROLE_MAINTENANCE) {
                $this->assertFalse(Gate::allows('post', $this->expense($portfolioA, '1000.00')));
            }
        }
    }

    private function workspace(string $type, string $suffix = ''): array
    {
        auth()->logout();
        app(CurrentAccount::class)->forget();
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Financial '.$suffix.str()->random(4),
            'slug' => 'financial-'.str()->lower(str()->random(8)),
            'type' => $type,
            'status' => Account::STATUS_ACTIVE,
            'currency' => 'RWF',
            'timezone' => 'Africa/Kigali',
        ]);
        $account->users()->attach($user, ['role' => Account::ROLE_OWNER, 'is_active' => true]);
        $owner = PropertyOwner::create(['account_id' => $account->id, 'name' => 'Owner '.$suffix]);
        if ($type === Account::TYPE_INDIVIDUAL_LANDLORD) {
            $account->forceFill(['self_property_owner_id' => $owner->id])->saveQuietly();
        }
        $property = Property::create([
            'account_id' => $account->id, 'property_owner_id' => $owner->id,
            'name' => 'Property '.$suffix, 'type' => 'apartment',
        ]);
        $unit = Unit::create([
            'account_id' => $account->id, 'property_id' => $property->id,
            'unit_code' => 'UNIT-'.$suffix.str()->upper(str()->random(3)),
            'monthly_rent' => '1000000.00', 'status' => Unit::STATUS_OCCUPIED,
        ]);
        $tenant = Tenant::create([
            'account_id' => $account->id, 'full_name' => 'Tenant '.$suffix,
            'id_number' => 'ID-'.$suffix.str()->upper(str()->random(5)),
        ]);
        $lease = Lease::create([
            'account_id' => $account->id, 'unit_id' => $unit->id, 'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01', 'monthly_rent' => '1000000.00',
            'deposit' => '1000000.00', 'status' => Lease::STATUS_DRAFT,
        ]);

        return [$user, $account, compact('owner', 'property', 'unit', 'tenant', 'lease')];
    }

    private function useAccount(User $user, Account $account): void
    {
        $this->actingAs($user);
        app(CurrentAccount::class)->forget();
        app(CurrentAccount::class)->switch($user, $account->id);
    }

    private function invoice(array $portfolio, string $amount, string $dueDate = '2026-08-31'): RentInvoice
    {
        return RentInvoice::create([
            'lease_id' => $portfolio['lease']->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'due_date' => $dueDate,
            'amount_due' => $amount,
            'amount_paid' => '0.00',
            'late_fee' => '0.00',
            'total_due' => $amount,
            'status' => 'unpaid',
        ]);
    }

    private function expense(array $portfolio, string $amount, string $category = 'repair'): PropertyExpense
    {
        return PropertyExpense::create([
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'unit_id' => $portfolio['unit']->id,
            'lease_id' => $portfolio['lease']->id,
            'category' => $category,
            'description' => 'Repair expense',
            'amount' => $amount,
            'occurred_on' => '2026-08-15',
        ]);
    }

    private function expenseWithoutContext(array $portfolio): PropertyExpense
    {
        return PropertyExpense::withoutEvents(fn () => PropertyExpense::withoutGlobalScopes()->create([
            'account_id' => $portfolio['property']->account_id,
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'expense_number' => 'EXP-FOREIGN',
            'category' => 'repair',
            'description' => 'Foreign expense',
            'amount' => '100.00',
            'currency' => 'RWF',
            'occurred_on' => '2026-08-15',
        ]));
    }

    private function agreement(Account $account, array $portfolio, string $type, string $percentage, string $fixed): ManagementAgreement
    {
        return ManagementAgreement::create([
            'account_id' => $account->id,
            'property_owner_id' => $portfolio['owner']->id,
            'property_id' => $portfolio['property']->id,
            'reference_number' => 'AGR-'.str()->upper(str()->random(5)),
            'start_date' => '2026-01-01',
            'management_fee_type' => $type,
            'management_fee_percentage' => $percentage,
            'management_fee_fixed_amount' => $fixed,
            'status' => ManagementAgreement::STATUS_ACTIVE,
            'maintenance_approval_limit' => '500000.00',
        ]);
    }
}
