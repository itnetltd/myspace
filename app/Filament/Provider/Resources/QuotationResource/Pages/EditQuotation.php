<?php

namespace App\Filament\Provider\Resources\QuotationResource\Pages;

use App\Filament\Provider\Resources\QuotationResource;
use App\Models\ServiceRequest;
use App\Services\QuotationService;
use App\Support\CurrentProviderCompany;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQuotation extends EditRecord
{
    protected static string $resource = QuotationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->record->lines->map(fn ($line) => $line->only([
            'service_request_line_id', 'supplier_product_id', 'description', 'quantity', 'unit_price',
            'tax_amount', 'discount_amount', 'is_alternative', 'alternative_reason',
            'availability_status', 'delivery_days', 'warranty_months', 'notes',
        ]))->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines']);
        $request = ServiceRequest::withoutGlobalScopes()->findOrFail($record->service_request_id);

        return app(QuotationService::class)->saveDraft($request, app(CurrentProviderCompany::class)->company(), $data, $lines, auth()->user());
    }
}
