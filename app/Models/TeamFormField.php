<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_form_id', 'custom_field_id', 'key', 'required', 'sort_order'])]
class TeamFormField extends Model
{
    public const KEY_NAME = 'name';

    public const KEY_EMAIL = 'email';

    public const KEY_PHONE = 'phone';

    public const KEY_EMPLOYMENT_TYPE = 'employment_type';

    public const BUILTIN_KEYS = [
        self::KEY_NAME,
        self::KEY_EMAIL,
        self::KEY_PHONE,
        self::KEY_EMPLOYMENT_TYPE,
    ];

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(TeamForm::class, 'team_form_id');
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function isCustom(): bool
    {
        return $this->custom_field_id !== null;
    }
}
