<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\QuotationResource\Pages;
use App\Models\ProviderInvitation;
use App\Models\Quotation;
use App\Models\ServiceRequestLine;
use App\Models\SupplierProduct;
use App\Services\QuotationService;
use App\Support\CurrentProviderCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('service_request_id')->label('Invited request')->required()->searchable()
                ->options(fn () => ProviderInvitation::where('provider_company_id', app(CurrentProviderCompany::class)->id())
                    ->whereIn('status', [ProviderInvitation::STATUS_INVITED, ProviderInvitation::STATUS_VIEWED])
                    ->whereHas('serviceRequest', fn ($query) => $query->withoutGlobalScopes()->whereIn('status', [
                        \App\Models\ServiceRequest::STATUS_REQUESTED, \App\Models\ServiceRequest::STATUS_QUOTES_RECEIVED,
                    ]))
                    ->with(['serviceRequest' => fn ($query) => $query->withoutGlobalScopes()])
                    ->get()->mapWithKeys(fn ($invitation) => [$invitation->service_request_id => $invitation->serviceRequest?->request_number.' — '.$invitation->serviceRequest?->title])),
            Forms\Components\TextInput::make('currency')->default('RWF')->required()->maxLength(3),
            Forms\Components\TextInput::make('delivery_amount')->numeric()->default(0),
            Forms\Components\DatePicker::make('valid_until'), Forms\Components\DatePicker::make('estimated_start_date'),
            Forms\Components\DatePicker::make('estimated_completion_date'), Forms\Components\Textarea::make('warranty_notes'),
            Forms\Components\Textarea::make('terms'), Forms\Components\Textarea::make('notes'),
            Forms\Components\Repeater::make('lines')->schema([
                Forms\Components\Select::make('service_request_line_id')->label('Requested line')->searchable()
                    ->options(fn () => ServiceRequestLine::query()
                        ->whereIn('service_request_id', ProviderInvitation::where('provider_company_id', app(CurrentProviderCompany::class)->id())->pluck('service_request_id'))
                        ->pluck('description', 'id')),
                Forms\Components\Select::make('supplier_product_id')->label('Catalog product')->searchable()
                    ->options(fn () => SupplierProduct::where('is_active', true)->pluck('name', 'id')),
                Forms\Components\TextInput::make('description')->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->default(1)->required(),
                Forms\Components\TextInput::make('unit_price')->numeric()->required(),
                Forms\Components\TextInput::make('tax_amount')->numeric()->default(0),
                Forms\Components\TextInput::make('discount_amount')->numeric()->default(0),
                Forms\Components\Select::make('availability_status')->options(array_combine(SupplierProduct::STOCK_STATUSES, SupplierProduct::STOCK_STATUSES)),
                Forms\Components\TextInput::make('delivery_days')->numeric(), Forms\Components\TextInput::make('warranty_months')->numeric(),
                Forms\Components\Toggle::make('is_alternative')->live(),
                Forms\Components\Textarea::make('alternative_reason')->visible(fn (Forms\Get $get) => (bool) $get('is_alternative')),
                Forms\Components\Textarea::make('notes'),
            ])->defaultItems(1)->columns(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('quotation_number')->searchable(),
            Tables\Columns\TextColumn::make('serviceRequest.request_number')->label('Request'),
            Tables\Columns\TextColumn::make('total_amount')->money(fn ($record) => $record->currency),
            Tables\Columns\TextColumn::make('status')->badge(), Tables\Columns\TextColumn::make('valid_until')->date(),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn (Quotation $record) => $record->status === Quotation::STATUS_DRAFT),
            Tables\Actions\Action::make('submit')->requiresConfirmation()
                ->visible(fn (Quotation $record) => $record->status === Quotation::STATUS_DRAFT
                    && in_array($record->serviceRequest?->status, [
                        \App\Models\ServiceRequest::STATUS_REQUESTED, \App\Models\ServiceRequest::STATUS_QUOTES_RECEIVED,
                    ], true))
                ->action(fn (Quotation $record) => app(QuotationService::class)->submit($record, auth()->user())),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListQuotations::route('/'), 'create' => Pages\CreateQuotation::route('/create'), 'edit' => Pages\EditQuotation::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['serviceRequest' => fn ($query) => $query->withoutGlobalScopes()]);
    }

    public static function canCreate(): bool
    {
        return app(CurrentProviderCompany::class)->company()?->isActive() === true
            && parent::canCreate();
    }
}
