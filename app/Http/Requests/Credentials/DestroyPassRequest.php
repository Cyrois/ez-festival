<?php

namespace App\Http\Requests\Credentials;

use App\Models\PassType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('credentials.passes.edit');
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $pass = $this->route('passType');
            if ($pass instanceof PassType && $pass->event_id === $this->route('event')->id
                && $pass->event_id === $this->user()->effectiveEvent()?->id && $pass->assignments()->exists()) {
                $validator->errors()->add('pass_type', __('credentials.passes.errors.delete_blocked'));
            }
        });
    }
}
