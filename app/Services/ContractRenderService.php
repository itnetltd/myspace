<?php

namespace App\Services;

use App\Models\ContractTemplate;
use App\Models\Lease;

class ContractRenderService
{
    public function render(Lease $lease, ContractTemplate $template): string
    {
        $lease->loadMissing(['unit.property.propertyOwner', 'unit.property.account', 'tenant']);

        $property = $lease->unit?->property;
        $owner = $property?->propertyOwner;
        $account = $property?->account;
        $managementCompany = $account?->isPropertyManagementCompany() ? $account : null;

        $vars = [
            '{{owner_name}}' => $owner?->name ?? '',
            '{{owner_phone}}' => $owner?->phone ?? '',
            '{{owner_email}}' => $owner?->email ?? '',
            '{{owner_tin}}' => $owner?->tin ?? '',

            '{{management_company_name}}' => $managementCompany?->name ?? '',
            '{{management_company_phone}}' => $managementCompany?->phone ?? '',
            '{{management_company_email}}' => $managementCompany?->email ?? '',
            '{{management_company_tin}}' => $managementCompany?->tin ?? '',

            '{{property_name}}' => $property?->name ?? '',
            '{{property_address}}' => $property?->address ?? '',

            '{{tenant_full_name}}' => $lease->tenant?->full_name ?? '',
            '{{tenant_phone}}' => $lease->tenant?->phone ?? '',
            '{{tenant_email}}' => $lease->tenant?->email ?? '',
            '{{tenant_national_id}}' => $lease->tenant?->id_number ?? '',

            '{{unit_code}}' => $lease->unit?->unit_code ?? '',
            '{{lease_start_date}}' => optional($lease->start_date)->format('Y-m-d') ?? '',
            '{{lease_end_date}}' => $lease->end_date ? optional($lease->end_date)->format('Y-m-d') : '',
            '{{monthly_rent}}' => number_format((float) $lease->monthly_rent, 0),
            '{{deposit}}' => number_format((float) $lease->deposit, 0),

            // Backward-compatible aliases for existing templates.
            '{{landlord_name}}' => $owner?->name ?? '',
            '{{landlord_phone}}' => $owner?->phone ?? '',
            '{{landlord_email}}' => $owner?->email ?? '',
        ];

        $html = $template->body_html;

        return str_replace(array_keys($vars), array_values($vars), $html);
    }
}
