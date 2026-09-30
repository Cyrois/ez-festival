<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class TeamPassword
{
    public static function rule(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    public static function temporary(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';

        do {
            $password = collect(range(1, 16))
                ->map(fn (): string => $characters[random_int(0, strlen($characters) - 1)])
                ->implode('');
        } while (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/[0-9]/', $password));

        return $password;
    }
}
