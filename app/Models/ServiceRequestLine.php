<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ServiceRequestLine extends Model
{
    protected $fillable = [
        'service_request_id', 'asset_item_id', 'description', 'quantity', 'unit',
        'requested_brand', 'requested_model', 'specification', 'photo_path',
        'allow_alternative', 'notes',
    ];

    protected $casts = ['quantity' => 'decimal:3', 'allow_alternative' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            if (! $line->asset_item_id) {
                return;
            }

            $request = ServiceRequest::withoutGlobalScopes()->find($line->service_request_id);
            $asset = AssetItem::withoutGlobalScopes()->find($line->asset_item_id);
            if (! $request || ! $asset || (int) $request->account_id !== (int) $asset->account_id) {
                throw ValidationException::withMessages(['asset_item_id' => 'The asset item belongs to another account.']);
            }
        });
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function assetItem(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class);
    }
}
