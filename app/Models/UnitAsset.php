<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitAsset extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'unit_id', 'asset_item_id', 'quantity', 'condition_status', 'notes', 'photo_path',
    ];

    protected function accountParentMap(): array
    {
        return [
            'unit_id' => Unit::class,
            'asset_item_id' => AssetItem::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assetItem(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class);
    }
}
