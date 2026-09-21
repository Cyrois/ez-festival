<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'color'])]
class ArtistLabel extends Model
{
    use HasFactory, HasNormalizedName;

    public const COLORS = ['primary', 'secondary', 'success', 'warning', 'danger', 'neutral'];

    public function engagements(): BelongsToMany
    {
        return $this->belongsToMany(ArtistEngagement::class, 'artist_engagement_label_assignments');
    }

    public function passTypes(): BelongsToMany
    {
        return $this->belongsToMany(PassType::class, 'pass_type_label_assignments');
    }

    public function entitlementItems(): BelongsToMany
    {
        return $this->belongsToMany(EntitlementItem::class, 'entitlement_item_label_assignments');
    }
}
