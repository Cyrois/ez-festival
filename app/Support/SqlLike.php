<?php

namespace App\Support;

class SqlLike
{
    public static function escape(string $value, string $escape = '!'): string
    {
        return str_replace(
            [$escape, '%', '_'],
            [$escape.$escape, $escape.'%', $escape.'_'],
            $value,
        );
    }
}
