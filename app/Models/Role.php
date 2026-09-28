<?php

namespace App\Models;

use App\Support\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A named job for Team, shared by the whole organization (one database per organization).
 * Roles are turned off instead of deleted.
 */
#[Fillable(['name', 'active'])]
class Role extends Model
{
    protected $attributes = [
        'active' => true,
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
        ];
    }
}
