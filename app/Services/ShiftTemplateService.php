<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ShiftTemplate;
use App\Models\ShiftTemplateRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftTemplateService
{
    /**
     * @param  array{name: string, location_id: int, roles: list<array{shift_role_id: int, headcount: int}>}  $data
     */
    public function create(Event $event, array $data): ShiftTemplate
    {
        return DB::transaction(function () use ($event, $data): ShiftTemplate {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $template = $event->shiftTemplates()->create([
                'location_id' => $data['location_id'],
                'name' => $data['name'],
            ]);

            $this->syncRoleLines($template, $event, $data['roles']);

            return $template->load(['roleLines.shiftRole', 'location']);
        });
    }

    /**
     * @param  array{name: string, location_id: int, roles: list<array{shift_role_id: int, headcount: int}>}  $data
     */
    public function update(ShiftTemplate $template, array $data): void
    {
        DB::transaction(function () use ($template, $data): void {
            $template = ShiftTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $event = Event::query()->lockForUpdate()->findOrFail($template->event_id);
            $event->ensureWritable();

            $template->update([
                'location_id' => $data['location_id'],
                'name' => $data['name'],
            ]);

            $template->roleLines()->delete();
            $this->syncRoleLines($template, $event, $data['roles']);
        });
    }

    public function destroy(ShiftTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            $template = ShiftTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $event = Event::query()->lockForUpdate()->findOrFail($template->event_id);
            $event->ensureWritable();

            $template->roleLines()->delete();
            $template->delete();
        });
    }

    /**
     * @param  list<array{shift_role_id: int, headcount: int}>  $roles
     */
    private function syncRoleLines(ShiftTemplate $template, Event $event, array $roles): void
    {
        if ($roles === []) {
            throw ValidationException::withMessages([
                'roles' => __('team.configure.templates.errors.roles_required'),
            ]);
        }

        foreach ($roles as $line) {
            ShiftTemplateRole::query()->create([
                'shift_template_id' => $template->id,
                'event_id' => $event->id,
                'shift_role_id' => $line['shift_role_id'],
                'headcount' => $line['headcount'],
            ]);
        }
    }
}
