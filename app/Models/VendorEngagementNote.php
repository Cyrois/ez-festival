<?php

namespace App\Models;

use Database\Factories\VendorEngagementNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vendor_engagement_id', 'user_id', 'body'])]
class VendorEngagementNote extends Model
{
    /** @use HasFactory<VendorEngagementNoteFactory> */
    use HasFactory;

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(VendorEngagement::class, 'vendor_engagement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
