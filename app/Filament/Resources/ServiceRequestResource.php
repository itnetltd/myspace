<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceRequestResource\Pages;
use App\Models\AssetItem;
use App\Models\ProviderCompany;
use App\Models\ServiceRequest;
use App\Services\ServiceRequestService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Marketplace / Services';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('request_type')->options(array_combine(ServiceRequest::TYPES, ServiceRequest::TYPES))->required(),
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->required()->columnSpanFull(),
            Forms\Components\Select::make('priority')->options(array_combine(ServiceRequest::PRIORITIES, ServiceRequest::PRIORITIES))->default('normal')->required(),
            Forms\Components\DatePicker::make('required_by'),
            Forms\Components\Select::make('property_owner_id')->relationship('propertyOwner', 'name')->searchable()->preload(),
            Forms\Components\Select::make('property_id')->relationship('property', 'name')->searchable()->preload(),
            Forms\Components\Select::make('unit_id')->relationship('unit', 'unit_code')->searchable()->preload(),
            Forms\Components\Select::make('lease_id')->relationship('lease', 'id')->searchable()->preload(),
            Forms\Components\Select::make('maintenance_ticket_id')->relationship('maintenanceTicket', 'ticket_no')->searchable()->preload(),
            Forms\Components\Select::make('inspection_id')->relationship('inspection', 'id')->searchable()->preload(),
            Forms\Components\Repeater::make('lines')->schema([
                Forms\Components\Select::make('asset_item_id')->options(fn () => AssetItem::pluck('name', 'id'))->searchable()->preload(),
                Forms\Components\Textarea::make('description')->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->default(1)->required(),
                Forms\Components\TextInput::make('unit'),
                Forms\Components\TextInput::make('requested_brand'),
                Forms\Components\TextInput::make('requested_model'),
                Forms\Components\Textarea::make('specification'),
                Forms\Components\FileUpload::make('photo_path')->directory('private/service-requests'),
                Forms\Components\Toggle::make('allow_alternative'),
                Forms\Components\Textarea::make('notes'),
            ])->defaultItems(1)->columns(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('request_number')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('request_type')->badge(),
            Tables\Columns\TextColumn::make('title')->searchable(),
            Tables\Columns\TextColumn::make('priority')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('quotations_count')->counts('quotations')->label('Quotes'),
        ])->actions([
            Tables\Actions\Action::make('inviteProviders')
                ->form([
                    Forms\Components\Select::make('providers')->multiple()->required()->searchable()
                        ->options(fn () => ProviderCompany::where('status', ProviderCompany::STATUS_ACTIVE)->pluck('name', 'id')),
                    Forms\Components\DateTimePicker::make('expires_at'),
                ])
                ->visible(fn (ServiceRequest $record) => auth()->user()->can('update', $record)
                    && in_array($record->status, [ServiceRequest::STATUS_DRAFT, ServiceRequest::STATUS_REQUESTED], true))
                ->action(fn (ServiceRequest $record, array $data) => app(ServiceRequestService::class)
                    ->invite($record, $data['providers'], auth()->user(), $data['expires_at'] ?? null)),
            Tables\Actions\Action::make('recordOwnerApproval')
                ->form([Forms\Components\Textarea::make('reference')->required()])
                ->visible(fn (ServiceRequest $record) => auth()->user()->can('update', $record)
                    && $record->owner_approval_required && ! $record->owner_approved_at)
                ->action(fn (ServiceRequest $record, array $data) => app(ServiceRequestService::class)
                    ->recordOwnerApproval($record, auth()->user(), $data['reference'])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListServiceRequests::route('/'), 'create' => Pages\CreateServiceRequest::route('/create')];
    }
}
