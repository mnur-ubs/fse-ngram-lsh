<?php

declare(strict_types=1);

set_time_limit(0);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Crypto/AesCipher.php';
require_once __DIR__ . '/src/Crypto/HmacTrapdoor.php';
require_once __DIR__ . '/src/Tokenizer/NgramTokenizer.php';
require_once __DIR__ . '/src/Lsh/MinHashEngine.php';
require_once __DIR__ . '/src/Filter/ClientSideFilter.php';

use Fkse\Config\Database;
use Fkse\Crypto\AesCipher;
use Fkse\Crypto\HmacTrapdoor;
use Fkse\Tokenizer\NgramTokenizer;
use Fkse\Lsh\MinHashEngine;
use Fkse\Filter\ClientSideFilter;

$pdo = Database::getConnection();
$secretKey = Database::getSecretKey();

// Fetch current index row counts for UI metrics display
$count_exact = (int) $pdo->query("SELECT COUNT(*) FROM exact_index")->fetchColumn();
$count_ngram = (int) $pdo->query("SELECT COUNT(*) FROM ngram_index")->fetchColumn();
$count_lsh   = (int) $pdo->query("SELECT COUNT(*) FROM lsh_index")->fetchColumn();

$search_query = trim((string) ($_POST['query'] ?? ''));
$results = ['exact' => [], 'ngram' => [], 'lsh' => []];
$stats = [
    'exact' => ['time' => 0.0, 'fetched' => 0, 'dropped' => 0, 'valid' => 0, 'accuracy' => 0.0, 'fp_list' => []],
    'ngram' => ['time' => 0.0, 'fetched' => 0, 'dropped' => 0, 'valid' => 0, 'accuracy' => 0.0, 'fp_list' => []],
    'lsh'   => ['time' => 0.0, 'fetched' => 0, 'dropped' => 0, 'valid' => 0, 'accuracy' => 0.0, 'fp_list' => []],
];

/**
 * Client-Side Decryption and Filtering Service
 *
 * @param list<string> $rawDocIds
 * @return list<array{doc_id: string, name_id: string, name_en: string, cipher: string}>
 */
function process_client_filtering(PDO $pdo, array $rawDocIds, string $query, string $secretKey, array &$statsOutput, float $threshold = 85.0): array
{
    $filteredResults = [];
    $falsePositivesList = [];
    $falsePositiveCount = 0;
    $totalFetched = count($rawDocIds);

    if ($totalFetched > 0) {
        $placeholders = implode(',', array_fill(0, $totalFetched, '?'));
        $stmt = $pdo->prepare("SELECT doc_id, enc_name_id, enc_name_en FROM icds_encrypted WHERE doc_id IN ($placeholders)");
        $stmt->execute($rawDocIds);
        $encryptedDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($encryptedDocs as $row) {
            $docId = (string) $row['doc_id'];
            $plaintextId = (string) (AesCipher::decrypt((string) $row['enc_name_id'], $secretKey) ?? '');
            $plaintextEn = (string) (AesCipher::decrypt((string) $row['enc_name_en'], $secretKey) ?? '');

            $isMatchId = ClientSideFilter::isValidMatch($query, $plaintextId, $threshold);
            $isMatchEn = ClientSideFilter::isValidMatch($query, $plaintextEn, $threshold);

            if (!$isMatchId && !$isMatchEn) {
                $falsePositiveCount++;
                if (count($falsePositivesList) < 10) {
                    $falsePositivesList[] = [
                        'doc_id'  => $docId,
                        'name_id' => $plaintextId,
                        'name_en' => $plaintextEn,
                    ];
                }
            } else {
                $filteredResults[] = [
                    'doc_id'  => $docId,
                    'name_id' => $plaintextId,
                    'name_en' => $plaintextEn,
                    'cipher'  => substr((string) $row['enc_name_id'], 0, 25) . '...',
                ];
            }
        }
    }

    $validCount = count($filteredResults);
    $accuracy = ($totalFetched > 0) ? round(($validCount / $totalFetched) * 100, 2) : 0.0;

    $statsOutput = [
        'fetched'  => $totalFetched,
        'dropped'  => $falsePositiveCount,
        'valid'    => $validCount,
        'accuracy' => $accuracy,
        'fp_list'  => $falsePositivesList,
        'time'     => $statsOutput['time'] ?? 0.0,
    ];

    return $filteredResults;
}

if ($search_query !== '') {
    // -------------------------------------------------------------
    // A. EXACT MATCH SEARCH (Baseline)
    // -------------------------------------------------------------
    $tStart = (hrtime(true) / 1e9);
    $trapdoorExact = HmacTrapdoor::generate($search_query, $secretKey);
    $stmtExact = $pdo->prepare("SELECT DISTINCT doc_id FROM exact_index WHERE trapdoor = ?");
    $stmtExact->execute([$trapdoorExact]);
    $exactRaw = $stmtExact->fetchAll(PDO::FETCH_COLUMN);

    $results['exact'] = process_client_filtering($pdo, $exactRaw, $search_query, $secretKey, $stats['exact']);
    $stats['exact']['time'] = round(((hrtime(true) / 1e9) - $tStart) * 1000, 2);

    // -------------------------------------------------------------
    // B. N-GRAM (BI-GRAM) FUZZY SEARCH
    // -------------------------------------------------------------
    $tStart = (hrtime(true) / 1e9);
    $queryBigrams = NgramTokenizer::extractBigrams($search_query);
    $trapdoorsNgram = array_map(fn($bg) => HmacTrapdoor::generate($bg, $secretKey), $queryBigrams);

    $ngramRaw = [];
    if (!empty($trapdoorsNgram)) {
        $placeholders = implode(',', array_fill(0, count($trapdoorsNgram), '?'));
        $stmtNgram = $pdo->prepare("SELECT doc_id, COUNT(*) as hits FROM ngram_index WHERE trapdoor IN ($placeholders) GROUP BY doc_id");
        $stmtNgram->execute($trapdoorsNgram);
        $minHits = count($queryBigrams) * 0.4;
        foreach ($stmtNgram->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((float) $row['hits'] >= $minHits) {
                $ngramRaw[] = (string) $row['doc_id'];
            }
        }
    }

    $results['ngram'] = process_client_filtering($pdo, $ngramRaw, $search_query, $secretKey, $stats['ngram']);
    $stats['ngram']['time'] = round(((hrtime(true) / 1e9) - $tStart) * 1000, 2);

    // -------------------------------------------------------------
    // C. LSH (MINHASH) FUZZY SEARCH (k = 3)
    // -------------------------------------------------------------
    $tStart = (hrtime(true) / 1e9);
    $bandTokens = MinHashEngine::getBandTokens($search_query, 3);
    $trapdoorsLsh = array_map(fn($token) => HmacTrapdoor::generate($token, $secretKey), $bandTokens);

    $lshRaw = [];
    if (!empty($trapdoorsLsh)) {
        $placeholders = implode(',', array_fill(0, count($trapdoorsLsh), '?'));
        $stmtLsh = $pdo->prepare("SELECT DISTINCT doc_id FROM lsh_index WHERE trapdoor IN ($placeholders)");
        $stmtLsh->execute($trapdoorsLsh);
        $lshRaw = $stmtLsh->fetchAll(PDO::FETCH_COLUMN);
    }

    $results['lsh'] = process_client_filtering($pdo, $lshRaw, $search_query, $secretKey, $stats['lsh']);
    $stats['lsh']['time'] = round(((hrtime(true) / 1e9) - $tStart) * 1000, 2);
}
