<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaseContractResource\Pages;
use App\Models\LeaseContract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeaseContractResource extends Resource
{
    protected static ?string $model = LeaseContract::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contract Details')
                ->schema([
                    Forms\Components\Select::make('lease_id')
                        ->label('Lease')
                        ->relationship('lease', 'id')
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            return data_get($record, 'reference')
                                ?? data_get($record, 'code')
                                ?? data_get($record, 'number')
                                ?? data_get($record, 'title')
                                ?? data_get($record, 'name')
                                ?? ('Lease #' . $record->id);
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('contract_template_id')
                        ->label('Contract Template')
                        ->relationship('template', 'id')
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            return data_get($record, 'name')
                                ?? data_get($record, 'title')
                                ?? ('Template #' . $record->id);
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('language')
                        ->options([
                            'en' => 'English',
                            'fr' => 'French',
                            'rw' => 'Kinyarwanda',
                        ])
                        ->default('en')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'sent' => 'Sent',
                            'signed' => 'Signed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('draft')
                        ->required(),

                    Forms\Components\DatePicker::make('signed_on')
                        ->label('Signed On')
                        ->nullable(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Rendered Contract')
                ->schema([
                    Forms\Components\Textarea::make('rendered_html')
                        ->label('Rendered HTML')
                        ->rows(10)
                        ->helperText('Optional: store the generated contract HTML here.')
                        /**
                         * FIX:
                         * DB column is NOT NULL, so never store null.
                         * If user leaves it empty, save "".
                         */
                        ->default('')
                        ->dehydrateStateUsing(fn ($state) => $state ?? '')
                        ->required(false), // optional in UI, but required for DB safety
                ]),

            Forms\Components\Section::make('Signatures')
                ->schema([
                    Forms\Components\FileUpload::make('landlord_signature_path')
                        ->label('Landlord Signature')
                        ->directory('signatures')
                        ->preserveFilenames()
                        ->image()
                        ->nullable(),

                    Forms\Components\FileUpload::make('tenant_signature_path')
                        ->label('Tenant Signature')
                        ->directory('signatures')
                        ->preserveFilenames()
                        ->image()
                        ->nullable(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('lease_id')
                    ->label('Lease')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $record->lease) {
                            return $state ? ('Lease #' . $state) : '-';
                        }

                        return data_get($record->lease, 'reference')
                            ?? data_get($record->lease, 'code')
                            ?? data_get($record->lease, 'number')
                            ?? data_get($record->lease, 'title')
                            ?? data_get($record->lease, 'name')
                            ?? ('Lease #' . $record->lease->id);
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contract_template_id')
                    ->label('Template')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $record->template) {
                            return $state ? ('Template #' . $state) : '-';
                        }

                        return data_get($record->template, 'name')
                            ?? data_get($record->template, 'title')
                            ?? ('Template #' . $record->template->id);
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('language')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('signed_on')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'signed' => 'Signed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Keep empty for now
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaseContracts::route('/'),
            'create' => Pages\CreateLeaseContract::route('/create'),
            'edit' => Pages\EditLeaseContract::route('/{record}/edit'),
        ];
    }
}
