<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use Barryvdh\DomPDF\Facade\Pdf;

class MoveOutReportController extends Controller
{
    public function show(Inspection $inspection)
    {
        // Safety: only move_out inspections
        if ($inspection->type !== 'move_out') {
            abort(404);
        }

        $inspection->load(['unit', 'lease', 'lines.assetItem']);

        $data = [
            'inspection' => $inspection,
            'issues' => $inspection->reportIssues(),

            // Totals: keep both Suggested and Applied (override-aware)
            'totalDeductionSuggested' => $inspection->suggestedTotalDeduction(),
            'totalDeductionApplied'   => method_exists($inspection, 'appliedTotalDeduction')
                ? $inspection->appliedTotalDeduction()
                : $inspection->suggestedTotalDeduction(),

            'deposit' => $inspection->leaseDeposit(),

            'refundSuggested' => $inspection->suggestedRefundAmount(),
            'refundApplied'   => method_exists($inspection, 'appliedRefundAmount')
                ? $inspection->appliedRefundAmount()
                : $inspection->suggestedRefundAmount(),
        ];

        $pdf = Pdf::loadView('reports.move_out', $data)->setPaper('a4', 'portrait');

        $filename = 'move-out-report-inspection-' . $inspection->id . '.pdf';

        return $pdf->download($filename);
    }
}