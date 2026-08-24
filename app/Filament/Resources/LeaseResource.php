<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaseResource\Pages;
use App\Filament\Resources\LeaseResource\RelationManagers\InspectionsRelationManager;
use App\Filament\Resources\LeaseResource\RelationManagers\MaintenanceTicketsRelationManager;
use App\Filament\Resources\LeaseResource\RelationManagers\RentInvoicesRelationManager;
use App\Models\Lease;
use App\Models\Inspection;
use App\Models\UnitAsset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeaseResource extends Resource
{
    protected static ?string $model = Lease::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'MySpaces Estate';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Lease Details')
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->relationship('unit', 'unit_code')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->label('Unit'),

                    // ✅ Tenant select + inline create (kept exactly)
                    Forms\Components\Select::make('tenant_id')
                        ->relationship('tenant', 'full_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->label('Tenant')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('full_name')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('phone')
                                ->tel()
                                ->maxLength(30),

                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('id_number')
                                ->label('National ID (Protected)')
                                ->password()
                                ->required()
                                ->maxLength(50)
                                ->helperText('Stored for security. Keep private.'),

                            Forms\Components\TextInput::make('emergency_contact_name')
                                ->maxLength(255),

                            Forms\Components\TextInput::make('emergency_contact_phone')
                                ->tel()
                                ->maxLength(30),

                            Forms\Components\Textarea::make('notes')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\DatePicker::make('start_date')->required(),
                    Forms\Components\DatePicker::make('end_date'),

                    Forms\Components\TextInput::make('monthly_rent')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('RWF')
                        ->required(),

                    Forms\Components\TextInput::make('deposit')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('RWF')
                        ->default(0),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'active' => 'Active',
                            'ended' => 'Ended',
                        ])
                        ->required()
                        ->default('draft'),

                    Forms\Components\Textarea::make('notes')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unit.unit_code')
                    ->label('Unit')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tenant.full_name')
                    ->label('Tenant')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date()->sortable(),

                Tables\Columns\TextColumn::make('monthly_rent')->money('RWF')->sortable(),
                Tables\Columns\TextColumn::make('deposit')->money('RWF')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'ended' => 'Ended',
                    ]),
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'unit_code')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'full_name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // Inspections filtered by this lease
                Tables\Actions\Action::make('inspections')
                    ->label('Inspections')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (Lease $record) => route('filament.admin.resources.inspections.index', [
                        'tableFilters[lease_id][value]' => $record->id,
                    ]))
                    ->openUrlInNewTab(),

                // Rent invoices filtered by this lease
                Tables\Actions\Action::make('invoices')
                    ->label('Invoices')
                    ->icon('heroicon-o-receipt-percent')
                    ->url(fn (Lease $record) => route('filament.admin.resources.rent-invoices.index', [
                        'tableFilters[lease_id][value]' => $record->id,
                    ]))
                    ->openUrlInNewTab(),

                // Maintenance filtered by this lease
                Tables\Actions\Action::make('maintenance')
                    ->label('Maintenance')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->url(fn (Lease $record) => route('filament.admin.resources.maintenance-tickets.index', [
                        'tableFilters[lease_id][value]' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * ✅ Tabs on Edit Lease page
     */
    public static function getRelations(): array
    {
        return [
            RentInvoicesRelationManager::class,
            MaintenanceTicketsRelationManager::class,
            InspectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeases::route('/'),
            'create' => Pages\CreateLease::route('/create'),
            'edit' => Pages\EditLease::route('/{record}/edit'),
        ];
    }

    /**
     * Helper: build inspection lines from Unit Assets
     */
    public static function buildInspectionLinesFromUnit(int $unitId): array
    {
        $unitAssets = UnitAsset::query()
            ->where('unit_id', $unitId)
            ->get();

        return $unitAssets->map(function ($ua) {
            $expected = (int) $ua->quantity;

            return [
                'asset_item_id' => $ua->asset_item_id,
                'expected_qty' => $expected,
                'found_qty' => $expected,
                'condition_status' => $ua->condition_status ?? 'Good',
                'issue_type' => 'none',
                'remarks' => null,
                'evidence_photo_path' => null,
                'deduction_override' => null,
                'deduction_reason' => null,
            ];
        })->values()->all();
    }

    /**
     * Helper: create inspection for lease
     */
    public static function createInspectionForLease(Lease $lease, string $type): Inspection
    {
        $inspection = Inspection::create([
            'unit_id' => $lease->unit_id,
            'lease_id' => $lease->id,
            'type' => $type,
            'inspected_on' => now()->toDateString(),
            'inspected_by' => 'Property Manager',
        ]);

        $lines = static::buildInspectionLinesFromUnit($lease->unit_id);

        foreach ($lines as $line) {
            $inspection->lines()->create($line);
        }

        return $inspection;
    }
}