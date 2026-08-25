<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use App\Support\CurrentProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

class SupplierProduct extends Model
{
    use BelongsToProviderCompany;

    public const STOCK_STATUSES = ['in_stock', 'low_stock', 'out_of_stock', 'on_order', 'unknown'];

    protected $fillable = [
        'provider_company_id', 'name', 'sku', 'category', 'brand', 'model', 'description',
        'unit_price', 'currency', 'stock_status', 'stock_quantity', 'warranty_months',
        'estimated_delivery_days', 'image_path', 'is_active',
    ];

    protected $casts = ['unit_price' => 'decimal:2', 'stock_quantity' => 'decimal:3', 'is_active' => 'boolean'];

    protected $attributes = ['currency' => 'RWF', 'stock_status' => 'unknown', 'is_active' => true];

    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if (! in_array($product->stock_status, self::STOCK_STATUSES, true)) {
                throw ValidationException::withMessages(['stock_status' => 'Unsupported stock status.']);
            }
            $providerCompanyId = $product->provider_company_id ?: app(CurrentProviderCompany::class)->id();
            $isSupplier = ProviderCapability::withoutGlobalScopes()
                ->where('provider_company_id', $providerCompanyId)
                ->where('capability', 'supplier')->exists();
            if (! $isSupplier) {
                throw ValidationException::withMessages(['provider_company_id' => 'Only providers with supplier capability can publish products.']);
            }
        });
    }

    public function assetItems(): BelongsToMany
    {
        return $this->belongsToMany(AssetItem::class)
            ->using(AssetItemSupplierProduct::class)
            ->withPivot(['match_type', 'notes', 'matched_by'])
            ->withTimestamps();
    }
}
