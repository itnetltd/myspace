<?php

namespace App\Filament\Resources\LeaseContractResource\Pages;

use App\Filament\Resources\LeaseContractResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaseContract extends CreateRecord
{
    protected static string $resource = LeaseContractResource::class;

    public function mount(): void
    {
        parent::mount();

        // Auto-fill when the URL contains ?lease_id=1&contract_template_id=2
        $leaseId = request()->integer('lease_id');
        $templateId = request()->integer('contract_template_id');

        $data = [];

        if ($leaseId) {
            $data['lease_id'] = $leaseId;
        }

        if ($templateId) {
            $data['contract_template_id'] = $templateId;
        }

        if (! empty($data)) {
            $this->form->fill($data);
        }
    }

    /**
     * DB requires rendered_html NOT NULL.
     * Ensure it is never null even if user leaves it empty.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['rendered_html'] = $data['rendered_html'] ?? '';
        return $data;
    }
}
