<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ProviderInvoiceResource\Pages;
use App\Models\ProviderInvoice;
use App\Models\Quotation;
use App\Models\WorkOrder;
use App\Services\ProviderInvoiceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderInvoiceResource extends Resource
{
    protected static ?string $model = ProviderInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'Invoices';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('quotation_id')->label('Accepted quotation')->required()->searchable()
                ->options(fn () => Quotation::where('status', Quotation::STATUS_ACCEPTED)
                    ->whereHas('workOrder', fn ($query) => $query->where('status', WorkOrder::STATUS_COMPLETED))
                    ->pluck('quotation_number', 'id')),
            Forms\Components\DatePicker::make('invoice_date')->default(now())->required(),
            Forms\Components\DatePicker::make('due_date'),
            Forms\Components\TextInput::make('delivery_amount')->numeric()->default(0)
                ->helperText('Leave at zero with no custom lines to copy the accepted quotation delivery charge.'),
            Forms\Components\FileUpload::make('document_path')->directory('private/provider-invoices'),
            Forms\Components\Textarea::make('variation_reason')->helperText('Required if the invoice exceeds the accepted quotation.'),
            Forms\Components\Textarea::make('notes'),
            Forms\Components\Repeater::make('lines')->helperText('Leave empty to copy the accepted quotation exactly.')->schema([
                Forms\Components\TextInput::make('description')->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->default(1)->required(),
                Forms\Components\TextInput::make('unit_price')->numeric()->required(),
                Forms\Components\TextInput::make('tax_amount')->numeric()->default(0),
                Forms\Components\TextInput::make('discount_amount')->numeric()->default(0),
            ])->defaultItems(0)->columns(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('invoice_number')->searchable(),
            Tables\Columns\TextColumn::make('serviceRequest.request_number')->label('Request'),
            Tables\Columns\TextColumn::make('total_amount')->money(fn ($record) => $record->currency),
            Tables\Columns\TextColumn::make('invoice_date')->date(), Tables\Columns\TextColumn::make('status')->badge(),
        ])->actions([
            Tables\Actions\Action::make('submit')->requiresConfirmation()
                ->visible(fn (ProviderInvoice $record) => $record->status === ProviderInvoice::STATUS_DRAFT)
                ->action(fn (ProviderInvoice $record) => app(ProviderInvoiceService::class)->submit($record, auth()->user())),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProviderInvoices::route('/'), 'create' => Pages\CreateProviderInvoice::route('/create')];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['serviceRequest' => fn ($query) => $query->withoutGlobalScopes()]);
    }
}
