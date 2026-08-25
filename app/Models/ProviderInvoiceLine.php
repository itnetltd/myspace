<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProviderInvoiceLine extends Model
{
    protected $fillable = [
        'provider_invoice_id', 'quotation_line_id', 'description', 'quantity', 'unit_price',
        'tax_amount', 'discount_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2', 'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            if (ProviderInvoice::withoutGlobalScopes()->find($line->provider_invoice_id)?->status !== ProviderInvoice::STATUS_DRAFT) {
                throw ValidationException::withMessages(['invoice' => 'Only draft provider invoice lines can be changed.']);
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProviderInvoice::class, 'provider_invoice_id');
    }

    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(QuotationLine::class);
    }
}
