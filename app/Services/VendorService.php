<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Vendor;
use App\Models\VendorEngagement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function addToEvent(Event $event, array $data): VendorEngagement
    {
        return DB::transaction(function () use ($event, $data): VendorEngagement {
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

                return $vendor->engagements()->create([
                    'event_id' => $event->id,
                    'vendor_type_id' => $data['vendor_type_id'] ?? null,
                    'status' => $data['status'] ?? 'idea',
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => __('vendors.errors.already_added'),
                ]);
            }
        });
    }
}
