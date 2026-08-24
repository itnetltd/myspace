<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetItem extends Model
{
    protected $fillable = [
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