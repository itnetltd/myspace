<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// ✅ Add these:
use App\Models\Lease;
use App\Observers\LeaseObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ✅ Auto-generate invoices when lease becomes Active (observer)
        Lease::observe(LeaseObserver::class);
    }
}