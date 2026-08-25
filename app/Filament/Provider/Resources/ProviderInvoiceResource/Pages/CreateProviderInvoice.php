<?php

namespace App\Filament\Provider\Resources\ProviderInvoiceResource\Pages;

use App\Filament\Provider\Resources\ProviderInvoiceResource;
use App\Models\Quotation;
use App\Services\ProviderInvoiceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProviderInvoice extends CreateRecord
{
    protected static string $resource = ProviderInvoiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = $data['lines'] ?? null;
        unset($data['lines']);
        if ($lines === []) {
            $lines = null;
        }

        return app(ProviderInvoiceService::class)->saveDraft(
            Quotation::withoutGlobalScopes()->findOrFail($data['quotation_id']), $data, $lines, auth()->user(),
        );
    }
}
