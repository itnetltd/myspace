<?php

namespace App\Filament\Resources\PropertyExpenseResource\Pages;

use App\Filament\Resources\PropertyExpenseResource;
use App\Models\MaintenanceTicket;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePropertyExpense extends CreateRecord
{
    protected static string $resource = PropertyExpenseResource::class;

    public function mount(): void
    {
        parent::mount();

        $ticket = request()->integer('maintenance_ticket_id')
            ? MaintenanceTicket::query()->with('unit.property')->find(request()->integer('maintenance_ticket_id'))
            : null;

        if ($ticket) {
            $this->form->fill([
                'maintenance_ticket_id' => $ticket->id,
                'property_owner_id' => $ticket->unit->property->property_owner_id,
                'property_id' => $ticket->unit->property_id,
                'unit_id' => $ticket->unit_id,
                'lease_id' => $ticket->lease_id,
                'category' => 'maintenance',
                'amount' => $ticket->actual_cost,
                'occurred_on' => $ticket->resolved_on?->toDateString() ?? now()->toDateString(),
                'description' => $ticket->title.($ticket->description ? ': '.$ticket->description : ''),
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (PropertyExpenseResource::maintenanceOnlyUser()) {
            $data['category'] = 'maintenance';

            if (empty($data['maintenance_ticket_id'])) {
                throw ValidationException::withMessages([
                    'data.maintenance_ticket_id' => 'Maintenance staff must create expenses from a maintenance ticket.',
                ]);
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(fn () => static::getModel()::create($data));
    }
}
