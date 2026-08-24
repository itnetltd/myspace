<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Services\ContractRenderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeaseContractController extends Controller
{
    public function generate(Request $request, Lease $lease)
    {
        Gate::authorize('view', $lease);

        $validated = $request->validate([
            'template_id' => ['required', 'integer'],
        ]);
        $templateId = (int) $validated['template_id'];
        $template = ContractTemplate::where('is_active', true)->findOrFail($templateId);

        $contract = DB::transaction(function () use ($lease, $template) {
            $rendered = app(ContractRenderService::class)->render($lease, $template);

            return LeaseContract::create([
                'lease_id' => $lease->id,
                'contract_template_id' => $template->id,
                'language' => $template->language,
                'status' => 'draft',
                'rendered_html' => $rendered,
            ]);
        });

        return redirect()->route('contracts.pdf', $contract);
    }

    public function pdf(LeaseContract $contract)
    {
        Gate::authorize('view', $contract);

        $contract->loadMissing(['lease.unit', 'lease.tenant', 'template']);

        $pdf = Pdf::loadView('pdf.lease-contract', [
            'contract' => $contract,
        ])->setPaper('a4', 'portrait');

        $unit = $contract->lease?->unit?->unit_code ?? 'unit';
        $tenant = $contract->lease?->tenant?->full_name ?? 'tenant';

        return $pdf->download("Lease_Contract_{$unit}_{$tenant}_C{$contract->id}.pdf");
    }
}
