<?php

declare(strict_types=1);

namespace Fkse\Crypto;

final class HmacTrapdoor
{
    /**
     * Normalizes a keyword by lowercasing and stripping non-alphanumeric characters.
     */
    public static function normalize(string $keyword): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $keyword));
    }

    /**
     * Generates a 256-bit hexadecimal HMAC-SHA256 trapdoor digest.
     */
    public static function generate(string $token, string $key): string
    {
        return hash_hmac('sha256', strtolower(trim($token)), $key);
    }
}
