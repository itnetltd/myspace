<?php

namespace App\Filament\Resources\OwnerStatementResource\Pages;

use App\Filament\Resources\OwnerStatementResource;
use App\Models\OwnerStatement;
use App\Services\OwnerStatementService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewOwnerStatement extends ViewRecord
{
    protected static string $resource = OwnerStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('finalize')
                ->icon('heroicon-o-lock-closed')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === OwnerStatement::STATUS_DRAFT
                    && Gate::allows('finalize', $this->record))
                ->action(function () {
                    Gate::authorize('finalize', $this->record);
                    $this->record = app(OwnerStatementService::class)->finalize($this->record, auth()->user());
                }),
            Actions\Action::make('pdf')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => route('reports.owner-statement', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
