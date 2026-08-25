<?php

namespace App\Services;

use App\Models\ProviderCompany;
use App\Models\ProviderCompanyMembership;
use App\Models\ProviderStaffInvitation;
use App\Models\User;
use App\Support\ProviderAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProviderStaffInvitationService
{
    public function invite(ProviderCompany $company, string $email, string $role, User $inviter, ?int $expiresInDays = 7): ProviderStaffInvitation
    {
        if (! app(ProviderAccess::class)->hasRole($inviter, $company, ProviderAccess::MANAGE_COMPANY_ROLES)) {
            abort(403);
        }
        if (! in_array($role, ProviderCompanyMembership::ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Unsupported provider role.']);
        }

        $email = mb_strtolower(trim($email));
        $plainToken = Str::random(64);

        $invitation = DB::transaction(function () use ($company, $email, $role, $inviter, $expiresInDays, $plainToken) {
            ProviderCompany::lockForUpdate()->findOrFail($company->getKey());

            if ($company->users()->wherePivot('is_active', true)
                ->whereRaw('LOWER(users.email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => 'This email already has an active provider membership.']);
            }
            if (ProviderStaffInvitation::withoutGlobalScopes()
                ->where('provider_company_id', $company->getKey())->where('email', $email)
                ->where('status', ProviderStaffInvitation::STATUS_PENDING)
                ->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['email' => 'An active invitation already exists for this email.']);
            }

            return ProviderStaffInvitation::withoutGlobalScopes()->create([
                'provider_company_id' => $company->getKey(), 'email' => $email, 'role' => $role,
                'status' => ProviderStaffInvitation::STATUS_PENDING,
                'token_hash' => hash('sha256', $plainToken), 'invited_by' => $inviter->getKey(),
                'invited_at' => now(), 'expires_at' => now()->addDays($expiresInDays ?? 7),
            ]);
        });

        $invitation->plainTextToken = $plainToken;

        return $invitation;
    }

    public function accept(string $plainToken, User $user): ProviderCompanyMembership
    {
        return DB::transaction(function () use ($plainToken, $user) {
            $invitation = ProviderStaffInvitation::withoutGlobalScopes()
                ->where('token_hash', hash('sha256', $plainToken))->lockForUpdate()->first();
            if (! $invitation || $invitation->status !== ProviderStaffInvitation::STATUS_PENDING) {
                throw ValidationException::withMessages(['invitation' => 'This provider staff invitation is invalid or no longer active.']);
            }
            if ($invitation->expires_at->isPast()) {
                $invitation->forceFill(['status' => ProviderStaffInvitation::STATUS_EXPIRED])->save();
                throw ValidationException::withMessages(['invitation' => 'This provider staff invitation has expired.']);
            }
            if (mb_strtolower(trim($user->email)) !== $invitation->email) {
                throw ValidationException::withMessages(['email' => 'Sign in with the exact email address that was invited.']);
            }

            $membership = ProviderCompanyMembership::withoutGlobalScopes()->firstOrNew([
                'provider_company_id' => $invitation->provider_company_id,
                'user_id' => $user->getKey(),
            ]);
            if ($membership->exists && $membership->is_active) {
                throw ValidationException::withMessages(['membership' => 'This user is already an active member of the provider company.']);
            }
            $membership->fill(['role' => $invitation->role, 'is_active' => true])->save();
            $invitation->forceFill(['status' => ProviderStaffInvitation::STATUS_ACCEPTED, 'accepted_at' => now()])->save();

            return $membership->refresh();
        });
    }
}
