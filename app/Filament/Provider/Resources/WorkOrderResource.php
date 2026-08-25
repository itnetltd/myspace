<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\WorkOrderResource\Pages;
use App\Models\ProviderCompanyMembership;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderEvidence;
use App\Services\ServiceAppointmentService;
use App\Services\WorkOrderAssignmentService;
use App\Services\WorkOrderCompletionService;
use App\Services\WorkOrderService;
use App\Support\CurrentProviderCompany;
use App\Support\ProviderAccess;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('work_order_number')->searchable(),
            Tables\Columns\TextColumn::make('serviceRequest.request_number')->label('Request'),
            Tables\Columns\TextColumn::make('serviceRequest.request_type')->label('Job type')->badge(),
            Tables\Columns\TextColumn::make('serviceRequest.title')->wrap(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('assignments_count')->counts('assignments')->label('Assigned'),
            Tables\Columns\TextColumn::make('scheduled_start')->dateTime(),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\Action::make('assignStaff')->label('Assign staff')
                ->form([
                    Forms\Components\Select::make('membership_id')->label('Provider staff')->required()->searchable()
                        ->options(fn () => ProviderCompanyMembership::withoutGlobalScopes()
                            ->where('provider_company_id', app(CurrentProviderCompany::class)->id())
                            ->where('is_active', true)->with('user')->get()
                            ->mapWithKeys(fn ($membership) => [$membership->id => $membership->user?->name.' ('.$membership->role.')'])),
                    Forms\Components\Select::make('assignment_type')->options(array_combine(WorkOrderAssignment::TYPES, WorkOrderAssignment::TYPES))->required(),
                    Forms\Components\Toggle::make('is_primary'),
                    Forms\Components\Textarea::make('notes'),
                ])
                ->visible(fn (WorkOrder $record) => in_array(app(ProviderAccess::class)->role(
                    auth()->user(), $record->provider_company_id,
                ), ProviderAccess::MANAGE_COMPANY_ROLES, true))
                ->action(fn (WorkOrder $record, array $data) => app(WorkOrderAssignmentService::class)->assign(
                    $record,
                    ProviderCompanyMembership::withoutGlobalScopes()->findOrFail($data['membership_id']),
                    $data['assignment_type'], auth()->user(), (bool) ($data['is_primary'] ?? false), $data['notes'] ?? null,
                )),
            Tables\Actions\Action::make('proposeAppointment')->label('Propose appointment')
                ->form([
                    Forms\Components\DateTimePicker::make('scheduled_start')->required(),
                    Forms\Components\DateTimePicker::make('scheduled_end')->required(),
                    Forms\Components\Textarea::make('location_notes'),
                    Forms\Components\Textarea::make('access_instructions'),
                ])
                ->visible(fn (WorkOrder $record) => auth()->user()->can('update', $record)
                    && in_array($record->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_REVISION_REQUESTED], true))
                ->action(fn (WorkOrder $record, array $data) => app(ServiceAppointmentService::class)->propose($record, $data, auth()->user())),
            Tables\Actions\Action::make('start')->requiresConfirmation()
                ->visible(fn (WorkOrder $record) => auth()->user()->can('update', $record)
                    && in_array($record->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_REVISION_REQUESTED], true))
                ->action(fn (WorkOrder $record) => app(WorkOrderService::class)->start($record, auth()->user())),
            Tables\Actions\Action::make('progress')->label('Progress update')
                ->form([
                    Forms\Components\Textarea::make('note')->required(),
                    Forms\Components\Select::make('evidence_type')->options(array_combine(WorkOrderEvidence::TYPES, WorkOrderEvidence::TYPES))->default('other'),
                    Forms\Components\FileUpload::make('files')->multiple()->disk('local')->visibility('private')->directory('private/work-orders/evidence'),
                ])
                ->visible(fn (WorkOrder $record) => auth()->user()->can('update', $record) && $record->status === WorkOrder::STATUS_IN_PROGRESS)
                ->action(fn (WorkOrder $record, array $data) => app(WorkOrderService::class)->addProgress(
                    $record, $data['note'], static::fileEvidence($data), auth()->user(),
                )),
            Tables\Actions\Action::make('submitCompletion')->label('Submit completion')
                ->form([
                    Forms\Components\Textarea::make('summary')->required(),
                    Forms\Components\Textarea::make('provider_notes'),
                    Forms\Components\Select::make('evidence_type')->options(array_combine(WorkOrderEvidence::TYPES, WorkOrderEvidence::TYPES))->default('other'),
                    Forms\Components\FileUpload::make('files')->multiple()->disk('local')->visibility('private')->directory('private/work-orders/completion'),
                    Forms\Components\TextInput::make('text_value')->label('Serial/model/other evidence text'),
                ])
                ->visible(fn (WorkOrder $record) => auth()->user()->can('update', $record)
                    && $record->status === WorkOrder::STATUS_IN_PROGRESS && $record->completion_review_required)
                ->action(fn (WorkOrder $record, array $data) => app(WorkOrderCompletionService::class)->submit(
                    $record, $data['summary'], $data['provider_notes'] ?? null,
                    static::fileEvidence($data, true), auth()->user(),
                )),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema(\App\Filament\Resources\WorkOrderResource::operationInfolist());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkOrders::route('/'),
            'view' => Pages\ViewWorkOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'serviceRequest' => fn ($query) => $query->withoutGlobalScopes(),
            'assignments.membership.user', 'appointments', 'completionSubmissions.evidence', 'activities',
        ]);
        $company = app(CurrentProviderCompany::class)->company();
        $role = $company ? app(ProviderAccess::class)->role(auth()->user(), $company) : null;
        if (in_array($role, [...ProviderAccess::MANAGE_COMPANY_ROLES, 'viewer'], true)) {
            return $query;
        }

        $membershipId = ProviderCompanyMembership::withoutGlobalScopes()
            ->where('provider_company_id', $company?->getKey())->where('user_id', auth()->id())
            ->where('is_active', true)->value('id');

        return $query->whereHas('assignments', fn ($assignment) => $assignment
            ->where('provider_company_membership_id', $membershipId ?: 0)
            ->whereNotIn('status', [WorkOrderAssignment::STATUS_CANCELLED, WorkOrderAssignment::STATUS_DECLINED]));
    }

    private static function fileEvidence(array $data, bool $includeText = false): array
    {
        $evidence = collect($data['files'] ?? [])->map(fn ($path) => [
            'evidence_type' => $data['evidence_type'] ?? 'other', 'file_path' => $path,
        ])->values()->all();
        if ($includeText && filled($data['text_value'] ?? null)) {
            $evidence[] = ['evidence_type' => $data['evidence_type'] ?? 'other', 'text_value' => $data['text_value']];
        }

        return $evidence;
    }
}
