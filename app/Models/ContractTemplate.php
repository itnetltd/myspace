<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $fillable = [
        'name','language','version','is_active','body_html','required_fields'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'required_fields' => 'array',
    ];

    public function leaseContracts()
    {
        return $this->hasMany(LeaseContract::class);
    }
}