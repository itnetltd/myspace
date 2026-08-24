<?php

namespace Tests\Feature;

use App\Filament\Pages\RentPolicySettings;
use App\Models\Account;
use App\Models\AssetItem;
use App\Models\ContractTemplate;
use App\Models\Inspection;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\MaintenanceTicket;
use App\Models\ManagementAgreement;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\RentInvoice;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountOnboarding;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_is_read_only_and_cannot_generate_a_contract(): void
    {
        [$user, $records] = $this->workspaceForRole(Account::ROLE_VIEWER);

        $this->assertTrue(Gate::allows('view', $records['property']));
        $this->assertFalse(Gate::allows('create', Property::class));
        $this->assertFalse(Gate::allows('update', $records['property']));
        $this->assertFalse(Gate::allows('delete', $records['property']));

        $this->post(route('contracts.generate', $records['lease']), [
            'template_id' => $records['template']->id,
        ])->assertForbidden();
        $this->assertSame(0, LeaseContract::count());
    }

    public function test_maintenance_role_can_manage_tickets_only(): void
    {
        [, $records] = $this->workspaceForRole(Account::ROLE_MAINTENANCE);

        $this->assertTrue(Gate::allows('update', $records['ticket']));
        $this->assertTrue(Gate::allows('create', MaintenanceTicket::class));
        $this->assertFalse(Gate::allows('update', $records['lease']));
        $this->assertFalse(Gate::allows('create', RentPayment::class));
        $this->assertFalse(Gate::allows('update', $records['agreement']));
        $this->assertFalse(Gate::allows('create', LeaseContract::class));
        $this->get(route('reports.rent.statement.lease', $records['lease']))->assertForbidden();
    }

    public function test_accountant_can_manage_finances_but_not_operations(): void
    {
        [, $records] = $this->workspaceForRole(Account::ROLE_ACCOUNTANT);

        $this->assertTrue(Gate::allows('update', $records['invoice']));
        $this->assertTrue(Gate::allows('create', RentInvoice::class));
        $this->assertTrue(Gate::allows('update', $records['payment']));
        $this->assertTrue(Gate::allows('create', RentPayment::class));
        $this->get(route('reports.rent.statement.lease', $records['lease']))->assertOk();
        $this->assertFalse(Gate::allows('update', $records['property']));
        $this->assertFalse(Gate::allows('update', $records['lease']));
        $this->assertFalse(Gate::allows('update', $records['agreement']));
    }

    public function test_property_manager_has_operational_access_but_not_admin_financial_or_policy_access(): void
    {
        [$user, $records] = $this->workspaceForRole(Account::ROLE_PROPERTY_MANAGER);

        foreach (['property', 'owner', 'unit', 'tenant', 'lease', 'inspection', 'ticket'] as $record) {
            $this->assertTrue(Gate::allows('update', $records[$record]), $record);
        }

        $this->assertTrue(Gate::allows('create', LeaseContract::class));
        $this->assertFalse(Gate::allows('update', $records['account']));
        $this->assertFalse(Gate::allows('update', $records['invoice']));
        $this->assertFalse(Gate::allows('update', $records['payment']));
        $this->assertFalse(Gate::allows('update', $records['agreement']));
        $this->assertFalse(app(AccountAccess::class)->can(
            $user,
            $records['account'],
            AccountAccess::MANAGE_SETTINGS,
        ));
        $this->assertFalse(RentPolicySettings::canAccess());
    }

    public function test_administrator_has_broad_operational_and_workspace_access(): void
    {
        [, $records] = $this->workspaceForRole(Account::ROLE_ADMINISTRATOR);

        $this->assertTrue(Gate::allows('update', $records['account']));
        $this->assertTrue(Gate::allows('update', $records['property']));
        $this->assertTrue(Gate::allows('update', $records['invoice']));
        $this->assertTrue(Gate::allows('update', $records['agreement']));
        $this->assertTrue(Gate::allows('create', LeaseContract::class));
        $this->assertTrue(RentPolicySettings::canAccess());
        $this->assertFalse(Gate::allows('delete', $records['account']));
    }

    public function test_owner_has_full_workspace_access(): void
    {
        [$user, $records] = $this->workspaceForRole(Account::ROLE_OWNER);

        foreach (['property', 'owner', 'unit', 'tenant', 'lease', 'invoice', 'payment', 'ticket', 'inspection', 'asset', 'agreement'] as $record) {
            $this->assertTrue(Gate::allows('update', $records[$record]), $record);
        }

        $this->assertTrue(Gate::allows('update', $records['account']));
        $this->assertTrue(app(AccountAccess::class)->can(
            $user,
            $records['account'],
            AccountAccess::MANAGE_STAFF,
        ));
        $this->assertTrue(RentPolicySettings::canAccess());
    }

    public function test_individual_onboarding_creates_one_linked_self_owner_but_company_onboarding_does_not(): void
    {
        $user = User::factory()->create();
        $service = app(AccountOnboarding::class);

        $individual = $service->create($this->accountAttributes('Individual', Account::TYPE_INDIVIDUAL_LANDLORD), $user);
        $owner = $individual->selfPropertyOwner;

        $this->assertNotNull($owner);
        $this->assertSame($individual->id, $owner->account_id);
        $this->assertSame($individual->name, $owner->name);
        $this->assertSame($owner->id, $service->ensureSelfOwner($individual)->id);
        $this->assertSame(1, PropertyOwner::withoutGlobalScopes()->where('account_id', $individual->id)->count());

        app(CurrentAccount::class)->forget();
        $company = $service->create($this->accountAttributes('Company', Account::TYPE_PROPERTY_MANAGEMENT_COMPANY), $user);

        $this->assertNull($company->self_property_owner_id);
        $this->assertSame(0, PropertyOwner::withoutGlobalScopes()->where('account_id', $company->id)->count());
    }

    private function workspaceForRole(string $role): array
    {
        $user = User::factory()->create();
        $account = Account::create($this->accountAttributes('Role '.$role, Account::TYPE_PROPERTY_MANAGEMENT_COMPANY));
        $account->users()->attach($user, ['role' => $role, 'is_active' => true]);
        $records = $this->recordsFor($account);

        $this->actingAs($user);
        app(CurrentAccount::class)->forget();
        app(CurrentAccount::class)->switch($user, $account->id);

        return [$user, ['account' => $account, ...$records]];
    }

    private function recordsFor(Account $account): array
    {
        $owner = PropertyOwner::create(['account_id' => $account->id, 'name' => 'Client Owner']);
        $property = Property::create([
            'account_id' => $account->id,
            'property_owner_id' => $owner->id,
            'name' => 'Role Property',
            'type' => 'apartment',
        ]);
        $unit = Unit::create([
            'account_id' => $account->id,
            'property_id' => $property->id,
            'unit_code' => 'ROLE-1',
            'monthly_rent' => 100000,
            'status' => Unit::STATUS_VACANT,
        ]);
        $tenant = Tenant::create([
            'account_id' => $account->id,
            'full_name' => 'Role Tenant',
            'id_number' => 'ROLE-ID',
        ]);
        $lease = Lease::create([
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'monthly_rent' => 100000,
            'deposit' => 100000,
            'status' => Lease::STATUS_DRAFT,
        ]);
        $invoice = RentInvoice::create([
            'account_id' => $account->id,
            'lease_id' => $lease->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'due_date' => '2026-01-05',
            'amount_due' => 100000,
            'total_due' => 100000,
            'status' => 'unpaid',
        ]);
        $payment = RentPayment::create([
            'account_id' => $account->id,
            'rent_invoice_id' => $invoice->id,
            'paid_on' => '2026-01-05',
            'amount' => 10000,
        ]);
        $ticket = MaintenanceTicket::create([
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'lease_id' => $lease->id,
            'ticket_no' => 'ROLE-MT',
            'title' => 'Role Ticket',
        ]);
        $inspection = Inspection::create([
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'lease_id' => $lease->id,
            'type' => 'move_in',
            'inspected_on' => '2026-01-01',
        ]);
        $asset = AssetItem::create(['account_id' => $account->id, 'name' => 'Role Asset']);
        $template = ContractTemplate::create([
            'account_id' => $account->id,
            'name' => 'Role Template',
            'language' => 'en',
            'version' => '1.0',
            'is_active' => true,
            'body_html' => '{{tenant_full_name}}',
        ]);
        $agreement = ManagementAgreement::create([
            'account_id' => $account->id,
            'property_owner_id' => $owner->id,
            'property_id' => $property->id,
            'reference_number' => 'ROLE-AGR',
            'start_date' => '2026-01-01',
            'management_fee_type' => ManagementAgreement::FEE_PERCENTAGE,
            'management_fee_value' => '10.00',
            'status' => ManagementAgreement::STATUS_ACTIVE,
        ]);

        return compact(
            'owner', 'property', 'unit', 'tenant', 'lease', 'invoice', 'payment',
            'ticket', 'inspection', 'asset', 'template', 'agreement',
        );
    }

    private function accountAttributes(string $name, string $type): array
    {
        return [
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(6),
            'type' => $type,
            'status' => Account::STATUS_ACTIVE,
            'currency' => 'RWF',
            'timezone' => 'Africa/Kigali',
        ];
    }
}
