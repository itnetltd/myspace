<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\SupplyDeliveryResource\Pages;
use App\Models\ProviderCompanyMembership;
use App\Models\SupplyDelivery;
use App\Models\WorkOrder;
use App\Services\SupplyDeliveryService;
use App\Support\CurrentProviderCompany;
use App\Support\ProviderAccess;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplyDeliveryResource extends Resource
{
    protected static ?string $model = SupplyDelivery::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('workOrder.work_order_number')->label('Work order'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('delivery_reference'),
            Tables\Columns\TextColumn::make('scheduled_for')->dateTime(),
            Tables\Columns\TextColumn::make('assignedMembership.user.name')->label('Assigned staff'),
        ])->headerActions([
            Tables\Actions\CreateAction::make()->form([
                Forms\Components\Select::make('work_order_id')->required()->options(fn () => static::authorizedWorkOrders()
                    ->whereHas('serviceRequest', fn ($query) => $query->withoutGlobalScopes()
                        ->where('request_type', 'product_supply'))
                    ->whereNotIn('status', [
                        WorkOrder::STATUS_COMPLETION_SUBMITTED,
                        WorkOrder::STATUS_COMPLETED,
                        WorkOrder::STATUS_CANCELLED,
                    ])
                    ->pluck('work_order_number', 'id')),
                Forms\Components\DateTimePicker::make('scheduled_for'),
                Forms\Components\TextInput::make('delivery_reference'),
                Forms\Components\Select::make('assigned_membership_id')
                    ->options(fn () => static::deliveryAssigneeOptions()),
                Forms\Components\Textarea::make('notes'),
            ])->using(fn (array $data) => app(SupplyDeliveryService::class)->create(
                WorkOrder::findOrFail($data['work_order_id']), $data, auth()->user(),
            )),
        ])->actions([
            Tables\Actions\Action::make('ready')->visible(fn (SupplyDelivery $record) => auth()->user()->can('update', $record)
                && $record->status === SupplyDelivery::STATUS_PREPARING)
                ->action(fn (SupplyDelivery $record) => app(SupplyDeliveryService::class)->transition($record, SupplyDelivery::STATUS_READY, [], auth()->user())),
            Tables\Actions\Action::make('dispatch')->visible(fn (SupplyDelivery $record) => auth()->user()->can('update', $record)
                && $record->status === SupplyDelivery::STATUS_READY)
                ->action(fn (SupplyDelivery $record) => app(SupplyDeliveryService::class)->transition($record, SupplyDelivery::STATUS_DISPATCHED, [], auth()->user())),
            Tables\Actions\Action::make('deliver')
                ->form([Forms\Components\TextInput::make('recipient_name')->required()])
                ->visible(fn (SupplyDelivery $record) => auth()->user()->can('update', $record)
                    && $record->status === SupplyDelivery::STATUS_DISPATCHED)
                ->action(fn (SupplyDelivery $record, array $data) => app(SupplyDeliveryService::class)->transition(
                    $record, SupplyDelivery::STATUS_DELIVERED, $data, auth()->user(),
                )),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $workOrderIds = static::authorizedWorkOrders()->select('work_orders.id');

        return $query->whereIn('work_order_id', $workOrderIds);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSupplyDeliveries::route('/')];
    }

    private static function authorizedWorkOrders(): Builder
    {
        $query = WorkOrder::query();
        $company = app(CurrentProviderCompany::class)->company();
        $role = $company ? app(ProviderAccess::class)->role(auth()->user(), $company) : null;
        if (in_array($role, [...ProviderAccess::MANAGE_COMPANY_ROLES, 'viewer'], true)) {
            return $query;
        }

        $membershipId = ProviderCompanyMembership::withoutGlobalScopes()
            ->where('provider_company_id', $company?->getKey())
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->value('id');

        return $query->whereHas('assignments', fn (Builder $assignment) => $assignment
            ->where('provider_company_membership_id', $membershipId ?: 0)
            ->whereIn('status', \App\Models\WorkOrderAssignment::ACTIVE_STATUSES));
    }

    private static function deliveryAssigneeOptions(): array
    {
        $company = app(CurrentProviderCompany::class)->company();
        $role = $company ? app(ProviderAccess::class)->role(auth()->user(), $company) : null;
        $memberships = ProviderCompanyMembership::query()->where('is_active', true);
        if (! in_array($role, ProviderAccess::MANAGE_COMPANY_ROLES, true)) {
            $memberships->where('user_id', auth()->id());
        }

        return $memberships->with('user')->get()->mapWithKeys(fn ($membership) => [
            $membership->id => $membership->user?->name.' ('.$membership->role.')',
        ])->all();
    }
}
