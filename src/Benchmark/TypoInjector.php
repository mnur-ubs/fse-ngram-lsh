<?php

declare(strict_types=1);

namespace Fkse\Benchmark;

final class TypoInjector
{
    public const ERROR_TYPES = ['omission', 'insertion', 'substitution', 'transposition'];

    public const PHONETIC_PATTERNS = [
        'ph' => 'f',
        'f'  => 'ph',
        'th' => 't',
        'rh' => 'r',
        'ch' => 'k',
        'c'  => 'k',
        'k'  => 'c',
        'y'  => 'i',
        'ae' => 'e',
        'oe' => 'e',
    ];

    /**
     * Injects a single typographical error (Edit Distance d=1).
     *
     * @param string $word Input word
     * @param string|null $type Specific typo type ('omission', 'insertion', 'substitution', 'transposition')
     * @return array{word: string, original: string, type: string, position: int, edit_distance: int}
     */
    public static function inject(string $word, ?string $type = null): array
    {
        $normalized = strtolower(trim($word));
        $len = strlen($normalized);
        if ($len < 4) {
            return ['word' => $normalized, 'original' => $normalized, 'type' => 'none', 'position' => 0, 'edit_distance' => 0];
        }

        $chosenType = $type !== null && in_array($type, self::ERROR_TYPES, true)
            ? $type
            : self::ERROR_TYPES[array_rand(self::ERROR_TYPES)];

        $pos = rand(1, $len - 2);
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        $mutated = $normalized;

        switch ($chosenType) {
            case 'omission':
                $mutated = substr_replace($normalized, '', $pos, 1);
                break;

            case 'insertion':
                $char = $chars[rand(0, 25)];
                $mutated = substr_replace($normalized, $char, $pos, 0);
                break;

            case 'substitution':
                $cur = $normalized[$pos];
                do {
                    $char = $chars[rand(0, 25)];
                } while ($char === $cur);
                $mutated = substr_replace($normalized, $char, $pos, 1);
                break;

            case 'transposition':
                $pos = min($pos, $len - 2);
                $c1 = $normalized[$pos];
                $c2 = $normalized[$pos + 1];
                $mutated = substr($normalized, 0, $pos) . $c2 . $c1 . substr($normalized, $pos + 2);
                break;
        }

        return [
            'word'          => $mutated,
            'original'      => $normalized,
            'type'          => $chosenType,
            'position'      => $pos,
            'edit_distance' => 1,
        ];
    }

    /**
     * Injects a compound typographical error (Edit Distance d=2: two sequential mutations).
     *
     * @return array{word: string, original: string, type: string, position: int, edit_distance: int}
     */
    public static function injectCompound(string $word): array
    {
        $step1 = self::inject($word);
        $step2 = self::inject($step1['word']);

        return [
            'word'          => $step2['word'],
            'original'      => strtolower(trim($word)),
            'type'          => 'compound_d2',
            'position'      => $step1['position'],
            'edit_distance' => 2,
        ];
    }

    /**
     * Applies medical phonetic/orthographic variation (Latin <-> Indonesian clinical loan words).
     *
     * @return array{word: string, original: string, type: string, position: int, edit_distance: int}
     */
    public static function injectPhonetic(string $word): array
    {
        $normalized = strtolower(trim($word));
        foreach (self::PHONETIC_PATTERNS as $pattern => $replacement) {
            if (str_contains($normalized, $pattern)) {
                $pos = (int) strpos($normalized, $pattern);
                $mutated = substr_replace($normalized, $replacement, $pos, strlen($pattern));
                return [
                    'word'          => $mutated,
                    'original'      => $normalized,
                    'type'          => "phonetic_{$pattern}_to_{$replacement}",
                    'position'      => $pos,
                    'edit_distance' => 1,
                ];
            }
        }

        // Fallback to standard substitution if no phonetic pair exists
        $fallback = self::inject($normalized, 'substitution');
        $fallback['type'] = 'orthographic_mutation';
        return $fallback;
    }
}
