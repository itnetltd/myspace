<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ProviderInvitationResource\Pages;
use App\Models\ProviderCompany;
use App\Models\ProviderInvitation;
use App\Models\ServiceRequest;
use App\Support\CurrentProviderCompany;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProviderInvitationResource extends Resource
{
    protected static ?string $model = ProviderInvitation::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'RFQ Invitations';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('serviceRequest.request_number')->label('Request'),
            Tables\Columns\TextColumn::make('serviceRequest.request_type')->badge(),
            Tables\Columns\TextColumn::make('serviceRequest.title')->wrap(),
            Tables\Columns\TextColumn::make('serviceRequest.property.address')->label('Location')->wrap(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('expires_at')->dateTime(),
        ])->actions([
            Tables\Actions\Action::make('quote')
                ->visible(fn (ProviderInvitation $record) => static::canQuote($record))
                ->url(fn (ProviderInvitation $record) => QuotationResource::getUrl('create', ['service_request_id' => $record->service_request_id])),
        ]);
    }

    public static function canQuote(ProviderInvitation $invitation): bool
    {
        $requestStatus = $invitation->relationLoaded('serviceRequest')
            ? $invitation->serviceRequest?->status
            : ServiceRequest::withoutGlobalScopes()->whereKey($invitation->service_request_id)->value('status');

        return in_array($invitation->status, [ProviderInvitation::STATUS_INVITED, ProviderInvitation::STATUS_VIEWED], true)
            && ($invitation->expires_at === null || ! $invitation->expires_at->isPast())
            && in_array($requestStatus, [ServiceRequest::STATUS_REQUESTED, ServiceRequest::STATUS_QUOTES_RECEIVED], true)
            && ProviderCompany::whereKey(app(CurrentProviderCompany::class)->id())
                ->where('status', ProviderCompany::STATUS_ACTIVE)
                ->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('provider_company_id', app(CurrentProviderCompany::class)->id())
            ->with(['serviceRequest' => fn ($query) => $query->withoutGlobalScopes()->with(['property', 'lines'])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProviderInvitations::route('/')];
    }
}
