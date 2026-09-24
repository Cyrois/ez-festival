<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shift_template_id', 'event_id', 'shift_role_id', 'headcount'])]
class ShiftTemplateRole extends Model
{
    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function shiftRole(): BelongsTo
    {
        return $this->belongsTo(ShiftRole::class);
    }
}
