<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PropertyExpense extends Model
{
    use BelongsToAccount;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AWAITING_APPROVAL = 'awaiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    public const CATEGORIES = [
        'maintenance' => 'Maintenance',
        'repair' => 'Repair',
        'utilities' => 'Utilities',
        'cleaning' => 'Cleaning',
        'inspection' => 'Inspection',
        'supplier_purchase' => 'Supplier Purchase',
        'insurance' => 'Insurance',
        'tax' => 'Tax',
        'security' => 'Security',
        'professional_service' => 'Professional Service',
        'other' => 'Other',
    ];

    protected $fillable = [
        'account_id', 'property_owner_id', 'property_id', 'unit_id', 'lease_id',
        'maintenance_ticket_id', 'expense_number', 'category', 'vendor_name',
        'description', 'amount', 'currency', 'occurred_on', 'status', 'reference',
        'document_path', 'notes', 'owner_approval_required', 'owner_approved_at',
        'owner_approved_by', 'approved_at', 'approved_by', 'source_type', 'source_id',
        'created_by',
        'provider_invoice_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_on' => 'date',
        'owner_approval_required' => 'boolean',
        'owner_approved_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT];

    protected function accountParentMap(): array
    {
        return [
            'property_owner_id' => PropertyOwner::class,
            'property_id' => Property::class,
            'unit_id' => Unit::class,
            'lease_id' => Lease::class,
            'maintenance_ticket_id' => MaintenanceTicket::class,
            'provider_invoice_id' => ProviderInvoice::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $expense) {
            if (Money::toMinor($expense->amount) < 0) {
                throw ValidationException::withMessages(['amount' => 'The expense amount cannot be negative.']);
            }

            $property = Property::withoutGlobalScopes()->find($expense->property_id);

            if (! $property || (int) $property->property_owner_id !== (int) $expense->property_owner_id) {
                throw ValidationException::withMessages([
                    'property_owner_id' => 'The selected property does not belong to this owner.',
                ]);
            }

            $unit = $expense->unit_id
                ? Unit::withoutGlobalScopes()->find($expense->unit_id)
                : null;

            if ($expense->unit_id && (! $unit || (int) $unit->property_id !== (int) $expense->property_id)) {
                throw ValidationException::withMessages([
                    'unit_id' => 'The selected unit does not belong to the selected property.',
                ]);
            }

            $lease = $expense->lease_id
                ? Lease::withoutGlobalScopes()->find($expense->lease_id)
                : null;

            if ($expense->lease_id && (! $lease || ! $unit || (int) $lease->unit_id !== (int) $unit->getKey())) {
                throw ValidationException::withMessages([
                    'lease_id' => 'The selected lease does not belong to the selected unit.',
                ]);
            }

            $ticket = $expense->maintenance_ticket_id
                ? MaintenanceTicket::withoutGlobalScopes()->find($expense->maintenance_ticket_id)
                : null;

            if ($expense->maintenance_ticket_id
                && (! $ticket || ! $unit || (int) $ticket->unit_id !== (int) $unit->getKey())) {
                throw ValidationException::withMessages([
                    'maintenance_ticket_id' => 'The maintenance ticket does not belong to the selected unit.',
                ]);
            }

            if ($ticket?->lease_id && (int) $ticket->lease_id !== (int) $expense->lease_id) {
                throw ValidationException::withMessages([
                    'maintenance_ticket_id' => 'The maintenance ticket lease is incompatible with the selected lease.',
                ]);
            }

            if ($expense->isDirty('status') && $expense->status === self::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'status' => 'Use the controlled Post Expense action to create the ledger entry.',
                ]);
            }

            if ($expense->exists && $expense->getOriginal('status') === self::STATUS_POSTED) {
                $allowedChanges = ['status', 'notes', 'updated_at'];
                $financialChanges = array_diff(array_keys($expense->getDirty()), $allowedChanges);

                if ($financialChanges !== []) {
                    throw ValidationException::withMessages([
                        'status' => 'Posted expense financial details are immutable; create an adjustment instead.',
                    ]);
                }

                if ($expense->isDirty('status')) {
                    throw ValidationException::withMessages([
                        'status' => 'Use the controlled Void Expense action so a reversal is recorded.',
                    ]);
                }
            }
        });
    }

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function maintenanceTicket(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTicket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ownerApprovalRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_approved_by');
    }

    public function providerInvoice(): BelongsTo
    {
        return $this->belongsTo(ProviderInvoice::class);
    }
}
