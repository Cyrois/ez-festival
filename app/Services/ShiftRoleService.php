<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ShiftRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftRoleService
{
    /**
     * @param  array{name: string}  $data
     */
    public function create(Event $event, array $data): ShiftRole
    {
        return DB::transaction(function () use ($event, $data): ShiftRole {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            try {
                return $event->shiftRoles()->create([
                    'name' => $data['name'],
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('team.configure.shift_roles.errors.name_taken'),
                ]);
            }
        });
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(ShiftRole $shiftRole, array $data): void
    {
        DB::transaction(function () use ($shiftRole, $data): void {
            $shiftRole = ShiftRole::query()->lockForUpdate()->findOrFail($shiftRole->id);
            $event = Event::query()->lockForUpdate()->findOrFail($shiftRole->event_id);
            $event->ensureWritable();

            try {
                $shiftRole->update(['name' => $data['name']]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('team.configure.shift_roles.errors.name_taken'),
                ]);
            }
        });
    }

    public function destroy(ShiftRole $shiftRole): void
    {
        DB::transaction(function () use ($shiftRole): void {
            $shiftRole = ShiftRole::query()->lockForUpdate()->findOrFail($shiftRole->id);
            $event = Event::query()->lockForUpdate()->findOrFail($shiftRole->event_id);
            $event->ensureWritable();

            if ($shiftRole->templateRoles()->exists()) {
                throw ValidationException::withMessages([
                    'shift_role' => __('team.configure.shift_roles.errors.in_use'),
                ]);
            }

            $shiftRole->delete();
        });
    }
}
