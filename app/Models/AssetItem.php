<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetItem extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_cost',
        'replacement_value', // NEW
        'notes',
    ];

    public function unitAssets(): HasMany
    {
        return $this->hasMany(UnitAsset::class);
    }

    public function inspectionLines(): HasMany
    {
        return $this->hasMany(InspectionLine::class);
    }
}
