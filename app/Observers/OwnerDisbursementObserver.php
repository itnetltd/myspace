<?php

namespace App\Observers;

use App\Models\OwnerDisbursement;
use App\Services\OwnerDisbursementService;
use Illuminate\Support\Facades\Auth;

class OwnerDisbursementObserver
{
    public function __construct(private readonly OwnerDisbursementService $disbursements) {}

    public function creating(OwnerDisbursement $disbursement): void
    {
        $disbursement->currency ??= $disbursement->account?->currency ?? 'RWF';
        $disbursement->created_by ??= Auth::id();
    }

    public function saved(OwnerDisbursement $disbursement): void
    {
        $this->disbursements->post($disbursement);
    }
}
