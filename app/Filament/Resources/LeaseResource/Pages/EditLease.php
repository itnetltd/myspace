<?php

namespace App\Filament\Resources\LeaseResource\Pages;

use App\Filament\Resources\LeaseResource;
use App\Models\ContractTemplate;
use App\Models\Lease;
use App\Models\LeaseContract;
use App\Models\RentInvoice;
use App\Models\Setting;
use App\Services\ContractRenderService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditLease extends EditRecord
{
    protected static string $resource = LeaseResource::class;

    /**
     * Generate invoices for a lease (shared by Activate + manual generator).
     * Keeps your logic consistent and avoids duplication.
     */
    protected function generateInvoicesForLease(Lease $lease, int $months, int $dueDay, ?string $firstPeriodStart = null): array
    {
        if (empty($lease->monthly_rent) || (float) $lease->monthly_rent <= 0) {
            return ['ok' => false, 'message' => 'Set monthly_rent on this lease first.'];
        }

        if (empty($lease->start_date) && empty($firstPeriodStart)) {
            return ['ok' => false, 'message' => 'Set start_date on this lease or choose First invoice period start.'];
        }

        $months = max(1, min($months, 36));
        $dueDay = max(1, min($dueDay, 28));

        $baseStart = ! empty($firstPeriodStart)
            ? Carbon::parse($firstPeriodStart)->startOfDay()
            : Carbon::parse($lease->start_date)->startOfDay();

        // Never generate invoices before lease start month
        if (! empty($lease->start_date)) {
            $leaseStartMonth = Carbon::parse($lease->start_date)->startOfMonth();
            if ($baseStart->startOfMonth()->lt($leaseStartMonth)) {
                $baseStart = $leaseStartMonth->copy();
            }
        }

        $leaseEndMonth = $lease->end_date
            ? Carbon::parse($lease->end_date)->endOfMonth()
            : null;

        $created = 0;
        $skipped = 0;

        for ($i = 0; $i < $months; $i++) {
            $periodStart = $baseStart->copy()->addMonthsNoOverflow($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            if ($leaseEndMonth && $periodStart->gt($leaseEndMonth)) {
                break;
            }

            $dueDate = $periodStart->copy()->day($dueDay);

            $exists = RentInvoice::query()
                ->where('lease_id', $lease->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->whereDate('period_end', $periodEnd->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            RentInvoice::create([
                'lease_id' => $lease->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'amount_due' => (float) $lease->monthly_rent,
                'amount_paid' => 0,
                'status' => 'unpaid',
                'notes' => null,
            ]);

            $created++;
        }

        // Refresh totals to apply late fees policy + status recalculation (if implemented)
        RentInvoice::where('lease_id', $lease->id)
            ->where('status', '!=', 'paid')
            ->get()
            ->each(fn (RentInvoice $inv) => method_exists($inv, 'refreshPaymentTotals') ? $inv->refreshPaymentTotals() : null);

        return ['ok' => true, 'created' => $created, 'skipped' => $skipped];
    }

    protected function getHeaderActions(): array
    {
        return [
            // ✅ Keep delete (your current code)
            Actions\DeleteAction::make(),

            // ✅ Activate Lease (marks unit occupied) + AUTO-GENERATE INVOICES
            Actions\Action::make('activateLease')
                ->label('Activate Lease')
                ->icon('heroicon-o-check-circle')
                ->visible(fn () => ($this->record->status ?? 'draft') !== 'active')
                ->action(function () {
                    /** @var Lease $lease */
                    $lease = $this->record;

                    $lease->update(['status' => 'active']);

                    // If your units table doesn't have status, remove this line:
                    $lease->unit?->update(['status' => 'occupied']);

                    // ✅ Auto-generate invoices based on policy settings (fallback to 6 months, due day 5)
                    $monthsAhead = (int) Setting::get('rent.invoice_months_ahead', 6);
                    $dueDay = (int) Setting::get('rent.due_day', 5);

                    $result = $this->generateInvoicesForLease(
                        $lease,
                        $monthsAhead,
                        $dueDay,
                        optional($lease->start_date)?->format('Y-m-d')
                    );

                    if (! $result['ok']) {
                        Notification::make()
                            ->title('Lease activated (invoices not generated)')
                            ->body($result['message'])
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Lease activated & unit marked occupied')
                        ->body("Invoices: Created {$result['created']}, Skipped {$result['skipped']}.")
                        ->success()
                        ->send();
                }),

            // ✅ INSPECTIONS dropdown (fits on Windows)
            ActionGroup::make([
                Actions\Action::make('createMoveInInspection')
                    ->label('Create Move-In Inspection')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->action(function () {
                        /** @var Lease $lease */
                        $lease = $this->record;

                        $inspection = LeaseResource::createInspectionForLease($lease, 'move_in');

                        Notification::make()
                            ->title('Move-in inspection created')
                            ->success()
                            ->send();

                        $this->redirect(route('filament.admin.resources.inspections.edit', $inspection));
                    }),

                Actions\Action::make('createMoveOutInspection')
                    ->label('Create Move-Out Inspection')
                    ->icon('heroicon-o-arrow-left-end-on-rectangle')
                    ->action(function () {
                        /** @var Lease $lease */
                        $lease = $this->record;

                        $inspection = LeaseResource::createInspectionForLease($lease, 'move_out');

                        Notification::make()
                            ->title('Move-out inspection created')
                            ->success()
                            ->send();

                        $this->redirect(route('filament.admin.resources.inspections.edit', $inspection));
                    }),

                Actions\Action::make('downloadMoveOutPdf')
                    ->label('Move-Out PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn () => $this->record->inspections()->where('type', 'move_out')->exists())
                    ->url(function () {
                        $moveOut = $this->record->inspections()
                            ->where('type', 'move_out')
                            ->latest('inspected_on')
                            ->first();

                        return route('reports.moveout', $moveOut);
                    })
                    ->openUrlInNewTab(),

                Actions\Action::make('viewInspections')
                    ->label('View Inspections')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn () => route('filament.admin.resources.inspections.index', [
                        'tableFilters[lease_id][value]' => $this->record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
                ->label('Inspections')
                ->icon('heroicon-o-clipboard-document-check')
                ->button(),

            // ✅ RENT dropdown (fits on Windows)
            ActionGroup::make([
                Actions\Action::make('viewInvoices')
                    ->label('View Rent Invoices')
                    ->icon('heroicon-o-receipt-percent')
                    ->url(fn () => route('filament.admin.resources.rent-invoices.index', [
                        'tableFilters[lease_id][value]' => $this->record->id,
                    ]))
                    ->openUrlInNewTab(),

                // ✅ Rent Statement PDF
                Actions\Action::make('rentStatementPdf')
                    ->label('Rent Statement PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn () => route('reports.rent.statement.lease', $this->record))
                    ->openUrlInNewTab(),

                Actions\Action::make('generateInvoices')
                    ->label('Generate Invoices')
                    ->icon('heroicon-o-calendar-days')
                    ->visible(fn () => Gate::allows('create', RentInvoice::class))
                    ->form([
                        TextInput::make('months')
                            ->label('How many months?')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(36)
                            ->default(fn () => (int) Setting::get('rent.invoice_months_ahead', 6))
                            ->required(),

                        DatePicker::make('first_period_start')
                            ->label('First invoice period start')
                            ->helperText('Leave empty to start from lease start date.')
                            ->default(fn () => optional($this->record->start_date)?->format('Y-m-d')),

                        TextInput::make('due_day')
                            ->label('Due day of month (1-28)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(28)
                            ->default(fn () => (int) Setting::get('rent.due_day', 5))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        Gate::authorize('create', RentInvoice::class);

                        /** @var Lease $lease */
                        $lease = $this->record;

                        $result = $this->generateInvoicesForLease(
                            $lease,
                            (int) ($data['months'] ?? 6),
                            (int) ($data['due_day'] ?? 5),
                            ! empty($data['first_period_start']) ? $data['first_period_start'] : null
                        );

                        if (! $result['ok']) {
                            Notification::make()
                                ->title('Cannot generate invoices')
                                ->body($result['message'])
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Invoices generated')
                            ->body("Created: {$result['created']}. Skipped existing: {$result['skipped']}.")
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Rent')
                ->icon('heroicon-o-banknotes')
                ->button(),

            // ✅ CONTRACTS dropdown (templates + languages)
            ActionGroup::make([
                Actions\Action::make('generateContract')
                    ->label('Generate Contract')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn () => Gate::allows('create', LeaseContract::class))
                    ->form([
                        Select::make('template_id')
                            ->label('Template')
                            ->options(fn () => ContractTemplate::where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($t) => [$t->id => "{$t->name} ({$t->language} v{$t->version})"])
                                ->toArray()
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        Gate::authorize('create', LeaseContract::class);

                        /** @var Lease $lease */
                        $lease = $this->record;

                        $template = ContractTemplate::findOrFail((int) $data['template_id']);
                        Gate::authorize('view', $template);

                        $rendered = app(ContractRenderService::class)->render($lease, $template);

                        $contract = LeaseContract::create([
                            'lease_id' => $lease->id,
                            'contract_template_id' => $template->id,
                            'language' => $template->language,
                            'status' => 'draft',
                            'rendered_html' => $rendered,
                        ]);

                        Notification::make()
                            ->title('Contract generated')
                            ->body("Contract #{$contract->id} created from {$template->name} ({$template->language}).")
                            ->success()
                            ->send();

                        // Open the PDF download route in a new tab
                        $this->redirect(route('contracts.pdf', $contract));
                    }),

                Actions\Action::make('viewContracts')
                    ->label('View Contracts')
                    ->icon('heroicon-o-folder-open')
                    ->url(fn () => route('filament.admin.resources.lease-contracts.index', [
                        'tableFilters[lease_id][value]' => $this->record->id,
                    ]))
                    ->openUrlInNewTab(),

                Actions\Action::make('downloadLatestContractPdf')
                    ->label('Latest Contract PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn () => $this->record->contracts()->exists())
                    ->url(function () {
                        $latest = $this->record->contracts()->latest('id')->first();

                        return route('contracts.pdf', $latest);
                    })
                    ->openUrlInNewTab(),
            ])
                ->label('Contracts')
                ->icon('heroicon-o-document-duplicate')
                ->button(),

            // ✅ MAINTENANCE dropdown (fits on Windows)
            ActionGroup::make([
                Actions\Action::make('newMaintenanceTicket')
                    ->label('New Maintenance Ticket')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->url(fn () => route('filament.admin.resources.maintenance-tickets.create', [
                        'unit_id' => $this->record->unit_id,
                        'lease_id' => $this->record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
                ->label('Maintenance')
                ->icon('heroicon-o-wrench-screwdriver')
                ->button(),

            // ✅ End Lease (requires move-out inspection, marks unit vacant)
            Actions\Action::make('endLease')
                ->label('End Lease')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn () => ($this->record->status ?? 'draft') !== 'ended')
                ->action(function () {
                    /** @var Lease $lease */
                    $lease = $this->record;

                    if (! method_exists($lease, 'hasMoveOutInspection') || ! $lease->hasMoveOutInspection()) {
                        Notification::make()
                            ->title('Cannot end lease')
                            ->body('Create a Move-Out Inspection first.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $lease->update(['status' => 'ended']);

                    // If your units table doesn't have status, remove this line:
                    $lease->unit?->update(['status' => 'vacant']);

                    Notification::make()
                        ->title('Lease ended & unit marked vacant')
                        ->success()
                        ->send();
                }),
        ];
    }
}
