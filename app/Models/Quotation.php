<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Quotation extends Model
{
    use BelongsToProviderCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'service_request_id', 'provider_company_id', 'quotation_number', 'status', 'currency',
        'subtotal', 'tax_amount', 'discount_amount', 'delivery_amount', 'total_amount',
        'valid_until', 'estimated_start_date', 'estimated_completion_date', 'warranty_notes',
        'terms', 'notes', 'submitted_at', 'accepted_at', 'rejected_at', 'created_by',
        'submitted_by', 'accepted_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'discount_amount' => 'decimal:2',
        'delivery_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'valid_until' => 'date',
        'estimated_start_date' => 'date', 'estimated_completion_date' => 'date',
        'submitted_at' => 'datetime', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $quotation) {
            if (in_array($quotation->getOriginal('status'), [self::STATUS_ACCEPTED, self::STATUS_REJECTED], true)) {
                $allowed = ['status', 'accepted_at', 'accepted_by', 'rejected_at', 'updated_at'];
                if (array_diff(array_keys($quotation->getDirty()), $allowed) !== []) {
                    throw ValidationException::withMessages(['quotation' => 'Accepted or rejected quotations are immutable.']);
                }
            }
        });
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(ProviderInvoice::class);
    }
}
