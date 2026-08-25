<?php

namespace App\Services;

use App\Models\AssetItem;
use App\Models\AssetItemSupplierProduct;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Support\AccountAccess;
use Illuminate\Validation\ValidationException;

class SupplierProductMatchingService
{
    public function match(AssetItem $asset, SupplierProduct $product, string $matchType, User $user, ?string $notes = null): void
    {
        if (! app(AccountAccess::class)->can($user, $asset->account_id, AccountAccess::MANAGE_ASSETS)) {
            abort(403);
        }
        if (! in_array($matchType, AssetItemSupplierProduct::MATCH_TYPES, true) || ! $product->is_active) {
            throw ValidationException::withMessages(['match_type' => 'Choose an active product and supported match type.']);
        }

        $asset->supplierProducts()->syncWithoutDetaching([
            $product->getKey() => ['match_type' => $matchType, 'notes' => $notes, 'matched_by' => $user->getKey()],
        ]);
    }
}
