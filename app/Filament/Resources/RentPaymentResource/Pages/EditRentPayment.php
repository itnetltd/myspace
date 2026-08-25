<?php

namespace App\Filament\Resources\RentPaymentResource\Pages;

use App\Filament\Resources\RentPaymentResource;
use App\Services\RentPaymentService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRentPayment extends EditRecord
{
    protected static string $resource = RentPaymentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(RentPaymentService::class)->update($record, $data);
    }
}
