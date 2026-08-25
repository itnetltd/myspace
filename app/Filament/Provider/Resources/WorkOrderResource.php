<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\WorkOrderResource\Pages;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('work_order_number')->searchable(),
            Tables\Columns\TextColumn::make('serviceRequest.request_number')->label('Request'),
            Tables\Columns\TextColumn::make('serviceRequest.title')->wrap(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('scheduled_start')->dateTime(),
            Tables\Columns\TextColumn::make('scheduled_completion')->dateTime(),
        ])->actions([
            Tables\Actions\Action::make('schedule')->form([
                Forms\Components\DateTimePicker::make('scheduled_start')->required(),
                Forms\Components\DateTimePicker::make('scheduled_completion')->required(),
            ])->visible(fn (WorkOrder $record) => $record->status === WorkOrder::STATUS_PENDING)
                ->action(fn (WorkOrder $record, array $data) => app(WorkOrderService::class)->transition($record, WorkOrder::STATUS_SCHEDULED, $data, auth()->user())),
            Tables\Actions\Action::make('start')->requiresConfirmation()
                ->visible(fn (WorkOrder $record) => in_array($record->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED], true))
                ->action(fn (WorkOrder $record) => app(WorkOrderService::class)->transition($record, WorkOrder::STATUS_IN_PROGRESS, ['started_at' => now()], auth()->user())),
            Tables\Actions\Action::make('complete')->form([
                Forms\Components\Textarea::make('completion_notes')->required(),
                Forms\Components\FileUpload::make('completion_evidence')->multiple()->directory('private/work-orders'),
            ])->visible(fn (WorkOrder $record) => $record->status === WorkOrder::STATUS_IN_PROGRESS)
                ->action(fn (WorkOrder $record, array $data) => app(WorkOrderService::class)->transition($record, WorkOrder::STATUS_COMPLETED, [
                    'completed_at' => now(), 'completion_notes' => $data['completion_notes'],
                    'completion_evidence' => ['files' => $data['completion_evidence'] ?? []],
                ], auth()->user())),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWorkOrders::route('/')];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['serviceRequest' => fn ($query) => $query->withoutGlobalScopes()]);
    }
}
