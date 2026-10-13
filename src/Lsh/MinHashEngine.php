<?php

declare(strict_types=1);

namespace Fkse\Lsh;

use Fkse\Tokenizer\NgramTokenizer;

final class MinHashEngine
{
    /**
     * Computes the k-dimensional MinHash signature for a given word based on its bigrams.
     *
     * @param int $numHashes Number of hash permutations (k)
     * @return list<int>
     */
    public static function getSignature(string $word, int $numHashes = 3): array
    {
        $bigrams = NgramTokenizer::extractBigrams($word);
        if (empty($bigrams)) {
            return array_fill(0, $numHashes, 0);
        }

        $signature = [];
        for ($i = 0; $i < $numHashes; $i++) {
            $minVal = PHP_INT_MAX;
            foreach ($bigrams as $bg) {
                // Deterministic 32-bit integer hash permutation
                $hashVal = (int) hexdec(substr(md5($i . '_' . $bg), 0, 8));
                if ($hashVal < $minVal) {
                    $minVal = $hashVal;
                }
            }
            $signature[] = $minVal;
        }

        return $signature;
    }

    /**
     * Generates band tokens for the MinHash signature.
     * Format: "band_{index}_{signature_value}"
     *
     * @return list<string>
     */
    public static function getBandTokens(string $word, int $numHashes = 3): array
    {
        $signature = self::getSignature($word, $numHashes);
        $tokens = [];
        foreach ($signature as $idx => $sigVal) {
            $tokens[] = "band_{$idx}_{$sigVal}";
        }
        return $tokens;
    }
}
