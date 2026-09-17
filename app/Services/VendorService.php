<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Vendor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function addToEvent(Event $event, array $data): Vendor
    {
        return DB::transaction(function () use ($event, $data): Vendor {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $event->ensureWritable();

            $name = $data['name'];
            $nameKey = Vendor::normalizeName($name);

            if ($event->vendors()->where('name_key', $nameKey)->exists()) {
                throw ValidationException::withMessages([
                    'name' => __('vendors.errors.already_added'),
                ]);
            }

            try {
                return $event->vendors()->create([
                    'name' => $name,
                    'name_key' => $nameKey,
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
