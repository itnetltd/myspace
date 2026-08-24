<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitAssetResource\Pages;
use App\Models\UnitAsset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitAssetResource extends Resource
{
    protected static ?string $model = UnitAsset::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'MySpaces Estate';

    public static function form(Form $form): Form
    {
        $conditions = ['Excellent','Good','Fair','Damaged','Missing'];

        return $form->schema([
            Forms\Components\Section::make('Unit Asset (Inventory)')
                ->schema([
                    Forms\Components\Select::make('unit_id')
                        ->relationship('unit', 'unit_code') // adjust if your Unit label field differs
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('asset_item_id')
                        ->relationship('assetItem', 'name')
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('quantity')->numeric()->minValue(1)->default(1)->required(),

                    Forms\Components\Select::make('condition_status')
                        ->options(array_combine($conditions, $conditions))
                        ->required()
                        ->default('Good'),

                    Forms\Components\FileUpload::make('photo_path')
                        ->directory('unit-assets')
                        ->image()
                        ->maxSize(4096),

                    Forms\Components\Textarea::make('notes')->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                Tables\Columns\TextColumn::make('unit.unit_code')->label('Unit')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('assetItem.name')->label('Asset')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\BadgeColumn::make('condition_status')->sortable(),
                Tables\Columns\ImageColumn::make('photo_path')->label('Photo')->circular(),
                Tables\Columns\TextColumn::make('updated_at')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnitAssets::route('/'),
            'create' => Pages\CreateUnitAsset::route('/create'),
            'edit' => Pages\EditUnitAsset::route('/{record}/edit'),
        ];
    }
}