<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class ServiceRequest extends Model
{
    use BelongsToAccount;

    public const TYPE_MAINTENANCE = 'maintenance';

    public const TYPE_PRODUCT_SUPPLY = 'product_supply';

    public const TYPE_INSPECTION = 'inspection';

    public const TYPES = [self::TYPE_MAINTENANCE, self::TYPE_PRODUCT_SUPPLY, self::TYPE_INSPECTION];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_QUOTES_RECEIVED = 'quotes_received';

    public const STATUS_QUOTE_ACCEPTED = 'quote_accepted';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'account_id', 'property_owner_id', 'property_id', 'unit_id', 'lease_id',
        'maintenance_ticket_id', 'inspection_id', 'request_number', 'request_type',
        'title', 'description', 'priority', 'status', 'required_by', 'created_by',
        'accepted_quotation_id', 'owner_approval_required', 'owner_approved_at',
        'owner_approved_by', 'owner_approval_reference',
    ];

    protected $casts = [
        'required_by' => 'date',
        'owner_approval_required' => 'boolean',
        'owner_approved_at' => 'datetime',
    ];

    protected $attributes = ['priority' => 'normal', 'status' => self::STATUS_DRAFT];

    protected static function booted(): void
    {
        static::saving(function (self $request) {
            if (! in_array($request->request_type, self::TYPES, true) || ! in_array($request->priority, self::PRIORITIES, true)) {
                throw ValidationException::withMessages(['request' => 'Unsupported request type or priority.']);
            }
            $property = $request->property_id ? Property::withoutGlobalScopes()->find($request->property_id) : null;
            if ($property && $request->property_owner_id && (int) $property->property_owner_id !== (int) $request->property_owner_id) {
                throw ValidationException::withMessages(['property_id' => 'The property does not belong to the selected owner.']);
            }
            $unit = $request->unit_id ? Unit::withoutGlobalScopes()->find($request->unit_id) : null;
            if ($unit && $request->property_id && (int) $unit->property_id !== (int) $request->property_id) {
                throw ValidationException::withMessages(['unit_id' => 'The unit does not belong to the selected property.']);
            }
            if ($request->accepted_quotation_id) {
                $belongs = Quotation::withoutGlobalScopes()->whereKey($request->accepted_quotation_id)
                    ->where('service_request_id', $request->getKey())->exists();
                if (! $belongs) {
                    throw ValidationException::withMessages(['accepted_quotation_id' => 'The quotation belongs to another request.']);
                }
            }
        });
    }

    protected function accountParentMap(): array
    {
        return [
            'property_owner_id' => PropertyOwner::class, 'property_id' => Property::class,
            'unit_id' => Unit::class, 'lease_id' => Lease::class,
            'maintenance_ticket_id' => MaintenanceTicket::class, 'inspection_id' => Inspection::class,
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ServiceRequestLine::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProviderInvitation::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function acceptedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'accepted_quotation_id');
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    public function providerInvoice(): HasOne
    {
        return $this->hasOne(ProviderInvoice::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
