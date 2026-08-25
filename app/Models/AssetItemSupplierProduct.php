<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

class AssetItemSupplierProduct extends Pivot
{
    public const MATCH_TYPES = ['exact', 'compatible', 'alternative'];

    protected $table = 'asset_item_supplier_product';

    protected static function booted(): void
    {
        static::saving(function (self $match) {
            if (! in_array($match->match_type, self::MATCH_TYPES, true)) {
                throw ValidationException::withMessages(['match_type' => 'Unsupported supplier-product match type.']);
            }

            $asset = AssetItem::withoutGlobalScopes()->find($match->asset_item_id);
            $product = SupplierProduct::withoutGlobalScopes()->find($match->supplier_product_id);
            if (! $asset || ! $product) {
                throw ValidationException::withMessages(['match' => 'Both the asset item and supplier product must exist.']);
            }
        });
    }
}
