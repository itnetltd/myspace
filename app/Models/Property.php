<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    protected $fillable = [
        'name','type','address','sector','district','owner_name','owner_phone','notes',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}