<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Settings\Concerns\ValidatesRoleName;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    use ValidatesRoleName;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function ignoredRole(): ?Role
    {
        return null;
    }
}
