<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyIssuedEntitlementSource extends Model
{
    protected $primaryKey = 'issued_entitlement_id';

    public $incrementing = false;

    public $timestamps = false;

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
