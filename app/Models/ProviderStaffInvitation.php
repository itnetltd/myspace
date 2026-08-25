<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProviderStaffInvitation extends Model
{
    use BelongsToProviderCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public ?string $plainTextToken = null;

    protected $fillable = [
        'provider_company_id', 'email', 'role', 'status', 'token_hash', 'invited_by',
        'invited_at', 'accepted_at', 'expires_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'invited_at' => 'datetime', 'accepted_at' => 'datetime', 'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $invitation) {
            $invitation->email = mb_strtolower(trim($invitation->email));

            if (! in_array($invitation->role, ProviderCompanyMembership::ROLES, true)) {
                throw ValidationException::withMessages(['role' => 'Unsupported provider role.']);
            }
        });
    }

    public function providerCompany(): BelongsTo
    {
        return $this->belongsTo(ProviderCompany::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
