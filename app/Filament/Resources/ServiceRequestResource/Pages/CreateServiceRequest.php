<?php

namespace App\Filament\Resources\ServiceRequestResource\Pages;

use App\Filament\Resources\ServiceRequestResource;
use App\Models\AssetItem;
use App\Models\Inspection;
use App\Models\MaintenanceTicket;
use App\Models\ServiceRequest;
use App\Services\ServiceRequestService;
use App\Support\CurrentAccount;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->filled('maintenance_ticket_id')) {
            $ticket = MaintenanceTicket::with('unit.property')->findOrFail(request()->integer('maintenance_ticket_id'));
            $this->form->fill([
                'request_type' => ServiceRequest::TYPE_MAINTENANCE,
                'title' => $ticket->title, 'description' => $ticket->description ?: $ticket->title,
                'priority' => $ticket->priority === 'medium' ? 'normal' : $ticket->priority,
                'property_owner_id' => $ticket->unit->property->property_owner_id,
                'property_id' => $ticket->unit->property_id, 'unit_id' => $ticket->unit_id,
                'lease_id' => $ticket->lease_id, 'maintenance_ticket_id' => $ticket->getKey(),
                'lines' => [['description' => $ticket->description ?: $ticket->title, 'quantity' => 1]],
            ]);
        } elseif (request()->filled('inspection_id')) {
            $inspection = Inspection::with('unit.property')->findOrFail(request()->integer('inspection_id'));
            $this->form->fill([
                'request_type' => ServiceRequest::TYPE_INSPECTION, 'title' => 'External '.$inspection->type.' inspection',
                'description' => 'Perform and report the requested external inspection.', 'priority' => 'normal',
                'property_owner_id' => $inspection->unit->property->property_owner_id,
                'property_id' => $inspection->unit->property_id, 'unit_id' => $inspection->unit_id,
                'lease_id' => $inspection->lease_id, 'inspection_id' => $inspection->getKey(),
                'lines' => [['description' => 'Inspection service, report, and evidence', 'quantity' => 1]],
            ]);
        } elseif (request()->filled('asset_item_id')) {
            $asset = AssetItem::findOrFail(request()->integer('asset_item_id'));
            $this->form->fill([
                'request_type' => ServiceRequest::TYPE_PRODUCT_SUPPLY, 'title' => 'Supply '.$asset->name,
                'description' => 'Request supplier quotations for a replacement or compatible product.', 'priority' => 'normal',
                'lines' => [[
                    'asset_item_id' => $asset->getKey(), 'description' => $asset->name,
                    'requested_brand' => $asset->brand, 'requested_model' => $asset->model,
                    'quantity' => 1, 'allow_alternative' => true,
                ]],
            ]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        return app(ServiceRequestService::class)->create(
            app(CurrentAccount::class)->account(), $data, $lines, auth()->user(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
