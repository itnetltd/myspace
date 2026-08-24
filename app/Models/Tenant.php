<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'full_name',
        'phone',
        'email',
        'id_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
    ];

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }
}
