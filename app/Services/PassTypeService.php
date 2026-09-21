<?php

namespace App\Services;

use App\Models\ArtistLabel;
use App\Models\CustomField;
use App\Models\EntitlementItem;
use App\Models\Event;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PassTypeService
{
    public function __construct(private readonly CustomFieldValueService $customFieldValueService) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    public function create(Event $event, array $data, Collection $customFields): PassType
    {
        return DB::transaction(function () use ($event, $data, $customFields): PassType {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $passType = $event->passTypes()->create([
                'name' => $data['name'],
                'max_assignments' => $data['max_assignments'] ?? null,
            ]);

            $this->syncDetails($passType, $data, $customFields, $event);

            return $passType;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    public function update(PassType $passType, array $data, Collection $customFields): void
    {
        DB::transaction(function () use ($passType, $data, $customFields): void {
            $passType = PassType::query()->lockForUpdate()->findOrFail($passType->id);
            $event = Event::query()->lockForUpdate()->findOrFail($passType->event_id);
            $event->ensureWritable();

            $maxAssignments = $data['max_assignments'] ?? null;
            if ($maxAssignments !== null && $passType->assignments()->count() > $maxAssignments) {
                throw ValidationException::withMessages([
                    'max_assignments' => __('credentials.passes.errors.capacity_below_assigned'),
                ]);
            }

            $passType->update([
                'name' => $data['name'],
                'max_assignments' => $maxAssignments,
            ]);
            $this->syncDetails($passType, $data, $customFields, $event);
        });
    }

    public function destroy(PassType $passType): void
    {
        DB::transaction(function () use ($passType): void {
            $passType = PassType::query()->lockForUpdate()->findOrFail($passType->id);
            $event = Event::query()->lockForUpdate()->findOrFail($passType->event_id);
            $event->ensureWritable();

            if ($passType->assignments()->exists()) {
                throw ValidationException::withMessages([
                    'pass_type' => __('credentials.passes.errors.delete_blocked'),
                ]);
            }

            $passType->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, CustomField>  $customFields
     */
    private function syncDetails(PassType $passType, array $data, Collection $customFields, Event $event): void
    {
        $labelIds = $data['label_ids'] ?? [];
        foreach ($data['new_labels'] ?? [] as $label) {
            $savedLabel = ArtistLabel::query()->firstOrCreate(
                ['name_key' => ArtistLabel::normalizeName($label['name'])],
                ['name' => $label['name'], 'color' => $label['color']],
            );
            $labelIds[] = $savedLabel->id;
        }
        $passType->labels()->sync(array_values(array_unique($labelIds)));

        $itemIds = $data['entitlement_item_ids'] ?? [];
        if (EntitlementItem::query()->whereIn('id', $itemIds)->where('event_id', '!=', $event->id)->exists()) {
            throw ValidationException::withMessages([
                'entitlement_item_ids' => __('credentials.passes.errors.entitlement_unavailable'),
            ]);
        }
        $passType->entitlements()->delete();
        $passType->entitlements()->createMany(
            array_map(
                fn (int $itemId, int $sortOrder): array => [
                    'entitlement_item_id' => $itemId,
                    'sort_order' => $sortOrder,
                ],
                array_values($itemIds),
                array_keys(array_values($itemIds)),
            ),
        );

        $this->customFieldValueService->sync(
            $passType->customFieldValues(),
            $customFields,
            $data['custom_fields'] ?? [],
            $event->id,
        );
    }
}
