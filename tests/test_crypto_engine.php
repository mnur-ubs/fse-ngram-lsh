<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Crypto/AesCipher.php';
require_once __DIR__ . '/../src/Crypto/HmacTrapdoor.php';
require_once __DIR__ . '/../src/Tokenizer/NgramTokenizer.php';
require_once __DIR__ . '/../src/Lsh/MinHashEngine.php';
require_once __DIR__ . '/../src/Filter/ClientSideFilter.php';

use Fkse\Crypto\AesCipher;
use Fkse\Crypto\HmacTrapdoor;
use Fkse\Tokenizer\NgramTokenizer;
use Fkse\Lsh\MinHashEngine;
use Fkse\Filter\ClientSideFilter;

function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
        echo "[FAIL] {$message}\n";
        exit(1);
    }
    echo "[PASS] {$message}\n";
}

$secretKey = "test_secret_key_12345";

// 1. Test AES-256-CBC
$plaintext = "Diabetes Mellitus Tipe 2";
$cipher1 = AesCipher::encrypt($plaintext, $secretKey);
$cipher2 = AesCipher::encrypt($plaintext, $secretKey);
assertCondition($cipher1 !== $cipher2, "AES encryption produces distinct ciphertexts due to random IV");
assertCondition(AesCipher::decrypt($cipher1, $secretKey) === $plaintext, "AES decryption round-trip succeeds");
assertCondition(AesCipher::decrypt($cipher2, $secretKey) === $plaintext, "AES decryption round-trip succeeds on second ciphertext");
assertCondition(AesCipher::decrypt($cipher1, "wrong_key") === null, "AES decryption fails with invalid key");

// 2. Test HMAC Trapdoor
$trapdoor1 = HmacTrapdoor::generate("diabetes", $secretKey);
$trapdoor2 = HmacTrapdoor::generate("diabetes", $secretKey);
$trapdoorDiff = HmacTrapdoor::generate("diabrtes", $secretKey);
assertCondition(strlen($trapdoor1) === 64, "HMAC trapdoor is 64 hex characters (256-bit)");
assertCondition($trapdoor1 === $trapdoor2, "HMAC trapdoor is deterministic for identical input");
assertCondition($trapdoor1 !== $trapdoorDiff, "HMAC trapdoor differs completely on single-character typo");

// 3. Test N-Gram Tokenizer
$bigrams = NgramTokenizer::extractBigrams("kolera");
// $kolera$ -> $k, ko, ol, le, er, ra, a$ (7 bigrams)
assertCondition(count($bigrams) === 7, "Word 'kolera' generates 7 bigrams with boundary sentinels");
assertCondition(in_array('$k', $bigrams, true), 'Bigrams include opening sentinel \'$k\'');
assertCondition(in_array('a$', $bigrams, true), "Bigrams include closing sentinel 'a$'");

$keywords = NgramTokenizer::extractKeywords("Cholera due to Vibrio cholerae 01");
assertCondition(count($keywords) >= 4, "Keyword extraction splits text correctly and filters short tokens");

// 4. Test MinHash Engine
$sig3 = MinHashEngine::getSignature("tuberculosis", 3);
$sig5 = MinHashEngine::getSignature("tuberculosis", 5);
assertCondition(count($sig3) === 3, "MinHash produces exactly k=3 signature values");
assertCondition(count($sig5) === 5, "MinHash produces exactly k=5 signature values");
$bands = MinHashEngine::getBandTokens("tuberculosis", 3);
assertCondition(count($bands) === 3 && str_starts_with($bands[0], 'band_0_'), "Band tokens properly formatted");

// 5. Test Client-Side Filter
$typoQuery = "diabrtes";
$targetText = "Non-insulin-dependent diabetes mellitus";
assertCondition(ClientSideFilter::isValidMatch($typoQuery, $targetText, 85.0), "Client filter accepts 'diabrtes' against 'diabetes' at tau=85%");
assertCondition(!ClientSideFilter::isValidMatch("pneumonia", $targetText, 85.0), "Client filter rejects completely irrelevant term 'pneumonia'");

echo "\n>>> ALL CRYPTO & TOKENIZER ENGINE TESTS PASSED! <<<\n";
