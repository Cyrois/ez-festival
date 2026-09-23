<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'name_key', 'color'])]
class PassTypeLabel extends Model
{
    use HasNormalizedName;

    public function passTypes(): BelongsToMany
    {
        return $this->belongsToMany(PassType::class, 'pass_type_label_assignments');
    }
}
