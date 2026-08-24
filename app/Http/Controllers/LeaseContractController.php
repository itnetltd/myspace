<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\ContractTemplate;
use App\Models\LeaseContract;
use App\Services\ContractRenderService;
use Illuminate\Http\Request;
use PDF;

class LeaseContractController extends Controller
{
    public function generate(Request $request, Lease $lease)
    {
        $templateId = (int) $request->get('template_id');
        $template = ContractTemplate::where('is_active', true)->findOrFail($templateId);

        $renderer = app(ContractRenderService::class);
        $rendered = $renderer->render($lease, $template);

        $contract = LeaseContract::create([
            'lease_id' => $lease->id,
            'contract_template_id' => $template->id,
            'language' => $template->language,
            'status' => 'draft',
            'rendered_html' => $rendered,
        ]);

        return redirect()->route('contracts.pdf', $contract);
    }

    public function pdf(LeaseContract $contract)
    {
        $contract->loadMissing(['lease.unit','lease.tenant','template']);

        $pdf = PDF::loadView('pdf.lease-contract', [
            'contract' => $contract,
        ])->setPaper('a4', 'portrait');

        $unit = $contract->lease?->unit?->unit_code ?? 'unit';
        $tenant = $contract->lease?->tenant?->full_name ?? 'tenant';

        return $pdf->download("Lease_Contract_{$unit}_{$tenant}_C{$contract->id}.pdf");
    }
}