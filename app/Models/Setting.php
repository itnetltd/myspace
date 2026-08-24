<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class Setting extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'key', 'value'];

    public static function get(string $key, $default = null, Account|int|null $account = null)
    {
        $query = $account
            ? static::withoutGlobalScopes()->where('account_id', $account instanceof Account ? $account->id : $account)
            : static::query();

        return optional($query->where('key', $key)->first())->value ?? $default;
    }

    public static function set(string $key, $value, Account|int|null $account = null): void
    {
        $accountId = $account instanceof Account
            ? $account->getKey()
            : ($account ?: app(CurrentAccount::class)->id());

        if (! $accountId) {
            throw new LogicException('An account is required when writing settings.');
        }

        static::withoutGlobalScopes()->updateOrCreate(
            ['account_id' => $accountId, 'key' => $key],
            ['value' => (string) $value],
        );
    }
}
