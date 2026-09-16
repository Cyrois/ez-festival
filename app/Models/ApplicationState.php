<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['default_event_id', 'setup_completed_at'])]
class ApplicationState extends Model
{
    protected $table = 'application_state';

    protected function casts(): array
    {
        return [
            'setup_completed_at' => 'datetime',
        ];
    }

    public function defaultEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'default_event_id');
    }
}
