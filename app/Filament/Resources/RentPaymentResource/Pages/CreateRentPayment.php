<?php

namespace App\Filament\Resources\RentPaymentResource\Pages;

use App\Filament\Resources\RentPaymentResource;
use App\Services\RentPaymentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRentPayment extends CreateRecord
{
    protected static string $resource = RentPaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(RentPaymentService::class)->create($data);
    }
}
