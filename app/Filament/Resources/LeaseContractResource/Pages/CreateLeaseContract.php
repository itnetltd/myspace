<?php

namespace App\Filament\Resources\LeaseContractResource\Pages;

use App\Filament\Resources\LeaseContractResource;
use App\Models\ContractTemplate;
use App\Models\Lease;
use App\Services\ContractRenderService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

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
        $leaseId = (int) ($data['lease_id'] ?? request()->integer('lease_id'));
        $templateId = (int) ($data['contract_template_id'] ?? request()->integer('contract_template_id'));

        $lease = Lease::query()->find($leaseId);
        $template = ContractTemplate::query()->find($templateId);

        if (! $lease) {
            throw ValidationException::withMessages([
                'data.lease_id' => 'Please select a valid lease.',
            ]);
        }

        if (! $template) {
            throw ValidationException::withMessages([
                'data.contract_template_id' => 'Please select a valid contract template.',
            ]);
        }

        $data['lease_id'] = $lease->getKey();
        $data['contract_template_id'] = $template->getKey();
        $data['language'] = $template->language;
        $data['status'] ??= 'draft';
        $data['rendered_html'] = filled($data['rendered_html'] ?? null)
            ? $data['rendered_html']
            : app(ContractRenderService::class)->render($lease, $template);

        return $data;
    }
}
