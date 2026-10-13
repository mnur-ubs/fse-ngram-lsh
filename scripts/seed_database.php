<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Crypto/AesCipher.php';
require_once __DIR__ . '/../src/Crypto/HmacTrapdoor.php';
require_once __DIR__ . '/../src/Tokenizer/NgramTokenizer.php';
require_once __DIR__ . '/../src/Lsh/MinHashEngine.php';
require_once __DIR__ . '/../src/Index/IndexManager.php';

use Fkse\Index\IndexManager;

ini_set('memory_limit', '1024M');
set_time_limit(0);

echo "=== STARTING DATABASE SEEDING & INDEX CONSTRUCTION ===\n";
$start = microtime(true);

$indexManager = new IndexManager();

$stats = $indexManager->buildIndices(5, function (int $current, int $total) {
    $pct = round(($current / $total) * 100, 1);
    echo "Progress: {$current} / {$total} ({$pct}%)\r";
});

$elapsed = round(microtime(true) - $start, 2);
echo "\n=======================================================\n";
echo "Indexing completed successfully in {$elapsed} seconds!\n";
echo "Index Storage Statistics:\n";
foreach ($stats as $table => $count) {
    echo " - {$table}: " . number_format($count) . " rows\n";
}
echo "=======================================================\n";
