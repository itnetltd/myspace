<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderInvoiceResource\Pages;
use App\Models\ProviderInvoice;
use App\Services\ProviderInvoiceService;
use App\Support\CurrentAccount;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProviderInvoiceResource extends Resource
{
    protected static ?string $model = ProviderInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Marketplace / Services';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('invoice_number')->searchable(),
            Tables\Columns\TextColumn::make('providerCompany.name')->label('Provider'),
            Tables\Columns\TextColumn::make('serviceRequest.request_number')->label('Request'),
            Tables\Columns\TextColumn::make('total_amount')->money(fn ($record) => $record->currency),
            Tables\Columns\TextColumn::make('invoice_date')->date(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\IconColumn::make('variation_approved_at')->boolean()->label('Variation approved'),
        ])->actions([
            Tables\Actions\Action::make('approve')
                ->form([Forms\Components\Toggle::make('approve_variation')->helperText('Required when invoice exceeds the accepted quotation.')])
                ->visible(fn (ProviderInvoice $record) => $record->status === ProviderInvoice::STATUS_SUBMITTED && auth()->user()->can('approve', $record))
                ->action(fn (ProviderInvoice $record, array $data) => app(ProviderInvoiceService::class)
                    ->approve($record, auth()->user(), (bool) ($data['approve_variation'] ?? false))),
            Tables\Actions\Action::make('postAsExpense')->requiresConfirmation()
                ->visible(fn (ProviderInvoice $record) => $record->status === ProviderInvoice::STATUS_APPROVED && auth()->user()->can('post', $record))
                ->action(fn (ProviderInvoice $record) => app(ProviderInvoiceService::class)->postAsExpense($record, auth()->user())),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()->where('account_id', app(CurrentAccount::class)->id());
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProviderInvoices::route('/')];
    }
}
