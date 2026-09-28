<?php

namespace App\Support;

use Normalizer;

/**
 * One source of truth for how role names are cleaned and compared.
 */
class RoleName
{
    /**
     * Display form: drop invisible format characters (zero-width space/joiners, word joiner,
     * BOM, soft hyphen, …), trim the ends and squash repeated inner whitespace to one space.
     */
    public static function clean(string $name): string
    {
        $name = Normalizer::normalize($name, Normalizer::FORM_C) ?: $name;
        $name = (string) preg_replace('/\p{Cf}+/u', '', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Comparison key: the clean name in lower case. Accents are kept, so "Café" ≠ "Cafe".
     */
    public static function key(string $name): string
    {
        return mb_strtolower(self::clean($name));
    }
}
