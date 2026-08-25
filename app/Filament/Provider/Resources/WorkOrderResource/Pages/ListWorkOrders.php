<?php

namespace App\Filament\Provider\Resources\WorkOrderResource\Pages;

use App\Filament\Provider\Resources\WorkOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;
}
