<?php

namespace App\Support;

final class Permissions
{
    public const INCLUDES = [
        'artists.view' => [],
        'artists.edit' => ['artists.view', 'artists.personal_info'],
        'artists.personal_info' => [],
        'vendors.view' => [],
        'vendors.edit' => ['vendors.view', 'vendors.personal_info'],
        'vendors.personal_info' => [],
        'checkin.view' => [],
        'checkin.edit' => ['checkin.view'],
        'team.view' => [],
        'team.edit' => ['team.view', 'team.notes.read', 'team.notes.add', 'team.personal_info'],
        'team.change_role' => ['team.view'],
        'team.notes.read' => [],
        'team.notes.add' => ['team.notes.read'],
        'team.personal_info' => [],
        'scheduling.view' => [],
        'scheduling.edit' => ['scheduling.view'],
        'forms.view' => [],
        'forms.edit' => ['forms.view'],
        'meals.view' => [],
        'meals.edit' => ['meals.view'],
        'meals.claim' => ['meals.view'],
        'meals.override' => ['meals.view'],
        'meals.undo_claim' => ['meals.view'],
        'patrons.view' => [],
        'patrons.personal_info' => ['patrons.view'],
    ];

    public static function keys(): array
    {
        return array_keys(self::INCLUDES);
    }

    public static function expand(array $permissions): array
    {
        $known = array_values(array_intersect($permissions, self::keys()));
        foreach ($known as $key) {
            $known = array_merge($known, self::INCLUDES[$key]);
        }

        return array_values(array_unique($known));
    }

    public static function groups(): array
    {
        return collect(self::INCLUDES)->map(fn ($includes, $key) => [
            'key' => $key,
            'label' => __('permissions.'.$key.'.label'),
            'tooltip' => __('permissions.'.$key.'.tooltip'),
            'includes' => $includes,
        ])->groupBy(fn ($permission) => explode('.', $permission['key'])[0])->all();
    }
}
