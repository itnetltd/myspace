<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Models\Quotation;
use App\Services\QuotationAcceptanceService;
use App\Services\ServiceRequestService;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Marketplace / Services';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('serviceRequest.request_number')->label('Request')->sortable(),
            Tables\Columns\TextColumn::make('providerCompany.name')->label('Provider')->searchable(),
            Tables\Columns\TextColumn::make('total_amount')->money(fn ($record) => $record->currency)->sortable(),
            Tables\Columns\TextColumn::make('estimated_start_date')->date(),
            Tables\Columns\TextColumn::make('estimated_completion_date')->date(),
            Tables\Columns\TextColumn::make('warranty_notes')->wrap(),
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->actions([
            Tables\Actions\Action::make('recordOwnerApproval')
                ->label('Record owner approval')
                ->form([Forms\Components\Textarea::make('reference')->required()])
                ->visible(fn (Quotation $record) => $record->status === Quotation::STATUS_SUBMITTED
                    && app(AccountAccess::class)->can(
                        auth()->user(), app(CurrentAccount::class)->account(), AccountAccess::RECORD_MARKETPLACE_OWNER_APPROVAL,
                    ))
                ->action(fn (Quotation $record, array $data) => app(ServiceRequestService::class)
                    ->recordOwnerApproval($record->serviceRequest()->withoutGlobalScopes()->firstOrFail(), $record, auth()->user(), $data['reference'])),
            Tables\Actions\Action::make('accept')->requiresConfirmation()
                ->visible(fn (Quotation $record) => $record->status === Quotation::STATUS_SUBMITTED
                    && ! $record->valid_until?->lt(today())
                    && in_array($record->serviceRequest?->status, [\App\Models\ServiceRequest::STATUS_REQUESTED, \App\Models\ServiceRequest::STATUS_QUOTES_RECEIVED], true)
                    && auth()->user()->can('accept', $record))
                ->action(fn (Quotation $record) => app(QuotationAcceptanceService::class)->accept($record, auth()->user())),
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()
            ->whereHas('serviceRequest', fn ($query) => $query->withoutGlobalScopes()->where('account_id', app(\App\Support\CurrentAccount::class)->id()));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListQuotations::route('/')];
    }
}
