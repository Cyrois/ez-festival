<?php

namespace App\Services;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use App\Models\VendorEngagementNote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function addToEvent(Event $event, array $data, Collection $customFields): VendorEngagement
    {
        return DB::transaction(function () use ($event, $data, $customFields): VendorEngagement {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $name = $data['name'];
            $nameKey = Vendor::normalizeName($name);

            $vendor = Vendor::query()->where('name_key', $nameKey)->first();

            try {
                if (! $vendor) {
                    $vendor = Vendor::query()->create([
                        'name' => $name,
                        'name_key' => $nameKey,
                    ]);
                }

                if ($event->vendorEngagements()->whereBelongsTo($vendor)->exists()) {
                    throw ValidationException::withMessages([
                        'name' => __('vendors.errors.already_added'),
                    ]);
                }

                $engagement = $vendor->engagements()->create([
                    'event_id' => $event->id,
                    'vendor_type_id' => $data['vendor_type_id'] ?? null,
                    'status' => $data['status'] ?? 'idea',
                ]);

                $this->syncCustomFieldValues(
                    $vendor,
                    $customFields,
                    $data['custom_fields'] ?? [],
                );

                return $engagement;
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('vendors.errors.already_added'),
                ]);
            }
        });
    }

    /**
     * @param  Collection<int, CustomField>  $customFields
     * @param  array<int|string, mixed>  $values
     */
    private function syncCustomFieldValues(Vendor $vendor, Collection $customFields, array $values): void
    {
        foreach ($customFields as $field) {
            if (! array_key_exists($field->id, $values)) {
                continue;
            }

            $value = $values[$field->id];

            if ($field->type !== 'checkbox' && ($value === null || $value === '')) {
                $vendor->customFieldValues()
                    ->where('custom_field_id', $field->id)
                    ->delete();

                continue;
            }

            $fieldValue = $vendor->customFieldValues()->firstOrNew([
                'custom_field_id' => $field->id,
            ]);
            $fieldValue->setTypedValue($field, $value);
            $fieldValue->save();
        }
    }

    /** @param array<string, mixed> $data */
    public function updateEngagement(VendorEngagement $engagement, array $data): void
    {
        DB::transaction(function () use ($engagement, $data): void {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();
            $vendor = Vendor::query()->lockForUpdate()->findOrFail($engagement->vendor_id);
            $nameKey = Vendor::normalizeName($data['name']);
            $conflict = Vendor::query()->where('name_key', $nameKey)->whereKeyNot($vendor->id)->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['name' => __('vendors.errors.name_taken')]);
            }

            try {
                $vendor->update(['name' => $data['name'], 'name_key' => $nameKey]);
                $engagement->update([
                    'status' => $data['status'],
                    'vendor_type_id' => $data['vendor_type_id'] ?? null,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['name' => __('vendors.errors.name_taken')]);
            }
        });
    }

    public function addNote(VendorEngagement $engagement, User $user, string $body): VendorEngagementNote
    {
        return DB::transaction(function () use ($engagement, $user, $body): VendorEngagementNote {
            $event = Event::query()->lockForUpdate()->findOrFail($engagement->event_id);
            $event->ensureWritable();

            return $engagement->notes()->create(['user_id' => $user->id, 'body' => $body]);
        });
    }
}
