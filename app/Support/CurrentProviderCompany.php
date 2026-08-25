<?php

namespace App\Support;

use App\Models\ProviderCompany;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CurrentProviderCompany
{
    private ?ProviderCompany $resolved = null;

    private ?int $resolvedForUserId = null;

    public function company(): ?ProviderCompany
    {
        $user = Auth::user();

        return $user instanceof User ? $this->forUser($user) : null;
    }

    public function id(): ?int
    {
        return $this->company()?->getKey();
    }

    public function forUser(User $user): ?ProviderCompany
    {
        if ($this->resolvedForUserId === $user->getKey() && $this->resolved) {
            return $this->resolved;
        }

        $query = $user->providerCompanies()
            ->wherePivot('is_active', true)
            ->whereIn('provider_companies.status', [ProviderCompany::STATUS_PENDING, ProviderCompany::STATUS_ACTIVE]);

        $company = $user->current_provider_company_id
            ? (clone $query)->whereKey($user->current_provider_company_id)->first()
            : null;
        $company ??= $query->orderBy('provider_companies.id')->first();

        if ($company && (int) $user->current_provider_company_id !== (int) $company->getKey()) {
            $user->forceFill(['current_provider_company_id' => $company->getKey()])->saveQuietly();
        }

        $this->resolvedForUserId = $user->getKey();
        $this->resolved = $company;

        return $company;
    }

    public function switch(User $user, int $companyId): ProviderCompany
    {
        $company = $user->providerCompanies()
            ->wherePivot('is_active', true)
            ->whereIn('provider_companies.status', [ProviderCompany::STATUS_PENDING, ProviderCompany::STATUS_ACTIVE])
            ->whereKey($companyId)
            ->first();

        if (! $company) {
            throw new AuthorizationException('That provider workspace is unavailable or you do not belong to it.');
        }

        $user->forceFill(['current_provider_company_id' => $company->getKey()])->save();
        $this->resolvedForUserId = $user->getKey();
        $this->resolved = $company;

        return $company;
    }

    public function forget(): void
    {
        $this->resolvedForUserId = null;
        $this->resolved = null;
    }
}
