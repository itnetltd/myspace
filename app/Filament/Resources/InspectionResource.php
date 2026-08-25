<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InspectionResource\Pages;
use App\Models\Inspection;
use App\Models\UnitAsset;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Get;
// Form button imports
use Filament\Resources\Resource;
use Filament\Tables;
// For conditional visibility in forms
use Filament\Tables\Actions\Action as TableAction;
// Table action alias
use Filament\Tables\Filters\SelectFilter;
// ✅ Filters
use Filament\Tables\Table;

class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'MySpaces Estate';

    public static function form(Form $form): Form
    {
        $types = [
            'move_in' => 'Move-in',
            'move_out' => 'Move-out',
            'routine' => 'Routine',
            'maintenance' => 'Maintenance',
        ];

        $conditions = ['Excellent', 'Good', 'Fair', 'Damaged', 'Missing'];
        $issueTypes = ['none' => 'None', 'damaged' => 'Damaged', 'missing' => 'Missing', 'other' => 'Other'];

        return $form->schema([
            Forms\Components\Section::make('Inspection Details')
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->relationship('unit', 'unit_code') // adjust if needed
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Auto-fill lines ONLY if empty (do not overwrite user input)
                            $existingLines = $get('lines') ?? [];
                            if (! empty($existingLines)) {
                                return;
                            }

                            if (! $state) {
                                $set('lines', []);

                                return;
                            }

                            $unitAssets = UnitAsset::query()
                                ->where('unit_id', $state)
                                ->with('assetItem:id,name')
                                ->get();

                            $lines = $unitAssets->map(function ($ua) {
                                $expected = (int) $ua->quantity;

                                return [
                                    'asset_item_id' => $ua->asset_item_id,
                                    'expected_qty' => $expected,
                                    'found_qty' => $expected,
                                    'condition_status' => $ua->condition_status ?? 'Good',
                                    'issue_type' => 'none',

                                    // Manual override fields
                                    'deduction_override' => null,
                                    'deduction_reason' => null,

                                    'remarks' => null,
                                    'evidence_photo_path' => null,
                                ];
                            })->values()->all();

                            $set('lines', $lines);
                        }),

                    /**
                     * ✅ FIXED: lease dropdown label must NEVER be null
                     * We base relationship on 'id' then build a safe label:
                     * "Tenant Name (UNITCODE)" OR "Lease #ID (UNITCODE)"
                     */
                    Forms\Components\Select::make('lease_id')
                        ->relationship('lease', 'id')
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            $tenant = (string) ($record->tenant_name ?? '');
                            $unit = (string) ($record->unit?->unit_code ?? '');

                            $label = trim($tenant) !== '' ? $tenant : ('Lease #'.$record->id);

                            return $unit !== '' ? "{$label} ({$unit})" : $label;
                        })
                        ->searchable()
                        ->placeholder('Optional (link to lease)'),

                    Forms\Components\Select::make('type')
                        ->options($types)
                        ->required(),

                    Forms\Components\DatePicker::make('inspected_on')->required(),
                    Forms\Components\TextInput::make('inspected_by')->maxLength(255),
                    Forms\Components\TextInput::make('summary_status')->maxLength(255),
                    Forms\Components\Textarea::make('general_notes')->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Inspection Lines (Assets)')
                ->schema([
                    // Manual button to load inventory anytime
                    Actions::make([
                        Action::make('loadFromInventory')
                            ->label('Load from Unit Inventory')
                            ->icon('heroicon-o-arrow-path')
                            ->action(function (callable $set, callable $get) {
                                $unitId = $get('unit_id');

                                if (! $unitId) {
                                    return;
                                }

                                $unitAssets = UnitAsset::query()
                                    ->where('unit_id', $unitId)
                                    ->with('assetItem:id,name')
                                    ->get();

                                $lines = $unitAssets->map(function ($ua) {
                                    $expected = (int) $ua->quantity;

                                    return [
                                        'asset_item_id' => $ua->asset_item_id,
                                        'expected_qty' => $expected,
                                        'found_qty' => $expected,
                                        'condition_status' => $ua->condition_status ?? 'Good',
                                        'issue_type' => 'none',

                                        // Manual override fields
                                        'deduction_override' => null,
                                        'deduction_reason' => null,

                                        'remarks' => null,
                                        'evidence_photo_path' => null,
                                    ];
                                })->values()->all();

                                $set('lines', $lines);
                            }),
                    ]),

                    Forms\Components\Repeater::make('lines')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('asset_item_id')
                                ->relationship('assetItem', 'name')
                                ->searchable()
                                ->required(),

                            Forms\Components\TextInput::make('expected_qty')
                                ->numeric()
                                ->minValue(0)
                                ->default(1)
                                ->required(),

                            // Auto-detect missing if found < expected
                            Forms\Components\TextInput::make('found_qty')
                                ->numeric()
                                ->minValue(0)
                                ->default(1)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $expected = (int) ($get('expected_qty') ?? 0);
                                    $found = (int) ($state ?? 0);

                                    if ($found < $expected) {
                                        $set('issue_type', 'missing');
                                    } elseif (($get('issue_type') ?? 'none') === 'missing') {
                                        $set('issue_type', 'none');
                                    }
                                })
                                ->helperText(function (callable $get) {
                                    $expected = (int) ($get('expected_qty') ?? 0);
                                    $found = (int) ($get('found_qty') ?? 0);

                                    if ($found < $expected) {
                                        return "Mismatch: expected {$expected}, found {$found}.";
                                    }

                                    return null;
                                }),

                            Forms\Components\Select::make('condition_status')
                                ->options(array_combine($conditions, $conditions))
                                ->required()
                                ->default('Good'),

                            Forms\Components\Select::make('issue_type')
                                ->options($issueTypes)
                                ->required()
                                ->default('none'),

                            // Manual deduction override fields (show only for move_out to keep UI clean)
                            Forms\Components\TextInput::make('deduction_override')
                                ->label('Manual Deduction (RWF)')
                                ->numeric()
                                ->minValue(0)
                                ->helperText('Leave empty to use suggested deduction.')
                                ->visible(fn (Get $get) => $get('../../type') === 'move_out'),

                            Forms\Components\TextInput::make('deduction_reason')
                                ->label('Override Reason')
                                ->maxLength(255)
                                ->placeholder('e.g., agreed replacement cost, partial damage, etc.')
                                ->visible(fn (Get $get) => $get('../../type') === 'move_out'),

                            Forms\Components\FileUpload::make('evidence_photo_path')
                                ->directory('inspection-evidence')
                                ->image()
                                ->maxSize(4096),

                            Forms\Components\Textarea::make('remarks')->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Add Asset Line'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('unit.unit_code')->label('Unit')->sortable()->searchable(),

            // Better label instead of move_in/move_out
            Tables\Columns\TextColumn::make('type')
                ->label('Type')
                ->badge()
                ->formatStateUsing(fn ($state) => match ($state) {
                    'move_in' => 'Move-in',
                    'move_out' => 'Move-out',
                    'routine' => 'Routine',
                    'maintenance' => 'Maintenance',
                    default => $state,
                })
                ->sortable(),

            // Issues count badge (missing/damaged)
            Tables\Columns\TextColumn::make('issues_count')
                ->label('Issues')
                ->badge()
                ->getStateUsing(function (Inspection $record) {
                    $record->loadMissing('lines');

                    return $record->lines->filter(function ($l) {
                        $expected = (int) ($l->expected_qty ?? 0);
                        $found = (int) ($l->found_qty ?? 0);

                        return ($found < $expected)
                            || (($l->issue_type ?? '') === 'damaged')
                            || (($l->condition_status ?? '') === 'Damaged');
                    })->count();
                }),

            Tables\Columns\TextColumn::make('inspected_on')->date()->sortable(),
            Tables\Columns\TextColumn::make('inspected_by')->toggleable(),

            Tables\Columns\TextColumn::make('lease_id')
                ->label('Lease')
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])
            // ✅ Filters (Lease + Unit) - FIXED lease label
            ->filters([
                SelectFilter::make('lease_id')
                    ->label('Lease')
                    ->relationship('lease', 'id')
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        $tenant = (string) ($record->tenant_name ?? '');
                        $unit = (string) ($record->unit?->unit_code ?? '');

                        $label = trim($tenant) !== '' ? $tenant : ('Lease #'.$record->id);

                        return $unit !== '' ? "{$label} ({$unit})" : $label;
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'unit_code')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('requestExternalInspection')
                    ->icon('heroicon-o-paper-airplane')
                    ->url(fn (Inspection $record) => ServiceRequestResource::getUrl('create', [
                        'inspection_id' => $record->getKey(),
                    ])),

                // Move-Out PDF button (safe if route exists)
                TableAction::make('moveOutPdf')
                    ->label('Move-Out PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Inspection $record) => route('reports.moveout', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Inspection $record) => $record->type === 'move_out' && \Illuminate\Support\Facades\Route::has('reports.moveout')
                    ),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspections::route('/'),
            'create' => Pages\CreateInspection::route('/create'),
            'edit' => Pages\EditInspection::route('/{record}/edit'),
        ];
    }
}
