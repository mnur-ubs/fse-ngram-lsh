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

echo "===============================================================\n";
echo "      FKSE EXTENDED QUERY DIVERSITY BENCHMARK EVALUATION       \n";
echo "===============================================================\n";

$dataDir = __DIR__ . '/../data';
$benchmark = new SensitivityBenchmark();

echo "[1/2] Generating stratified diverse clinical query set (n = 100)...\n";
$diverseCases = $benchmark->generateDiverseQuerySet();
echo "Generated " . count($diverseCases) . " diverse queries across 5 strata:\n";

$counts = [];
foreach ($diverseCases as $c) {
    $d = $c['diversity_dimension'];
    $counts[$d] = ($counts[$d] ?? 0) + 1;
}
foreach ($counts as $dim => $cnt) {
    echo " - {$dim}: {$cnt} samples\n";
}

echo "\n[2/2] Executing diverse evaluation across Exact, N-Gram, and LSH...\n";
$tStart = microtime(true);
$results = $benchmark->executeExtendedDiversityEvaluation($diverseCases);
$elapsed = round(microtime(true) - $tStart, 2);
echo "Completed in {$elapsed} seconds.\n\n";

echo "--- STRATIFIED EVALUATION SUMMARY BY DIVERSITY DIMENSION ---\n";
printf("%-35s | %-10s | %-10s | %-10s | %-16s | %-16s\n", 
    "Diversity Dimension", "Exact (%)", "N-Gram (%)", "LSH (%)", "N-Gram Latency", "LSH Latency");
echo str_repeat('-', 108) . "\n";

foreach ($results['summary'] as $dim => $s) {
    printf("%-35s | %-10.1f | %-10.1f | %-10.1f | %7.2f ± %-6.2f | %7.2f ± %-6.2f\n",
        $dim,
        $s['exact_recall'],
        $s['ngram_recall'],
        $s['lsh_recall'],
        $s['ngram_latency']['mean'],
        $s['ngram_latency']['margin_of_error'],
        $s['lsh_latency']['mean'],
        $s['lsh_latency']['margin_of_error']
    );
}
echo str_repeat('-', 108) . "\n";

file_put_contents($dataDir . '/diversity_benchmark.json', json_encode($results, JSON_PRETTY_PRINT));
echo "[PASS] Saved diversity_benchmark.json successfully!\n";
