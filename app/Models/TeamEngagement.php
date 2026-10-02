<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['event_id', 'person_id', 'group_id', 'role_id', 'team_form_id', 'status', 'employment_type', 'hourly_pay', 'submitted_at'])]
class TeamEngagement extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (TeamEngagement $engagement): void {
            if ($engagement->shiftAssignments()->exists()) {
                throw ValidationException::withMessages([
                    'team_engagement_id' => __('team.scheduling.assignments.errors.delete_member'),
                ]);
            }
        });
    }

    public const STATUSES = ['applied', 'reviewing', 'hired', 'declined'];

    public const EMPLOYMENT_TYPES = ['volunteer', 'paid'];

    public static function startingStatus(): string
    {
        return self::STATUSES[0];
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    protected function casts(): array
    {
        return [
            'hourly_pay' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function teamForm(): BelongsTo
    {
        return $this->belongsTo(TeamForm::class);
    }

    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'custom_fieldable');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TeamEngagementNote::class);
    }

    public function passAssignments(): HasMany
    {
        return $this->hasMany(PassAssignment::class);
    }
}
