<?php

declare(strict_types=1);

namespace Fkse\Filter;

final class ClientSideFilter
{
    /**
     * Evaluates whether a decrypted text contains a word matching the query within similarity threshold tau.
     *
     * @param string $query Query keyword (possibly with typo)
     * @param string $decryptedText Decrypted plaintext document content
     * @param float $threshold Similarity threshold (percentage, e.g., 85.0 for tau = 85%)
     * @return bool True if valid match (similarity >= threshold), false if false positive
     */
    public static function isValidMatch(string $query, string $decryptedText, float $threshold = 85.0): bool
    {
        $normalizedQuery = strtolower(trim($query));
        $normalizedText  = strtolower(trim($decryptedText));

        if ($normalizedQuery === '' || $normalizedText === '') {
            return false;
        }

        // Exact substring match check
        if (str_contains($normalizedText, $normalizedQuery)) {
            return true;
        }

        $words = str_word_count($normalizedText, 1);
        foreach ($words as $word) {
            similar_text($normalizedQuery, $word, $percent);
            if ($percent >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Backward-compatible helper: returns true if result is a False Positive (to be discarded).
     */
    public static function isFalsePositive(string $query, string $decryptedText, float $threshold = 85.0): bool
    {
        return !self::isValidMatch($query, $decryptedText, $threshold);
    }

    /**
     * Computes the maximum similarity percentage between the query and any word in decryptedText.
     */
    public static function getMaxSimilarity(string $query, string $decryptedText): float
    {
        $normalizedQuery = strtolower(trim($query));
        $normalizedText  = strtolower(trim($decryptedText));

        if ($normalizedQuery === '' || $normalizedText === '') {
            return 0.0;
        }

        if (str_contains($normalizedText, $normalizedQuery)) {
            return 100.0;
        }

        $words = str_word_count($normalizedText, 1);
        $maxSimilarity = 0.0;

        foreach ($words as $word) {
            similar_text($normalizedQuery, $word, $percent);
            if ($percent > $maxSimilarity) {
                $maxSimilarity = (float) $percent;
            }
        }

        return round($maxSimilarity, 2);
    }
}
