<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\ContractTemplate;

class ContractRenderService
{
    public function render(Lease $lease, ContractTemplate $template): string
    {
        $lease->loadMissing(['unit','tenant']);

        $vars = [
            '{{tenant_full_name}}' => $lease->tenant?->full_name ?? '',
            '{{tenant_phone}}' => $lease->tenant?->phone ?? '',
            '{{tenant_email}}' => $lease->tenant?->email ?? '',
            '{{tenant_national_id}}' => $lease->tenant?->national_id ?? '',

            '{{unit_code}}' => $lease->unit?->unit_code ?? '',
            '{{lease_start_date}}' => optional($lease->start_date)->format('Y-m-d') ?? '',
            '{{lease_end_date}}' => $lease->end_date ? optional($lease->end_date)->format('Y-m-d') : '',
            '{{monthly_rent}}' => number_format((float) $lease->monthly_rent, 0),
            '{{deposit}}' => number_format((float) $lease->deposit, 0),

            // Landlord fields (set in settings later, or hardcode for now)
            '{{landlord_name}}' => \App\Models\Setting::get('company.landlord_name', 'Landlord'),
            '{{landlord_phone}}' => \App\Models\Setting::get('company.landlord_phone', ''),
            '{{landlord_email}}' => \App\Models\Setting::get('company.landlord_email', ''),
            '{{property_address}}' => \App\Models\Setting::get('company.property_address', ''),
        ];

        $html = $template->body_html;

        return str_replace(array_keys($vars), array_values($vars), $html);
    }
}