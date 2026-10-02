<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\RoleRepository;
use App\Support\Permissions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(private readonly RoleRepository $roles) {}

    public function create(string $name, array $permissions): Role
    {
        return $this->guardUniqueName($name, null, fn (): Role => Role::query()->create([
            'name' => $name,
            'permissions' => Permissions::expand($permissions),
        ]));
    }

    public function update(Role $role, string $name, array $permissions): void
    {
        $this->guardUniqueName($name, $role, fn (): bool => $role->update([
            'name' => $name,
            'permissions' => Permissions::expand($permissions),
        ]));
    }

    public function rename(Role $role, string $name): void
    {
        $this->update($role, $name, $role->permissions);
    }

    public function setActive(Role $role, bool $active): void
    {
        $role->update(['active' => $active]);
    }

    /**
     * The Form Request checks names first; the unique index is the last line of defence
     * when two requests race for the same name. Only a real name clash becomes a
     * validation error; any other unique violation is rethrown.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function guardUniqueName(string $name, ?Role $ignore, callable $write): mixed
    {
        try {
            return DB::transaction($write);
        } catch (UniqueConstraintViolationException $exception) {
            $clash = $this->roles->findByName($name, $ignore);

            if ($clash === null) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'name' => __('settings.roles.validation.name_taken'),
                'name_match' => $clash->name,
            ]);
        }
    }
}
