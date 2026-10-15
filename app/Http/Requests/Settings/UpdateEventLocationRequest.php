<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Settings\Concerns\EventLocationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventLocationRequest extends FormRequest
{
    use EventLocationRules;
}
