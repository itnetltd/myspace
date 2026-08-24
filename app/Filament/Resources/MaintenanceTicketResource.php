<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceTicketResource\Pages;
use App\Models\MaintenanceTicket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceTicketResource extends Resource
{
    protected static ?string $model = MaintenanceTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'MySpaces Estate';
    protected static ?string $navigationLabel = 'Maintenance Tickets';

    public static function form(Form $form): Form
    {
        // ✅ Prefill when coming from Lease action:
        // /admin/maintenance-tickets/create?unit_id=1&lease_id=2
        $unitIdFromUrl = request()->get('unit_id');
        $leaseIdFromUrl = request()->get('lease_id');

        return $form->schema([
            Forms\Components\Section::make('Ticket Details')
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit')
                        ->relationship('unit', 'unit_code')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default($unitIdFromUrl),

                    Forms\Components\Select::make('lease_id')
                        ->label('Lease')
                        ->relationship('lease', 'id')
                        ->searchable()
                        ->preload()
                        ->default($leaseIdFromUrl)
                        ->placeholder('Optional (link to lease)')
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            // Lease record
                            $record->loadMissing(['unit', 'tenant']);
                            $unit = $record->unit?->unit_code ?? 'Unit';
                            $tenant = $record->tenant?->full_name ?? 'Tenant';
                            return "{$unit} — {$tenant} (Lease #{$record->id})";
                        }),

                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('category')
                        ->options([
                            'plumbing' => 'Plumbing',
                            'electrical' => 'Electrical',
                            'internet' => 'Internet',
                            'painting' => 'Painting',
                            'appliance' => 'Appliance',
                            'other' => 'Other',
                        ])
                        ->searchable()
                        ->placeholder('Optional'),

                    Forms\Components\Select::make('priority')
                        ->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                            'urgent' => 'Urgent',
                        ])
                        ->required()
                        ->default('medium'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'open' => 'Open',
                            'in_progress' => 'In Progress',
                            'resolved' => 'Resolved',
                            'closed' => 'Closed',
                        ])
                        ->required()
                        ->default('open'),

                    Forms\Components\TextInput::make('reported_by')
                        ->maxLength(255)
                        ->placeholder('Optional (tenant name / agent)'),

                    Forms\Components\DatePicker::make('reported_on')
                        ->default(now())
                        ->required(),

                    Forms\Components\DatePicker::make('resolved_on'),

                    Forms\Components\FileUpload::make('photo_path')
                        ->directory('maintenance')
                        ->image()
                        ->maxSize(4096),

                    Forms\Components\Textarea::make('description')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('estimated_cost')
                        ->numeric()
                        ->prefix('RWF'),

                    Forms\Components\TextInput::make('actual_cost')
                        ->numeric()
                        ->prefix('RWF'),

                    Forms\Components\Textarea::make('internal_notes')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_no')
                    ->label('Ticket')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit.unit_code')
                    ->label('Unit')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('lease.tenant.full_name')
                    ->label('Tenant')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('title')
                    ->searchable(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'gray',
                        'low' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('reported_on')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('resolved_on')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceTickets::route('/'),
            'create' => Pages\CreateMaintenanceTicket::route('/create'),
            'edit' => Pages\EditMaintenanceTicket::route('/{record}/edit'),
        ];
    }
}