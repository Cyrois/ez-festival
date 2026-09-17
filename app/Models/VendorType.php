<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sort_order'])]
class VendorType extends Model
{
    public function engagements(): HasMany
    {
        return $this->hasMany(VendorEngagement::class);
    }
}
