<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Crypto/AesCipher.php';
require_once __DIR__ . '/../src/Crypto/HmacTrapdoor.php';
require_once __DIR__ . '/../src/Tokenizer/NgramTokenizer.php';
require_once __DIR__ . '/../src/Lsh/MinHashEngine.php';
require_once __DIR__ . '/../src/Filter/ClientSideFilter.php';
require_once __DIR__ . '/../src/Benchmark/TypoInjector.php';
require_once __DIR__ . '/../src/Benchmark/MetricsCalculator.php';
require_once __DIR__ . '/../src/Benchmark/SensitivityBenchmark.php';

use Fkse\Benchmark\SensitivityBenchmark;

ini_set('memory_limit', '1024M');
set_time_limit(0);

// Usage:
//   php scripts/run_benchmark.php                            new query set, writes to data/
//   php scripts/run_benchmark.php --queries=data/benchmark_100_queries.json --out=data/rerun_X
//       reuses the published 100 test queries (TypoInjector uses unseeded rand(), so only
//       --queries reproduces them) and writes the new results to another folder.
$opts = getopt('', ['queries:', 'out:']);
$root = dirname(__DIR__);
$resolve = static fn (string $p): string =>
    (str_starts_with($p, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $p) === 1) ? $p : $root . '/' . $p;

echo "===============================================================\n";
echo "       FKSE EXPERIMENTAL BENCHMARK & SENSITIVITY SWEEPS        \n";
echo "===============================================================\n";

$dataDir = isset($opts['out']) ? $resolve((string) $opts['out']) : $root . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}
echo "Timer: hrtime() (monotonic). Output folder: {$dataDir}\n";

$benchmark = new SensitivityBenchmark();

// 1. Query set: reuse a previous run, or generate 100 balanced clinical test cases
if (isset($opts['queries'])) {
    $src = $resolve((string) $opts['queries']);
    $prev = json_decode((string) file_get_contents($src), true, 512, JSON_THROW_ON_ERROR);
    $testCases = $prev['test_cases'] ?? $prev;
    echo "[1/4] Reusing " . count($testCases) . " test queries from {$src}\n";
} else {
    echo "[1/4] Generating 100 standardized typo-injected clinical queries...\n";
    $testCases = $benchmark->generateQuerySet(100);
    echo "Generated " . count($testCases) . " test queries across 4 typo categories.\n";
}

// 2. Execute 100-query benchmark
echo "[2/4] Executing primary 100-query comparative evaluation (Exact vs N-Gram vs LSH)...\n";
$tStart = hrtime(true);
$results100 = $benchmark->execute100QueryBenchmark($testCases);
$elapsed100 = round((hrtime(true) - $tStart) / 1e9, 2);
echo "Completed " . count($testCases) . " queries in {$elapsed100} seconds.\n";

// Display summary table
echo "\n--- Summary Performance (n = " . count($testCases) . " queries) ---\n";
printf("%-15s | %-10s | %-10s | %-10s | %-12s | %-18s\n", "Scheme", "Recall (%)", "Prec (%)", "F1 (%)", "FPR (%)", "Latency Mean (ms)");
echo str_repeat('-', 85) . "\n";
foreach (['exact', 'ngram', 'lsh'] as $s) {
    $c = $results100['summary'][$s]['classification'];
    $l = $results100['summary'][$s]['latency'];
    printf("%-15s | %-10.2f | %-10.2f | %-10.2f | %-10.2f | %8.2f ± %-6.2f\n", 
        strtoupper($s), $c['recall'], $c['precision'], $c['f1'], $c['fpr'], $l['mean'], $l['margin_of_error']);
}
echo str_repeat('-', 85) . "\n";
$tTest = $results100['summary']['paired_t_test_ngram_vs_lsh'];
echo "Paired t-test (N-Gram vs LSH): t = {$tTest['t_statistic']}, df = {$tTest['df']}, p = {$tTest['p_value']}\n\n";

// Save primary results
file_put_contents($dataDir . '/benchmark_100_queries.json', json_encode($results100, JSON_PRETTY_PRINT));
echo "[PASS] Saved benchmark_100_queries.json\n";

// 3. Execute sensitivity sweeps
echo "\n[3/4] Executing Sensitivity Sweeps (k in {2,3,4,5}, tau in {70%-95%}, theta in {0.3-0.6})...\n";
$tSweepStart = hrtime(true);
$sweeps = $benchmark->executeSensitivitySweeps($testCases);
$elapsedSweeps = round((hrtime(true) - $tSweepStart) / 1e9, 2);
echo "Completed sensitivity sweeps in {$elapsedSweeps} seconds.\n";

file_put_contents($dataDir . '/sensitivity_analysis.json', json_encode($sweeps, JSON_PRETTY_PRINT));
echo "[PASS] Saved sensitivity_analysis.json\n";

// 4. Scalability Projection
echo "\n[4/4] Computing Scalability Projections (10k to 1,000,000 records)...\n";
$scalabilityData = [
    'corpus_sizes' => [10469, 50000, 100000, 250000, 500000, 1000000],
    'projections' => [
        'exact' => [],
        'ngram' => [],
        'lsh_k3' => [],
        'lsh_k5' => [],
    ]
];

// Based on empirical multipliers per record:
// Exact: ~10.51 trapdoors/record
// N-Gram: ~83.99 trapdoors/record (bigram expansion)
// LSH k=3: ~31.55 trapdoors/record
// LSH k=5: ~52.58 trapdoors/record
foreach ($scalabilityData['corpus_sizes'] as $n) {
    $scale = $n / 10469;
    $scalabilityData['projections']['exact'][] = [
        'docs'       => $n,
        'index_rows' => (int) round(110102 * $scale),
        'storage_mb' => round((110102 * $scale * 128) / (1024 * 1024), 2),
    ];
    $scalabilityData['projections']['ngram'][] = [
        'docs'       => $n,
        'index_rows' => (int) round(879327 * $scale),
        'storage_mb' => round((879327 * $scale * 128) / (1024 * 1024), 2),
    ];
    $scalabilityData['projections']['lsh_k3'][] = [
        'docs'       => $n,
        'index_rows' => (int) round(330306 * $scale),
        'storage_mb' => round((330306 * $scale * 128) / (1024 * 1024), 2),
    ];
    $scalabilityData['projections']['lsh_k5'][] = [
        'docs'       => $n,
        'index_rows' => (int) round(550510 * $scale),
        'storage_mb' => round((550510 * $scale * 128) / (1024 * 1024), 2),
    ];
}

file_put_contents($dataDir . '/scalability_projection.json', json_encode($scalabilityData, JSON_PRETTY_PRINT));
echo "[PASS] Saved scalability_projection.json\n";

echo "\n>>> ALL BENCHMARK & SENSITIVITY EXPERIMENTS COMPLETE! <<<\n";
