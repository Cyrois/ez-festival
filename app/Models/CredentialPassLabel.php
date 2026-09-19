<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'name_key', 'color'])]
class CredentialPassLabel extends Model
{
    use HasNormalizedName;

    public const COLORS = ['primary', 'secondary', 'success', 'warning', 'danger', 'neutral'];

    public function passes(): BelongsToMany
    {
        return $this->belongsToMany(
            CredentialPass::class,
            'credential_pass_label_assignments',
        );
    }
}
