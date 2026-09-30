<?php

namespace App\Models;

use App\Support\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named job for Team, shared by the whole organization (one database per organization).
 * Roles are turned off instead of deleted.
 */
#[Fillable(['name', 'active', 'can_read_team_notes'])]
class Role extends Model
{
    protected $attributes = [
        'active' => true,
        'can_read_team_notes' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            $role->name = RoleName::clean($role->name);
            $role->name_key = RoleName::key($role->name);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'can_read_team_notes' => 'boolean',
        ];
    }

    public function teamEngagements(): HasMany
    {
        return $this->hasMany(TeamEngagement::class);
    }
}
