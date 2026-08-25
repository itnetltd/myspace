<?php

namespace App\Observers;

use App\Models\OwnerDisbursement;
use App\Services\FinancialPeriodGuard;
use App\Services\OwnerDisbursementService;
use Illuminate\Support\Facades\Auth;

class OwnerDisbursementObserver
{
    public function __construct(
        private readonly OwnerDisbursementService $disbursements,
        private readonly FinancialPeriodGuard $periods,
    ) {}

    public function creating(OwnerDisbursement $disbursement): void
    {
        $disbursement->currency ??= $disbursement->account?->currency ?? 'RWF';
        $disbursement->created_by ??= Auth::id();
        $this->periods->ensureOpen(
            (int) $disbursement->account_id,
            (int) $disbursement->property_owner_id,
            $disbursement->paid_on,
        );
    }

    public function saved(OwnerDisbursement $disbursement): void
    {
        $this->disbursements->post($disbursement);
    }
}
