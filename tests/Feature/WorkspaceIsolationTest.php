<?php

namespace Tests\Feature;

use App\Filament\Resources\LeaseContractResource\Pages\CreateLeaseContract;
use App\Filament\Resources\LeaseResource;
use App\Filament\Resources\ManagementAgreementResource;
use App\Filament\Resources\PropertyOwnerResource;
use App\Models\Account;
use App\Models\AssetItem;
use App\Models\ContractTemplate;
use App\Models\Inspection;
use App\Models\InspectionLine;
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
use App\Models\UnitAsset;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_use_only_accounts_with_an_active_membership(): void
    {
        $user = User::factory()->create();
        $accountA = $this->createAccount('Account A');
        $accountB = $this->createAccount('Account B');
        $this->attach($user, $accountA);
        $this->actingAs($user);

        $this->assertSame($accountA->id, app(CurrentAccount::class)->switch($user, $accountA->id)->id);

        $this->expectException(AuthorizationException::class);
        app(CurrentAccount::class)->switch($user, $accountB->id);
    }

    public function test_properties_owners_and_leases_are_isolated(): void
    {
        [$user, $accountA, $portfolioA, $accountB, $portfolioB] = $this->twoWorkspaces();
        $this->useAccount($user, $accountA);

        $this->assertNotNull(Property::find($portfolioA['property']->id));
        $this->assertNull(Property::find($portfolioB['property']->id));
        $this->assertNotNull(PropertyOwner::find($portfolioA['owner']->id));
        $this->assertNull(PropertyOwner::find($portfolioB['owner']->id));
        $this->assertNotNull(Lease::find($portfolioA['lease']->id));
        $this->assertNull(Lease::find($portfolioB['lease']->id));
        $this->assertTrue(Gate::allows('view', $portfolioA['lease']));
        $this->assertFalse(Gate::allows('view', $portfolioB['lease']));
    }

    public function test_financial_and_maintenance_records_are_isolated(): void
    {
        [$user, $accountA, $portfolioA, $accountB, $portfolioB] = $this->twoWorkspaces();
        $this->useAccount($user, $accountA);

        $this->assertNotNull(RentInvoice::find($portfolioA['invoice']->id));
        $this->assertNull(RentInvoice::find($portfolioB['invoice']->id));
        $this->assertNotNull(RentPayment::find($portfolioA['payment']->id));
        $this->assertNull(RentPayment::find($portfolioB['payment']->id));
        $this->assertNotNull(MaintenanceTicket::find($portfolioA['ticket']->id));
        $this->assertNull(MaintenanceTicket::find($portfolioB['ticket']->id));
    }

    public function test_pdf_routes_cannot_be_used_to_guess_foreign_workspace_ids(): void
    {
        [$user, $accountA, $portfolioA, $accountB, $portfolioB] = $this->twoWorkspaces();
        $this->useAccount($user, $accountA);

        $this->get(route('reports.rent.statement.lease', $portfolioB['lease']))->assertNotFound();
        $this->get(route('reports.moveout', $portfolioB['inspection']))->assertNotFound();
        $this->get(route('contracts.pdf', $portfolioB['contract']))->assertNotFound();
    }

    public function test_filament_queries_and_relationship_ids_are_account_scoped(): void
    {
        [$user, $accountA, $portfolioA, $accountB, $portfolioB] = $this->twoWorkspaces();
        $this->useAccount($user, $accountA);

        $this->assertSame([$portfolioA['owner']->id], PropertyOwnerResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$portfolioA['lease']->id], LeaseResource::getEloquentQuery()->pluck('id')->all());

        $this->expectException(ValidationException::class);
        UnitAsset::create([
            'unit_id' => $portfolioA['unit']->id,
            'asset_item_id' => $portfolioB['asset']->id,
            'quantity' => 1,
        ]);
    }

    public function test_management_agreements_are_company_only_and_account_isolated(): void
    {
        $userA = User::factory()->create();
        $individual = $this->createAccount('Individual', Account::TYPE_INDIVIDUAL_LANDLORD);
        $company = $this->createAccount('Company', Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->attach($userA, $individual);
        $companyPortfolio = $this->createPortfolio($company, 'B');
        $this->useAccount($userA, $individual);

        $this->assertFalse(ManagementAgreementResource::shouldRegisterNavigation());
        $this->assertNull(ManagementAgreement::find($companyPortfolio['agreement']->id));
        $this->assertFalse(Gate::allows('view', $companyPortfolio['agreement']));

        $userB = User::factory()->create();
        $this->attach($userB, $company);
        $this->useAccount($userB, $company);

        $this->assertTrue(ManagementAgreementResource::shouldRegisterNavigation());
        $this->assertNotNull(ManagementAgreement::find($companyPortfolio['agreement']->id));
    }

    public function test_contract_generation_keeps_required_keys_and_workspace_parties(): void
    {
        $user = User::factory()->create();
        $account = $this->createAccount('Manager', Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->attach($user, $account);
        $portfolio = $this->createPortfolio($account, 'A');
        $this->useAccount($user, $account);

        $response = $this->post(route('contracts.generate', $portfolio['lease']), [
            'template_id' => $portfolio['template']->id,
        ]);

        $contract = LeaseContract::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('contracts.pdf', $contract));
        $this->assertSame($portfolio['lease']->id, $contract->lease_id);
        $this->assertSame($portfolio['template']->id, $contract->contract_template_id);
        $this->assertStringContainsString($portfolio['owner']->name, $contract->rendered_html);
        $this->assertStringContainsString($account->name, $contract->rendered_html);
    }

    public function test_filament_contract_creation_keeps_required_foreign_keys(): void
    {
        $user = User::factory()->create();
        $account = $this->createAccount('Filament Contract Account', Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->attach($user, $account);
        $portfolio = $this->createPortfolio($account, 'F');
        $this->useAccount($user, $account);

        $method = new ReflectionMethod(CreateLeaseContract::class, 'mutateFormDataBeforeCreate');
        $data = $method->invoke(new CreateLeaseContract, [
            'lease_id' => $portfolio['lease']->id,
            'contract_template_id' => $portfolio['template']->id,
            'status' => 'draft',
            'rendered_html' => '',
        ]);

        $this->assertSame($portfolio['lease']->id, $data['lease_id']);
        $this->assertSame($portfolio['template']->id, $data['contract_template_id']);
        $this->assertNotEmpty($data['rendered_html']);
    }

    public function test_contract_and_report_routes_require_authentication(): void
    {
        $account = $this->createAccount('Unauthenticated Route Account', Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $portfolio = $this->createPortfolio($account, 'G');

        $this->get(route('reports.rent.statement.lease', $portfolio['lease']))
            ->assertRedirect(route('filament.admin.auth.login'));
        $this->get(route('contracts.pdf', $portfolio['contract']))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    private function twoWorkspaces(): array
    {
        $user = User::factory()->create();
        $accountA = $this->createAccount('Account A', Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $accountB = $this->createAccount('Account B', Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        $this->attach($user, $accountA);

        return [
            $user,
            $accountA,
            $this->createPortfolio($accountA, 'A'),
            $accountB,
            $this->createPortfolio($accountB, 'B'),
        ];
    }

    private function createAccount(string $name, string $type = Account::TYPE_INDIVIDUAL_LANDLORD): Account
    {
        return Account::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(6),
            'type' => $type,
            'status' => Account::STATUS_ACTIVE,
            'currency' => 'RWF',
            'timezone' => 'Africa/Kigali',
        ]);
    }

    private function attach(User $user, Account $account): void
    {
        $account->users()->attach($user, [
            'role' => Account::ROLE_OWNER,
            'is_active' => true,
        ]);
    }

    private function useAccount(User $user, Account $account): void
    {
        $this->actingAs($user);
        app(CurrentAccount::class)->forget();
        app(CurrentAccount::class)->switch($user, $account->id);
    }

    private function createPortfolio(Account $account, string $suffix): array
    {
        $owner = PropertyOwner::create([
            'account_id' => $account->id,
            'type' => PropertyOwner::TYPE_INDIVIDUAL,
            'name' => "Owner {$suffix}",
            'phone' => "07880000{$suffix}",
        ]);
        $property = Property::create([
            'account_id' => $account->id,
            'property_owner_id' => $owner->id,
            'name' => "Property {$suffix}",
            'type' => 'apartment',
            'address' => "Address {$suffix}",
        ]);
        $unit = Unit::create([
            'account_id' => $account->id,
            'property_id' => $property->id,
            'unit_code' => "UNIT-{$suffix}",
            'monthly_rent' => 100000,
            'status' => Unit::STATUS_VACANT,
        ]);
        $tenant = Tenant::create([
            'account_id' => $account->id,
            'full_name' => "Tenant {$suffix}",
            'id_number' => "ID-{$suffix}",
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
            'ticket_no' => "MT-{$suffix}",
            'title' => "Ticket {$suffix}",
        ]);
        $asset = AssetItem::create([
            'account_id' => $account->id,
            'name' => "Asset {$suffix}",
        ]);
        UnitAsset::create([
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'asset_item_id' => $asset->id,
            'quantity' => 1,
        ]);
        $inspection = Inspection::create([
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'lease_id' => $lease->id,
            'type' => 'move_out',
            'inspected_on' => '2026-02-01',
        ]);
        InspectionLine::create([
            'inspection_id' => $inspection->id,
            'asset_item_id' => $asset->id,
            'expected_qty' => 1,
            'found_qty' => 1,
        ]);
        $template = ContractTemplate::create([
            'account_id' => $account->id,
            'name' => "Template {$suffix}",
            'language' => 'en',
            'version' => '1.0',
            'is_active' => true,
            'body_html' => '{{owner_name}} | {{management_company_name}} | {{tenant_full_name}}',
        ]);
        $contract = LeaseContract::create([
            'account_id' => $account->id,
            'lease_id' => $lease->id,
            'contract_template_id' => $template->id,
            'language' => 'en',
            'status' => 'draft',
            'rendered_html' => 'Existing contract',
        ]);
        $agreement = ManagementAgreement::create([
            'account_id' => $account->id,
            'property_owner_id' => $owner->id,
            'property_id' => $property->id,
            'reference_number' => "AGR-{$suffix}",
            'start_date' => '2026-01-01',
            'management_fee_type' => ManagementAgreement::FEE_PERCENTAGE,
            'management_fee_value' => '10.00',
            'status' => ManagementAgreement::STATUS_ACTIVE,
        ]);

        return compact(
            'owner', 'property', 'unit', 'tenant', 'lease', 'invoice', 'payment', 'ticket',
            'asset', 'inspection', 'template', 'contract', 'agreement',
        );
    }
}
