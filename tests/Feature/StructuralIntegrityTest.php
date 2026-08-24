<?php

namespace Tests\Feature;

use App\Filament\Resources\LeaseContractResource\Pages\CreateLeaseContract;
use App\Models\Account;
use App\Models\ContractTemplate;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class StructuralIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_model_matches_the_repaired_schema(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Test Account',
            'slug' => 'test-account',
            'type' => 'landlord',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $account->users()->attach($user, [
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->assertTrue(Schema::hasColumns('accounts', [
            'name',
            'slug',
            'type',
            'status',
            'created_by',
        ]));
        $this->assertTrue(Schema::hasTable('account_user'));
        $this->assertTrue($account->users()->whereKey($user->id)->exists());
    }

    public function test_contract_generation_persists_required_foreign_keys(): void
    {
        $user = User::factory()->create();
        $lease = $this->createLease();
        $template = ContractTemplate::create([
            'name' => 'Test Lease',
            'language' => 'en',
            'version' => '1.0',
            'is_active' => true,
            'body_html' => 'Tenant ID: {{tenant_national_id}}',
        ]);

        $response = $this->actingAs($user)->post(route('contracts.generate', $lease), [
            'template_id' => $template->id,
        ]);

        $contract = LeaseContract::query()->sole();

        $response->assertRedirect(route('contracts.pdf', $contract));
        $this->assertSame($lease->id, $contract->lease_id);
        $this->assertSame($template->id, $contract->contract_template_id);
        $this->assertStringContainsString('TEST-ID-123', $contract->rendered_html);
    }

    public function test_filament_contract_creation_keeps_required_foreign_keys(): void
    {
        $lease = $this->createLease();
        $template = ContractTemplate::create([
            'name' => 'Filament Test Lease',
            'language' => 'en',
            'version' => '1.0',
            'is_active' => true,
            'body_html' => 'Tenant ID: {{tenant_national_id}}',
        ]);

        $method = new ReflectionMethod(CreateLeaseContract::class, 'mutateFormDataBeforeCreate');
        $data = $method->invoke(new CreateLeaseContract, [
            'lease_id' => $lease->id,
            'contract_template_id' => $template->id,
            'status' => 'draft',
            'rendered_html' => '',
        ]);

        $this->assertSame($lease->id, $data['lease_id']);
        $this->assertSame($template->id, $data['contract_template_id']);
        $this->assertNotEmpty($data['rendered_html']);
    }

    public function test_contract_and_report_routes_require_authentication(): void
    {
        $lease = $this->createLease();

        $this->get(route('reports.rent.statement.lease', $lease))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    private function createLease(): Lease
    {
        $property = Property::create([
            'name' => 'Test Property',
            'type' => 'apartment',
        ]);
        $unit = Unit::create([
            'property_id' => $property->id,
            'unit_code' => 'T-001',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'monthly_rent' => 100000,
            'status' => 'vacant',
        ]);
        $tenant = Tenant::create([
            'full_name' => 'Test Tenant',
            'id_number' => 'TEST-ID-123',
        ]);

        return Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'monthly_rent' => 100000,
            'deposit' => 100000,
            'status' => 'draft',
        ]);
    }
}
