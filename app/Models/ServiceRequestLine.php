<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestLine extends Model
{
    protected $fillable = [
        'service_request_id', 'asset_item_id', 'description', 'quantity', 'unit',
        'requested_brand', 'requested_model', 'specification', 'photo_path',
        'allow_alternative', 'notes',
    ];

    protected $casts = ['quantity' => 'decimal:3', 'allow_alternative' => 'boolean'];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function assetItem(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class);
    }
}
