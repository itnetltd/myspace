<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitAsset extends Model
{
    protected $fillable = [
        'unit_id','asset_item_id','quantity','condition_status','notes','photo_path',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assetItem(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class);
    }
}