<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasNormalizedName
{
    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    protected static function bootHasNormalizedName(): void
    {
        static::saving(function (Model $model): void {
            $model->setAttribute('name_key', static::normalizeName((string) $model->getAttribute('name')));
        });
    }
}
