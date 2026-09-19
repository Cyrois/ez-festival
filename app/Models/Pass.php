<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['event_id', 'name', 'name_key', 'max_assignments'])]
class Pass extends Model
{
    use HasNormalizedName;

    protected function casts(): array
    {
        return [
            'max_assignments' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(
            PassLabel::class,
            'pass_label_assignments',
        );
    }

    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'custom_fieldable');
    }
}
