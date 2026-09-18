<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'target', 'label', 'key', 'type', 'required', 'options', 'sort_order', 'active'])]
class CustomField extends Model
{
    public const TARGET_USER = 'user';

    public const TARGET_ARTIST = 'artist';

    public const TARGET_VENDOR = 'vendor';

    public const TARGET_PATRON = 'patron';

    public const TARGET_TEAM_MEMBER = 'team_member';

    public const TARGETS = [
        self::TARGET_ARTIST,
        self::TARGET_VENDOR,
        self::TARGET_PATRON,
        self::TARGET_TEAM_MEMBER,
        self::TARGET_USER,
    ];

    public const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'checkbox'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * @param  Builder<CustomField>  $query
     */
    public function scopeForTarget(Builder $query, string $target): void
    {
        $query->where('target', $target);
    }
}
