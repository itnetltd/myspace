<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class QuotationLine extends Model
{
    protected $fillable = [
        'quotation_id', 'service_request_line_id', 'supplier_product_id', 'description',
        'quantity', 'unit_price', 'tax_amount', 'discount_amount', 'line_total',
        'is_alternative', 'alternative_reason', 'availability_status', 'delivery_days',
        'warranty_months', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2', 'line_total' => 'decimal:2', 'is_alternative' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            $quotation = Quotation::withoutGlobalScopes()->find($line->quotation_id);
            if (! $quotation || $quotation->status !== Quotation::STATUS_DRAFT) {
                throw ValidationException::withMessages(['quotation' => 'Only draft quotation lines can be changed.']);
            }
            if ($line->is_alternative && blank($line->alternative_reason)) {
                throw ValidationException::withMessages(['alternative_reason' => 'Alternative products require a reason.']);
            }
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(ServiceRequestLine::class, 'service_request_line_id');
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }
}
