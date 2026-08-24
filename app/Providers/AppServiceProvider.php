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
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\RentInvoice;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAsset;
use App\Observers\LeaseObserver;
use App\Policies\AccountOwnedPolicy;
use App\Policies\AccountPolicy;
use App\Policies\ManagementAgreementPolicy;
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
        Gate::policy(ManagementAgreement::class, ManagementAgreementPolicy::class);

        foreach ([
            AssetItem::class,
            ContractTemplate::class,
            Inspection::class,
            Lease::class,
            LeaseContract::class,
            MaintenanceTicket::class,
            Property::class,
            PropertyOwner::class,
            RentInvoice::class,
            RentPayment::class,
            Tenant::class,
            Unit::class,
            UnitAsset::class,
        ] as $model) {
            Gate::policy($model, AccountOwnedPolicy::class);
        }

        Lease::observe(LeaseObserver::class);
    }
}
