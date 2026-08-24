<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use Illuminate\Http\Request;
use PDF;

class RentStatementController extends Controller
{
    public function lease(Lease $lease)
    {
        $lease->loadMissing([
            'unit',
            'tenant',
            'rentInvoices.payments',
        ]);

        // Make sure totals are up-to-date
        foreach ($lease->rentInvoices as $inv) {
            $inv->refreshPaymentTotals();
        }
        $lease->refresh();

        $invoices = $lease->rentInvoices()->orderBy('period_start')->get();

        $totals = [
            'amount_due' => (float) $invoices->sum('amount_due'),
            'late_fee'   => (float) $invoices->sum('late_fee'),
            'total_due'  => (float) $invoices->sum('total_due'),
            'paid'       => (float) $invoices->sum('amount_paid'),
            'balance'    => (float) ($invoices->sum('total_due') - $invoices->sum('amount_paid')),
        ];

        $pdf = PDF::loadView('pdf.rent-statement-lease', [
            'lease' => $lease,
            'invoices' => $invoices,
            'totals' => $totals,
        ])->setPaper('a4', 'portrait');

        $unit = $lease->unit?->unit_code ?? 'unit';
        $tenant = $lease->tenant?->full_name ?? 'tenant';

        return $pdf->download("Rent_Statement_{$unit}_{$tenant}_Lease{$lease->id}.pdf");
    }
}