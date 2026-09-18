<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'name_key'])]
class Vendor extends Model
{
    use HasNormalizedName;

    public function engagements(): HasMany
    {
        return $this->hasMany(VendorEngagement::class);
    }

    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'custom_fieldable');
    }

    public function engagementFor(Event $event): ?VendorEngagement
    {
        return $this->engagements()->whereBelongsTo($event)->first();
    }
}
