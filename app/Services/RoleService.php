<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function create(string $name): Role
    {
        return $this->guardUniqueName(fn (): Role => Role::query()->create(['name' => $name]));
    }

    public function rename(Role $role, string $name): void
    {
        $this->guardUniqueName(fn (): bool => $role->update(['name' => $name]));
    }

    public function setActive(Role $role, bool $active): void
    {
        $role->update(['active' => $active]);
    }

    /**
     * The Form Request checks names first; the unique index is the last line of defence
     * when two requests race for the same name.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function guardUniqueName(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => __('settings.roles.validation.name_taken'),
            ]);
        }
    }
}
