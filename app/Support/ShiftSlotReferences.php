<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Collection;

final class ShiftSlotReferences
{
    /**
     * Check references in batches, both during validation and inside the write transaction.
     * An off role may remain on its own saved slot, but cannot be newly selected.
     *
     * @return array<string, string>
     */
    public static function errors(array $slots, Collection $existing, bool $lock = false): array
    {
        $roles = Role::query()->whereIn('id', array_column($slots, 'role_id'))
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'active'])->keyBy('id');
        $errors = [];
        foreach ($slots as $index => $data) {
            $slot = isset($data['id']) ? $existing->get((int) $data['id']) : null;
            if (isset($data['id']) && $slot === null) {
                $errors["slots.$index.id"] = __('team.scheduling.slots.errors.foreign_slot');
            }

            $retained = $slot !== null && (int) $slot->role_id === (int) $data['role_id'];
            if (! $retained && ! ($roles->get((int) $data['role_id'])?->active ?? false)) {
                $errors["slots.$index.role_id"] = __('team.scheduling.slots.errors.role_unavailable');
            }
        }

        return $errors;
    }

    public static function options(): array
    {
        return Role::query()->where('active', true)->orderBy('name_key')
            ->get(['id', 'name'])->toArray();
    }
}
