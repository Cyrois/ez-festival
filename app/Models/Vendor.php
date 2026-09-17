<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'vendor_type_id', 'name', 'name_key', 'status'])]
class Vendor extends Model
{
    use HasNormalizedName;

    public const STATUSES = ['idea', 'outreach', 'negotiating', 'contract_sent', 'confirmed', 'declined'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vendorType(): BelongsTo
    {
        return $this->belongsTo(VendorType::class);
    }
}
