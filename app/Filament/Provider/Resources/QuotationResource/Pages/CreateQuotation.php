<?php

namespace App\Filament\Provider\Resources\QuotationResource\Pages;

use App\Filament\Provider\Resources\QuotationResource;
use App\Models\ServiceRequest;
use App\Services\QuotationService;
use App\Support\CurrentProviderCompany;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    public function mount(): void
    {
        parent::mount();
        if (request()->filled('service_request_id')) {
            $this->form->fill(['service_request_id' => request()->integer('service_request_id')]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines']);
        $request = ServiceRequest::withoutGlobalScopes()->findOrFail($data['service_request_id']);

        return app(QuotationService::class)->saveDraft($request, app(CurrentProviderCompany::class)->company(), $data, $lines, auth()->user());
    }
}
