<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'name', 'language', 'version', 'is_active', 'body_html', 'required_fields',
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
