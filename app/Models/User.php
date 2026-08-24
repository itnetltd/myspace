<?php

namespace App\Models;

use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

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
            ->withPivot(['role', 'is_active'])
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
    public function getCurrentAccountId(): ?int
    {
        $account = app(CurrentAccount::class)->forUser($this);

        return $account?->getKey();
    }

    /**
     * Role of this user inside a specific account (from pivot).
     */
    public function roleInAccount(int $accountId): ?string
    {
        // Use loaded accounts first
        if ($this->relationLoaded('accounts')) {
            $acc = $this->accounts->first(
                fn (Account $account) => (int) $account->getKey() === $accountId
                    && (bool) $account->pivot?->is_active,
            );

            return $acc?->pivot?->role;
        }

        $account = $this->accounts()
            ->wherePivot('is_active', true)
            ->where('accounts.id', $accountId)
            ->first();

        return $account?->pivot?->role;
    }

    /**
     * Check if user has any of the given pivot roles in the CURRENT account.
     */
    public function hasAccountRole(array $roles): bool
    {
        $accountId = $this->getCurrentAccountId();
        if (! $accountId) {
            return false;
        }

        $role = $this->roleInAccount($accountId);

        return $role ? in_array($role, $roles, true) : false;
    }
}
