<?php

declare(strict_types=1);

namespace Fkse\Tokenizer;

final class NgramTokenizer
{
    /**
     * Extracts unique n-grams (default: bigrams, n=2) from a normalized word with boundary sentinels ($).
     *
     * @return list<string>
     */
    public static function extractBigrams(string $word, int $n = 2): array
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $word));
        if ($normalized === '') {
            return [];
        }

        $padded = '$' . $normalized . '$';
        $length = strlen($padded);
        $ngrams = [];

        for ($i = 0; $i <= $length - $n; $i++) {
            $ngrams[] = substr($padded, $i, $n);
        }

        return array_values(array_unique($ngrams));
    }

    /**
     * Splits text into normalized unique keywords of minimum length.
     *
     * @return list<string>
     */
    public static function extractKeywords(string $text, int $minLength = 3): array
    {
        $words = preg_split('/[^a-z0-9]+/i', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            return [];
        }

        $valid = [];
        foreach ($words as $w) {
            if (strlen($w) >= $minLength) {
                $valid[] = $w;
            }
        }

        return array_values(array_unique($valid));
    }
}
