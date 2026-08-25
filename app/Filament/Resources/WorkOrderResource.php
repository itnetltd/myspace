<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkOrderResource\Pages;
use App\Models\ServiceAppointment;
use App\Models\WorkOrder;
use App\Models\WorkOrderCompletionSubmission;
use App\Services\ServiceAppointmentService;
use App\Services\WorkOrderCompletionService;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Marketplace / Services';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('work_order_number')->searchable(),
            Tables\Columns\TextColumn::make('serviceRequest.request_number')->label('Request'),
            Tables\Columns\TextColumn::make('providerCompany.name')->label('Provider'),
            Tables\Columns\TextColumn::make('serviceRequest.request_type')->label('Job type')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('assignments.membership.user.name')->label('Assigned staff')->bulleted(),
            Tables\Columns\TextColumn::make('appointments.scheduled_start')->label('Appointments')->dateTime()->bulleted(),
            Tables\Columns\TextColumn::make('activities.description')->label('Timeline')->bulleted()->limitList(3),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\Action::make('confirmAppointment')
                ->form([
                    Forms\Components\Select::make('appointment_id')->required()
                        ->options(fn (WorkOrder $record) => $record->appointments()
                            ->where('status', ServiceAppointment::STATUS_PROPOSED)
                            ->get()->mapWithKeys(fn ($appointment) => [
                                $appointment->id => $appointment->scheduled_start->format('Y-m-d H:i').' - '.$appointment->scheduled_end->format('H:i'),
                            ])),
                ])
                ->visible(fn (WorkOrder $record) => app(AccountAccess::class)->can(
                    auth()->user(), app(CurrentAccount::class)->account(), AccountAccess::CONFIRM_MARKETPLACE_APPOINTMENTS,
                ) && $record->appointments()->where('status', ServiceAppointment::STATUS_PROPOSED)->exists())
                ->action(fn (array $data) => app(ServiceAppointmentService::class)->confirm(
                    ServiceAppointment::withoutGlobalScopes()->findOrFail($data['appointment_id']), auth()->user(),
                )),
            Tables\Actions\Action::make('requestReschedule')
                ->form([
                    Forms\Components\Select::make('appointment_id')->required()
                        ->options(fn (WorkOrder $record) => $record->appointments()
                            ->whereIn('status', [ServiceAppointment::STATUS_PROPOSED, ServiceAppointment::STATUS_CONFIRMED])
                            ->pluck('scheduled_start', 'id')),
                    Forms\Components\Textarea::make('reschedule_notes')->required(),
                ])
                ->visible(fn (WorkOrder $record) => app(AccountAccess::class)->can(
                    auth()->user(), app(CurrentAccount::class)->account(), AccountAccess::CONFIRM_MARKETPLACE_APPOINTMENTS,
                ) && $record->appointments()->whereIn('status', [ServiceAppointment::STATUS_PROPOSED, ServiceAppointment::STATUS_CONFIRMED])->exists())
                ->action(fn (array $data) => app(ServiceAppointmentService::class)->requestReschedule(
                    ServiceAppointment::withoutGlobalScopes()->findOrFail($data['appointment_id']), auth()->user(), $data['reschedule_notes'],
                )),
            Tables\Actions\Action::make('acceptCompletion')->requiresConfirmation()
                ->visible(fn (WorkOrder $record) => $record->status === WorkOrder::STATUS_COMPLETION_SUBMITTED
                    && app(AccountAccess::class)->can(auth()->user(), app(CurrentAccount::class)->account(), AccountAccess::REVIEW_MARKETPLACE_COMPLETION))
                ->action(fn (WorkOrder $record) => app(WorkOrderCompletionService::class)->accept(
                    $record, $record->completionSubmissions()->where('status', WorkOrderCompletionSubmission::STATUS_SUBMITTED)->latest('id')->firstOrFail(), auth()->user(),
                )),
            Tables\Actions\Action::make('requestCorrection')
                ->form([Forms\Components\Textarea::make('review_notes')->required()])
                ->visible(fn (WorkOrder $record) => $record->status === WorkOrder::STATUS_COMPLETION_SUBMITTED
                    && app(AccountAccess::class)->can(auth()->user(), app(CurrentAccount::class)->account(), AccountAccess::REVIEW_MARKETPLACE_COMPLETION))
                ->action(fn (WorkOrder $record, array $data) => app(WorkOrderCompletionService::class)->requestRevision(
                    $record, $record->completionSubmissions()->where('status', WorkOrderCompletionSubmission::STATUS_SUBMITTED)->latest('id')->firstOrFail(),
                    auth()->user(), $data['review_notes'],
                )),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema(static::operationInfolist());
    }

    public static function operationInfolist(): array
    {
        return [
            Infolists\Components\Section::make('Operation')->schema([
                Infolists\Components\TextEntry::make('work_order_number'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('providerCompany.name')->label('Provider'),
                Infolists\Components\TextEntry::make('serviceRequest.request_number')->label('Request'),
                Infolists\Components\TextEntry::make('serviceRequest.title')->label('Original request'),
                Infolists\Components\TextEntry::make('quotation.quotation_number')->label('Accepted quote'),
            ])->columns(3),
            Infolists\Components\RepeatableEntry::make('assignments')->schema([
                Infolists\Components\TextEntry::make('membership.user.name')->label('Staff'),
                Infolists\Components\TextEntry::make('membership.role')->label('Operational role'),
                Infolists\Components\TextEntry::make('assignment_type'),
                Infolists\Components\TextEntry::make('status')->badge(),
            ])->columns(4),
            Infolists\Components\RepeatableEntry::make('appointments')->schema([
                Infolists\Components\TextEntry::make('scheduled_start')->dateTime(),
                Infolists\Components\TextEntry::make('scheduled_end')->dateTime(),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('reschedule_notes'),
            ])->columns(4),
            Infolists\Components\RepeatableEntry::make('completionSubmissions')->schema([
                Infolists\Components\TextEntry::make('submission_number')->label('Submission'),
                Infolists\Components\TextEntry::make('summary'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('review_notes'),
            ])->columns(4),
            Infolists\Components\RepeatableEntry::make('evidence')->schema([
                Infolists\Components\TextEntry::make('evidence_type')->badge(),
                Infolists\Components\TextEntry::make('text_value'),
                Infolists\Components\TextEntry::make('file_path')->label('Private file')
                    ->formatStateUsing(fn ($state) => $state ? 'Authorized download' : null)
                    ->url(fn ($record) => $record->file_path ? route('work-order-evidence.show', $record) : null),
            ])->columns(3),
            Infolists\Components\RepeatableEntry::make('activities')->label('Timeline')->schema([
                Infolists\Components\TextEntry::make('occurred_at')->dateTime(),
                Infolists\Components\TextEntry::make('activity_type')->badge(),
                Infolists\Components\TextEntry::make('description'),
            ])->columns(3),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()
            ->whereHas('serviceRequest', fn (Builder $query) => $query->withoutGlobalScopes()
                ->where('account_id', app(CurrentAccount::class)->id()))
            ->with(['serviceRequest' => fn ($query) => $query->withoutGlobalScopes(), 'providerCompany',
                'assignments.membership.user', 'appointments', 'activities', 'completionSubmissions.evidence']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkOrders::route('/'),
            'view' => Pages\ViewWorkOrder::route('/{record}'),
        ];
    }
}
