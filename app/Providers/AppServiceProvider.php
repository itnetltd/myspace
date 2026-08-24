<?php

namespace App\Providers;

use App\Models\Account;
// ✅ Add these:
use App\Models\AssetItem;
use App\Models\ContractTemplate;
use App\Models\Inspection;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\MaintenanceTicket;
use App\Models\ManagementAgreement;
use App\Models\OwnerDisbursement;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerStatement;
use App\Models\Property;
use App\Models\PropertyExpense;
use App\Models\PropertyOwner;
use App\Models\RentInvoice;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAsset;
use App\Observers\LeaseObserver;
use App\Observers\OwnerDisbursementObserver;
use App\Observers\PropertyExpenseObserver;
use App\Policies\AccountPolicy;
use App\Policies\AssetPolicy;
use App\Policies\ContractPolicy;
use App\Policies\InspectionPolicy;
use App\Policies\LeasePolicy;
use App\Policies\MaintenanceTicketPolicy;
use App\Policies\ManagementAgreementPolicy;
use App\Policies\OwnerDisbursementPolicy;
use App\Policies\OwnerLedgerEntryPolicy;
use App\Policies\OwnerStatementPolicy;
use App\Policies\PropertyExpensePolicy;
use App\Policies\PropertyOwnerPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\RentInvoicePolicy;
use App\Policies\RentPaymentPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UnitPolicy;
use App\Support\CurrentAccount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentAccount::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ✅ Auto-generate invoices when lease becomes Active (observer)
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(AssetItem::class, AssetPolicy::class);
        Gate::policy(ContractTemplate::class, ContractPolicy::class);
        Gate::policy(Inspection::class, InspectionPolicy::class);
        Gate::policy(Lease::class, LeasePolicy::class);
        Gate::policy(LeaseContract::class, ContractPolicy::class);
        Gate::policy(MaintenanceTicket::class, MaintenanceTicketPolicy::class);
        Gate::policy(ManagementAgreement::class, ManagementAgreementPolicy::class);
        Gate::policy(OwnerDisbursement::class, OwnerDisbursementPolicy::class);
        Gate::policy(OwnerLedgerEntry::class, OwnerLedgerEntryPolicy::class);
        Gate::policy(OwnerStatement::class, OwnerStatementPolicy::class);
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(PropertyExpense::class, PropertyExpensePolicy::class);
        Gate::policy(PropertyOwner::class, PropertyOwnerPolicy::class);
        Gate::policy(RentInvoice::class, RentInvoicePolicy::class);
        Gate::policy(RentPayment::class, RentPaymentPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(UnitAsset::class, AssetPolicy::class);

        Lease::observe(LeaseObserver::class);
        OwnerDisbursement::observe(OwnerDisbursementObserver::class);
        PropertyExpense::observe(PropertyExpenseObserver::class);
    }
}
