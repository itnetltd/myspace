<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// ✅ Roles/Permissions
use Spatie\Permission\Traits\HasRoles;

// ✅ Add missing imports
use App\Models\Account;
use App\Models\Tenant;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',

        // ✅ Multi-account current workspace
        'current_account_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // =========================
    // Multi-Account Relationships
    // =========================

    /**
     * Accounts (workspaces) this user belongs to.
     * account_user pivot has 'role' (owner/manager/accountant/broker/etc).
     */
    public function accounts()
    {
        return $this->belongsToMany(Account::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Current selected workspace/account.
     */
    public function currentAccount()
    {
        return $this->belongsTo(Account::class, 'current_account_id');
    }

    /**
     * Safe getter for current account id.
     * Falls back to first joined account if current is empty.
     *
     * Optional: if current_account_id is empty but user has accounts,
     * automatically saves the first one as current_account_id.
     */
    public function getCurrentAccountId(bool $autoPersistFallback = true): ?int
    {
        if (!empty($this->current_account_id)) {
            return (int) $this->current_account_id;
        }

        // Prefer already-loaded accounts relation (avoids extra query)
        $firstAccountId = null;

        if ($this->relationLoaded('accounts')) {
            $firstAccountId = $this->accounts->first()?->id;
        } else {
            $firstAccountId = $this->accounts()->select('accounts.id')->value('accounts.id');
        }

        if ($firstAccountId && $autoPersistFallback) {
            // Persist once so the app always has a workspace selected
            $this->forceFill(['current_account_id' => $firstAccountId])->saveQuietly();
        }

        return $firstAccountId ? (int) $firstAccountId : null;
    }

    /**
     * Role of this user inside a specific account (from pivot).
     */
    public function roleInAccount(int $accountId): ?string
    {
        // Use loaded accounts first
        if ($this->relationLoaded('accounts')) {
            $acc = $this->accounts->firstWhere('id', $accountId);
            return $acc?->pivot?->role;
        }

        $account = $this->accounts()->where('accounts.id', $accountId)->first();
        return $account?->pivot?->role;
    }

    /**
     * Check if user has any of the given pivot roles in the CURRENT account.
     */
    public function hasAccountRole(array $roles): bool
    {
        $accountId = $this->getCurrentAccountId();
        if (!$accountId) return false;

        $role = $this->roleInAccount($accountId);
        return $role ? in_array($role, $roles, true) : false;
    }

    // =========================
    // Tenant Portal Link (Optional)
    // =========================

    /**
     * If this user is a tenant portal user, link to Tenant record.
     * Requires tenants.user_id (nullable) column.
     */
    public function tenantProfile()
    {
        return $this->hasOne(Tenant::class, 'user_id');
    }

    /**
     * Quick check: is this login a tenant portal user?
     */
    public function isTenantUser(): bool
    {
        if ($this->relationLoaded('tenantProfile')) {
            return (bool) $this->tenantProfile;
        }

        return $this->tenantProfile()->exists();
    }
}