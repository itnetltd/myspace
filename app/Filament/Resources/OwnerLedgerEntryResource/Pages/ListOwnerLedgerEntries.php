<?php

namespace App\Filament\Resources\OwnerLedgerEntryResource\Pages;

use App\Filament\Resources\OwnerLedgerEntryResource;
use App\Models\OwnerLedgerEntry;
use App\Models\PropertyOwner;
use App\Services\OwnerLedgerAdjustmentService;
use App\Support\CurrentAccount;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListOwnerLedgerEntries extends ListRecords
{
    protected static string $resource = OwnerLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('adjust')
                ->label('Record Adjustment')
                ->icon('heroicon-o-adjustments-horizontal')
                ->visible(fn () => Gate::allows('adjust', OwnerLedgerEntry::class))
                ->form([
                    Forms\Components\Select::make('property_owner_id')
                        ->options(fn () => PropertyOwner::query()->orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => app(CurrentAccount::class)->account()?->self_property_owner_id)
                        ->searchable()->required(),
                    Forms\Components\Select::make('direction')->options([
                        OwnerLedgerEntry::DIRECTION_CREDIT => 'Credit',
                        OwnerLedgerEntry::DIRECTION_DEBIT => 'Debit',
                    ])->required(),
                    Forms\Components\TextInput::make('amount')->numeric()->minValue(0.01)->required(),
                    Forms\Components\Textarea::make('reason')->required(),
                    Forms\Components\TextInput::make('reference')->maxLength(255),
                ])
                ->action(function (array $data) {
                    Gate::authorize('adjust', OwnerLedgerEntry::class);
                    $owner = PropertyOwner::query()->findOrFail($data['property_owner_id']);
                    app(OwnerLedgerAdjustmentService::class)->record(
                        $owner,
                        (string) $data['amount'],
                        $data['direction'],
                        $data['reason'],
                        $data['reference'] ?? null,
                        auth()->user(),
                    );
                }),
        ];
    }
}
