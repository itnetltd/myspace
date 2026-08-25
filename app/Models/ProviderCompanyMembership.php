<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ProviderCompanyMembership extends Model
{
    use BelongsToProviderCompany;

    public const ROLES = ['owner', 'administrator', 'sales', 'technician', 'inspector', 'accountant', 'viewer'];

    protected $fillable = ['provider_company_id', 'user_id', 'role', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $membership) {
            if (! in_array($membership->role, self::ROLES, true)) {
                throw ValidationException::withMessages(['role' => 'Unsupported provider role.']);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workOrderAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderAssignment::class);
    }
}
