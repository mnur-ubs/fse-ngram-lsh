<?php

declare(strict_types=1);

namespace Fkse\Benchmark;

use Fkse\Config\Database;
use Fkse\Crypto\AesCipher;
use Fkse\Crypto\HmacTrapdoor;
use Fkse\Tokenizer\NgramTokenizer;
use Fkse\Lsh\MinHashEngine;
use Fkse\Filter\ClientSideFilter;
use PDO;

final class SensitivityBenchmark
{
    private PDO $pdo;
    private string $secretKey;

    public function __construct(?PDO $pdo = null, ?string $secretKey = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->secretKey = $secretKey ?? Database::getSecretKey();
    }

    /**
     * Generates N standardized test cases with balanced typo categories.
     *
     * @return list<array{doc_id: string, original_word: string, typo_query: string, typo_type: string, disease_name: string}>
     */
    public function generateQuerySet(int $count = 100): array
    {
        // Sample candidate records with multisyllabic clinical terms
        $stmt = $this->pdo->query("SELECT code, name_en, name_id FROM icds ORDER BY RAND(42) LIMIT 1000");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $testCases = [];
        $types = TypoInjector::ERROR_TYPES;
        $typeIdx = 0;

        foreach ($rows as $row) {
            $wordsEn = NgramTokenizer::extractKeywords((string) $row['name_en'], 6);
            $wordsId = NgramTokenizer::extractKeywords((string) $row['name_id'], 6);
            $words = array_merge($wordsEn, $wordsId);

            foreach ($words as $word) {
                if (strlen($word) < 6) {
                    continue;
                }

                $chosenType = $types[$typeIdx % count($types)];
                $mutation = TypoInjector::inject($word, $chosenType);
                if ($mutation['word'] === $word) {
                    continue;
                }

                $testCases[] = [
                    'doc_id'        => (string) $row['code'],
                    'original_word' => $word,
                    'typo_query'    => $mutation['word'],
                    'typo_type'     => $chosenType,
                    'disease_name'  => (string) $row['name_en'],
                ];

                $typeIdx++;
                break; // 1 word per disease code
            }

            if (count($testCases) >= $count) {
                break;
            }
        }

        return $testCases;
    }

    /**
     * Generates an extended, multidimensional diverse query set covering:
     * 1. Short terms (4-6 chars, d=1): 20 queries
     * 2. Medium terms (7-10 chars, d=1): 20 queries
     * 3. Long terms (>= 11 chars, d=1): 20 queries
     * 4. Compound typos (Edit Distance d=2): 20 queries
     * 5. Clinical phonetic / orthographic loan shifts (ph<->f, c<->k, etc.): 20 queries
     *
     * @return list<array{doc_id: string, original_word: string, typo_query: string, typo_type: string, diversity_dimension: string, term_length: int, disease_name: string}>
     */
    public function generateDiverseQuerySet(): array
    {
        $stmt = $this->pdo->query("SELECT code, name_en, name_id FROM icds ORDER BY RAND(99) LIMIT 2000");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $buckets = [
            'short'    => [], // len 4-6
            'medium'   => [], // len 7-10
            'long'     => [], // len >= 11
            'compound' => [], // d=2
            'phonetic' => [], // phonetic loan shifts
        ];

        foreach ($rows as $row) {
            $wordsEn = NgramTokenizer::extractKeywords((string) $row['name_en'], 4);
            $wordsId = NgramTokenizer::extractKeywords((string) $row['name_id'], 4);
            $words = array_unique(array_merge($wordsEn, $wordsId));

            foreach ($words as $word) {
                $len = strlen($word);

                // 1. Short bucket (4-6 chars, d=1)
                if ($len >= 4 && $len <= 6 && count($buckets['short']) < 20) {
                    $m = TypoInjector::inject($word);
                    if ($m['word'] !== $word) {
                        $buckets['short'][] = [
                            'doc_id'              => (string) $row['code'],
                            'original_word'       => $word,
                            'typo_query'          => $m['word'],
                            'typo_type'           => $m['type'],
                            'diversity_dimension' => 'Short Terms (4-6 chars)',
                            'term_length'         => $len,
                            'disease_name'        => (string) $row['name_en'],
                        ];
                        continue;
                    }
                }

                // 2. Medium bucket (7-10 chars, d=1)
                if ($len >= 7 && $len <= 10 && count($buckets['medium']) < 20) {
                    $m = TypoInjector::inject($word);
                    if ($m['word'] !== $word) {
                        $buckets['medium'][] = [
                            'doc_id'              => (string) $row['code'],
                            'original_word'       => $word,
                            'typo_query'          => $m['word'],
                            'typo_type'           => $m['type'],
                            'diversity_dimension' => 'Medium Terms (7-10 chars)',
                            'term_length'         => $len,
                            'disease_name'        => (string) $row['name_en'],
                        ];
                        continue;
                    }
                }

                // 3. Long bucket (>= 11 chars, d=1)
                if ($len >= 11 && count($buckets['long']) < 20) {
                    $m = TypoInjector::inject($word);
                    if ($m['word'] !== $word) {
                        $buckets['long'][] = [
                            'doc_id'              => (string) $row['code'],
                            'original_word'       => $word,
                            'typo_query'          => $m['word'],
                            'typo_type'           => $m['type'],
                            'diversity_dimension' => 'Long Polysyllabic (>=11 chars)',
                            'term_length'         => $len,
                            'disease_name'        => (string) $row['name_en'],
                        ];
                        continue;
                    }
                }

                // 4. Compound typos (d=2)
                if ($len >= 6 && count($buckets['compound']) < 20) {
                    $m = TypoInjector::injectCompound($word);
                    if ($m['word'] !== $word) {
                        $buckets['compound'][] = [
                            'doc_id'              => (string) $row['code'],
                            'original_word'       => $word,
                            'typo_query'          => $m['word'],
                            'typo_type'           => 'compound_d2',
                            'diversity_dimension' => 'Compound Typo (Edit Distance d=2)',
                            'term_length'         => $len,
                            'disease_name'        => (string) $row['name_en'],
                        ];
                        continue;
                    }
                }

                // 5. Phonetic / Orthographic Latin-Indonesian variations
                if ($len >= 5 && count($buckets['phonetic']) < 20) {
                    $m = TypoInjector::injectPhonetic($word);
                    if ($m['word'] !== $word) {
                        $buckets['phonetic'][] = [
                            'doc_id'              => (string) $row['code'],
                            'original_word'       => $word,
                            'typo_query'          => $m['word'],
                            'typo_type'           => $m['type'],
                            'diversity_dimension' => 'Phonetic/Orthographic Shifts',
                            'term_length'         => $len,
                            'disease_name'        => (string) $row['name_en'],
                        ];
                        continue;
                    }
                }
            }

            if (
                count($buckets['short']) >= 20 &&
                count($buckets['medium']) >= 20 &&
                count($buckets['long']) >= 20 &&
                count($buckets['compound']) >= 20 &&
                count($buckets['phonetic']) >= 20
            ) {
                break;
            }
        }

        $allDiverse = array_merge(
            $buckets['short'],
            $buckets['medium'],
            $buckets['long'],
            $buckets['compound'],
            $buckets['phonetic']
        );

        return $allDiverse;
    }

    /**
     * Executes the primary 100-query benchmark across Exact Match, N-Gram, and LSH.
     *
     * @param list<array> $testCases
     * @return array<string, mixed>
     */
    public function execute100QueryBenchmark(array $testCases): array
    {
        $results = [
            'exact' => ['latency' => [], 'recall' => [], 'retrieved_count' => [], 'filtered_count' => [], 'tp' => 0, 'fp' => 0, 'fn' => 0, 'details' => []],
            'ngram' => ['latency' => [], 'recall' => [], 'retrieved_count' => [], 'filtered_count' => [], 'tp' => 0, 'fp' => 0, 'fn' => 0, 'details' => []],
            'lsh'   => ['latency' => [], 'recall' => [], 'retrieved_count' => [], 'filtered_count' => [], 'tp' => 0, 'fp' => 0, 'fn' => 0, 'details' => []],
        ];

        foreach ($testCases as $idx => $case) {
            $targetDocId = $case['doc_id'];
            $query       = $case['typo_query'];

            // 1. Exact Match
            $resExact = $this->searchExact($query, $targetDocId);
            $results['exact']['latency'][]         = $resExact['latency_total'];
            $results['exact']['recall'][]          = $resExact['is_recalled'] ? 100 : 0;
            $results['exact']['retrieved_count'][] = $resExact['retrieved_count'];
            $results['exact']['filtered_count'][]  = $resExact['filtered_count'];
            if ($resExact['is_recalled']) {
                $results['exact']['tp']++;
            } else {
                $results['exact']['fn']++;
            }
            $results['exact']['fp'] += $resExact['fp_count'];

            // 2. N-Gram (Bi-gram, threshold 0.4)
            $resNgram = $this->searchNgram($query, $targetDocId, 0.4, 85.0);
            $results['ngram']['latency'][]         = $resNgram['latency_total'];
            $results['ngram']['recall'][]          = $resNgram['is_recalled'] ? 100 : 0;
            $results['ngram']['retrieved_count'][] = $resNgram['retrieved_count'];
            $results['ngram']['filtered_count'][]  = $resNgram['filtered_count'];
            if ($resNgram['is_recalled']) {
                $results['ngram']['tp']++;
            } else {
                $results['ngram']['fn']++;
            }
            $results['ngram']['fp'] += $resNgram['fp_count'];

            // 3. LSH (MinHash, k=3, tau=85%)
            $resLsh = $this->searchLsh($query, $targetDocId, 3, 85.0);
            $results['lsh']['latency'][]         = $resLsh['latency_total'];
            $results['lsh']['recall'][]          = $resLsh['is_recalled'] ? 100 : 0;
            $results['lsh']['retrieved_count'][] = $resLsh['retrieved_count'];
            $results['lsh']['filtered_count'][]  = $resLsh['filtered_count'];
            if ($resLsh['is_recalled']) {
                $results['lsh']['tp']++;
            } else {
                $results['lsh']['fn']++;
            }
            $results['lsh']['fp'] += $resLsh['fp_count'];

            // Store per-sample details
            $results['exact']['details'][] = $resExact;
            $results['ngram']['details'][] = $resNgram;
            $results['lsh']['details'][]   = $resLsh;
        }

        // Compute summary statistics
        $summary = [];
        foreach (['exact', 'ngram', 'lsh'] as $scheme) {
            $classMetrics = MetricsCalculator::computeClassificationMetrics(
                $results[$scheme]['tp'],
                $results[$scheme]['fp'],
                $results[$scheme]['fn'],
                count($testCases) * 10 - $results[$scheme]['fp'] // Estimated True Negatives in candidate pool
            );
            $distLatency = MetricsCalculator::computeDistribution($results[$scheme]['latency']);
            $distCandidates = MetricsCalculator::computeDistribution($results[$scheme]['retrieved_count']);
            $distFiltered   = MetricsCalculator::computeDistribution($results[$scheme]['filtered_count']);

            $summary[$scheme] = [
                'classification' => $classMetrics,
                'latency'        => $distLatency,
                'retrieved'      => $distCandidates,
                'filtered'       => $distFiltered,
            ];
        }

        // Paired T-Test: N-Gram vs LSH
        $tTestNgramLsh = MetricsCalculator::pairedTTest($results['ngram']['latency'], $results['lsh']['latency']);
        $summary['paired_t_test_ngram_vs_lsh'] = $tTestNgramLsh;

        return [
            'summary'   => $summary,
            'raw_data'  => $results,
            'test_cases'=> $testCases,
        ];
    }

    /**
     * Executes evaluation across the diverse query set and computes stratified breakdown.
     */
    public function executeExtendedDiversityEvaluation(array $diverseCases): array
    {
        $breakdown = [];
        $detailedResults = [];

        foreach ($diverseCases as $idx => $case) {
            $dim = $case['diversity_dimension'];
            if (!isset($breakdown[$dim])) {
                $breakdown[$dim] = [
                    'count'         => 0,
                    'exact_recall'  => [],
                    'ngram_recall'  => [],
                    'lsh_recall'    => [],
                    'ngram_latency' => [],
                    'lsh_latency'   => [],
                ];
            }

            $query = $case['typo_query'];
            $docId = $case['doc_id'];

            $resExact = $this->searchExact($query, $docId);
            $resNgram = $this->searchNgram($query, $docId, 0.4, 85.0);
            $resLsh   = $this->searchLsh($query, $docId, 3, 85.0);

            $breakdown[$dim]['count']++;
            $breakdown[$dim]['exact_recall'][]  = $resExact['is_recalled'] ? 100 : 0;
            $breakdown[$dim]['ngram_recall'][]  = $resNgram['is_recalled'] ? 100 : 0;
            $breakdown[$dim]['lsh_recall'][]    = $resLsh['is_recalled'] ? 100 : 0;
            $breakdown[$dim]['ngram_latency'][] = $resNgram['latency_total'];
            $breakdown[$dim]['lsh_latency'][]   = $resLsh['latency_total'];

            $detailedResults[] = [
                'case'  => $case,
                'exact' => $resExact,
                'ngram' => $resNgram,
                'lsh'   => $resLsh,
            ];
        }

        $summary = [];
        foreach ($breakdown as $dim => $data) {
            $summary[$dim] = [
                'dimension'      => $dim,
                'count'          => $data['count'],
                'exact_recall'   => round(array_sum($data['exact_recall']) / $data['count'], 2),
                'ngram_recall'   => round(array_sum($data['ngram_recall']) / $data['count'], 2),
                'lsh_recall'     => round(array_sum($data['lsh_recall']) / $data['count'], 2),
                'ngram_latency'  => MetricsCalculator::computeDistribution($data['ngram_latency']),
                'lsh_latency'    => MetricsCalculator::computeDistribution($data['lsh_latency']),
            ];
        }

        return [
            'summary'          => $summary,
            'detailed_results' => $detailedResults,
        ];
    }

    /**
     * Executes sensitivity sweeps for k and tau with optimized in-memory filtering.
     */
    public function executeSensitivitySweeps(array $testCases): array
    {
        $kValues   = [2, 3, 4, 5];
        $tauValues = [70.0, 75.0, 80.0, 85.0, 90.0, 95.0];
        $thetaVals = [0.30, 0.40, 0.50, 0.60];

        // Pre-fetch candidate sets for each test case
        // 1. k sweep: Query MySQL for k in 2, 3, 4, 5
        $kSweep = [];
        $kCandidateCache = []; // [case_idx][k] => rawDocIds

        foreach ($kValues as $k) {
            $latencies = [];
            $cands     = [];
            $tp = 0; $fp = 0; $fn = 0;

            foreach ($testCases as $idx => $case) {
                $res = $this->searchLsh($case['typo_query'], $case['doc_id'], $k, 85.0);
                $latencies[] = $res['latency_total'];
                $cands[]     = $res['retrieved_count'];
                if ($res['is_recalled']) $tp++; else $fn++;
                $fp += $res['fp_count'];

                if ($k === 3) {
                    $kCandidateCache[$idx] = $res['raw_doc_ids'] ?? [];
                }
            }

            $kSweep[$k] = [
                'k'             => $k,
                'latency'       => MetricsCalculator::computeDistribution($latencies),
                'candidates'    => MetricsCalculator::computeDistribution($cands),
                'classification'=> MetricsCalculator::computeClassificationMetrics($tp, $fp, $fn),
            ];
        }

        // 2. tau sweep: In-memory evaluation on cached k=3 candidate documents
        $tauSweep = [];
        foreach ($tauValues as $tau) {
            $latencies = [];
            $tp = 0; $fp = 0; $fn = 0;

            foreach ($testCases as $idx => $case) {
                $rawDocIds = $kCandidateCache[$idx] ?? [];
                $t0 = microtime(true);
                $filterRes = $this->filterCandidates($rawDocIds, $case['typo_query'], $tau, $case['doc_id']);
                $latency = (microtime(true) - $t0) * 1000;
                $latencies[] = $latency;

                $isRecalled = in_array($case['doc_id'], $filterRes['valid_doc_ids'], true);
                if ($isRecalled) $tp++; else $fn++;
                $fp += $filterRes['fp_count'];
            }

            $tauSweep[(string) $tau] = [
                'tau'           => $tau,
                'latency'       => MetricsCalculator::computeDistribution($latencies),
                'classification'=> MetricsCalculator::computeClassificationMetrics($tp, $fp, $fn),
            ];
        }

        // 3. theta sweep: Query N-Gram hits and evaluate threshold in-memory
        $ngramThetaSweep = [];
        foreach ($thetaVals as $th) {
            $latencies = [];
            $cands = [];
            $tp = 0; $fp = 0; $fn = 0;

            foreach ($testCases as $case) {
                $res = $this->searchNgram($case['typo_query'], $case['doc_id'], $th, 85.0);
                $latencies[] = $res['latency_total'];
                $cands[]     = $res['retrieved_count'];
                if ($res['is_recalled']) $tp++; else $fn++;
                $fp += $res['fp_count'];
            }

            $ngramThetaSweep[(string) $th] = [
                'theta'         => $th,
                'latency'       => MetricsCalculator::computeDistribution($latencies),
                'candidates'    => MetricsCalculator::computeDistribution($cands),
                'classification'=> MetricsCalculator::computeClassificationMetrics($tp, $fp, $fn),
            ];
        }

        return [
            'k_sweep'     => $kSweep,
            'tau_sweep'   => $tauSweep,
            'theta_sweep' => $ngramThetaSweep,
        ];
    }

    // --- Search Implementations ---

    private function searchExact(string $query, string $targetDocId): array
    {
        $t0 = microtime(true);
        $trapdoor = HmacTrapdoor::generate($query, $this->secretKey);
        $t1 = microtime(true);

        $stmt = $this->pdo->prepare("SELECT DISTINCT doc_id FROM exact_index WHERE trapdoor = ?");
        $stmt->execute([$trapdoor]);
        $rawDocIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $t2 = microtime(true);

        $filterRes = $this->filterCandidates($rawDocIds, $query, 85.0, $targetDocId);
        $t3 = microtime(true);

        return [
            'latency_trapdoor' => round(($t1 - $t0) * 1000, 3),
            'latency_cloud'    => round(($t2 - $t1) * 1000, 3),
            'latency_filter'   => round(($t3 - $t2) * 1000, 3),
            'latency_total'    => round(($t3 - $t0) * 1000, 2),
            'retrieved_count'  => count($rawDocIds),
            'filtered_count'   => count($filterRes['valid_doc_ids']),
            'is_recalled'      => in_array($targetDocId, $filterRes['valid_doc_ids'], true),
            'fp_count'         => $filterRes['fp_count'],
        ];
    }

    private function searchNgram(string $query, string $targetDocId, float $hitRatio, float $tau): array
    {
        $t0 = microtime(true);
        $bigrams = NgramTokenizer::extractBigrams($query);
        $trapdoors = array_map(fn($bg) => HmacTrapdoor::generate($bg, $this->secretKey), $bigrams);
        $t1 = microtime(true);

        $rawDocIds = [];
        if (!empty($trapdoors)) {
            $placeholders = implode(',', array_fill(0, count($trapdoors), '?'));
            $sql = "SELECT doc_id, COUNT(*) as hits FROM ngram_index WHERE trapdoor IN ($placeholders) GROUP BY doc_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($trapdoors);
            $minHits = count($bigrams) * $hitRatio;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ((float) $row['hits'] >= $minHits) {
                    $rawDocIds[] = (string) $row['doc_id'];
                }
            }
        }
        $t2 = microtime(true);

        $filterRes = $this->filterCandidates($rawDocIds, $query, $tau, $targetDocId);
        $t3 = microtime(true);

        return [
            'latency_trapdoor' => round(($t1 - $t0) * 1000, 3),
            'latency_cloud'    => round(($t2 - $t1) * 1000, 3),
            'latency_filter'   => round(($t3 - $t2) * 1000, 3),
            'latency_total'    => round(($t3 - $t0) * 1000, 2),
            'retrieved_count'  => count($rawDocIds),
            'filtered_count'   => count($filterRes['valid_doc_ids']),
            'is_recalled'      => in_array($targetDocId, $filterRes['valid_doc_ids'], true),
            'fp_count'         => $filterRes['fp_count'],
        ];
    }

    private function searchLsh(string $query, string $targetDocId, int $k, float $tau): array
    {
        $t0 = microtime(true);
        $bandTokens = MinHashEngine::getBandTokens($query, $k);
        $trapdoors = array_map(fn($token) => HmacTrapdoor::generate($token, $this->secretKey), $bandTokens);
        $t1 = microtime(true);

        $rawDocIds = [];
        if (!empty($trapdoors)) {
            $placeholders = implode(',', array_fill(0, count($trapdoors), '?'));
            $sql = "SELECT DISTINCT doc_id FROM lsh_index WHERE trapdoor IN ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($trapdoors);
            $rawDocIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $t2 = microtime(true);

        $filterRes = $this->filterCandidates($rawDocIds, $query, $tau, $targetDocId);
        $t3 = microtime(true);

        return [
            'latency_trapdoor' => round(($t1 - $t0) * 1000, 3),
            'latency_cloud'    => round(($t2 - $t1) * 1000, 3),
            'latency_filter'   => round(($t3 - $t2) * 1000, 3),
            'latency_total'    => round(($t3 - $t0) * 1000, 2),
            'retrieved_count'  => count($rawDocIds),
            'filtered_count'   => count($filterRes['valid_doc_ids']),
            'is_recalled'      => in_array($targetDocId, $filterRes['valid_doc_ids'], true),
            'fp_count'         => $filterRes['fp_count'],
            'raw_doc_ids'      => $rawDocIds,
        ];
    }

    private function filterCandidates(array $docIds, string $query, float $threshold, string $targetDocId): array
    {
        if (empty($docIds)) {
            return ['valid_doc_ids' => [], 'fp_count' => 0];
        }

        // Fetch ciphertexts in single batch
        $placeholders = implode(',', array_fill(0, count($docIds), '?'));
        $stmt = $this->pdo->prepare("SELECT doc_id, enc_name_en, enc_name_id FROM icds_encrypted WHERE doc_id IN ($placeholders)");
        $stmt->execute($docIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $valid = [];
        $fp = 0;

        foreach ($rows as $row) {
            $id = (string) $row['doc_id'];
            $textEn = AesCipher::decrypt((string) $row['enc_name_en'], $this->secretKey) ?? '';
            $textId = AesCipher::decrypt((string) $row['enc_name_id'], $this->secretKey) ?? '';

            $matchEn = ClientSideFilter::isValidMatch($query, $textEn, $threshold);
            $matchId = ClientSideFilter::isValidMatch($query, $textId, $threshold);

            if ($matchEn || $matchId) {
                $valid[] = $id;
                if ($id !== $targetDocId) {
                    $fp++;
                }
            }
        }

        return ['valid_doc_ids' => $valid, 'fp_count' => $fp];
    }
}
