<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ProviderInvoice extends Model
{
    use BelongsToProviderCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'service_request_id', 'work_order_id', 'quotation_id', 'provider_company_id',
        'account_id', 'property_owner_id', 'property_id', 'unit_id', 'invoice_number',
        'invoice_date', 'due_date', 'currency', 'subtotal', 'tax_amount', 'discount_amount',
        'delivery_amount', 'total_amount', 'status', 'document_path', 'notes', 'variation_reason',
        'variation_approved_at', 'variation_approved_by', 'submitted_at', 'submitted_by',
        'approved_at', 'approved_by', 'property_expense_id',
    ];

    protected $casts = [
        'invoice_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
        'delivery_amount' => 'decimal:2',
        'variation_approved_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $invoice) {
            if ($invoice->getOriginal('status') === self::STATUS_POSTED) {
                throw ValidationException::withMessages(['invoice' => 'Posted provider invoices are immutable.']);
            }

            if ($invoice->getOriginal('status') !== self::STATUS_DRAFT) {
                $commercial = [
                    'invoice_date', 'due_date', 'currency', 'subtotal', 'tax_amount',
                    'discount_amount', 'delivery_amount', 'total_amount',
                ];
                if (array_intersect(array_keys($invoice->getDirty()), $commercial) !== []) {
                    throw ValidationException::withMessages(['invoice' => 'Submitted invoice commercial details are immutable.']);
                }
            }
        });
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProviderInvoiceLine::class);
    }

    public function propertyExpense(): BelongsTo
    {
        return $this->belongsTo(PropertyExpense::class);
    }
}
