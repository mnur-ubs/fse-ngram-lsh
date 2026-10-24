<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

use Fkse\Config\Database;

// Two valid layouts:
//  - full (local seeding): plaintext master table `icds` with >= 10,000 records;
//  - server only (VPS made by deploy/setup_vps_mysql.sh): no plaintext, only icds_encrypted and
//    the three index tables. This is enough for run_benchmark.php / run_diversity_benchmark.php
//    with --queries=..., which reuse the published query sets.
try {
    $pdo = Database::getConnection();
    echo "[PASS] Successfully connected to master_icd_x database via PDO.\n";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "[INFO] Tables found in master_icd_x: " . implode(', ', $tables) . "\n";

    if (in_array('icds', $tables, true)) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM icds")->fetchColumn();
        echo "[INFO] Total records in 'icds' table: {$count}\n";
        if ($count < 10000) {
            throw new RuntimeException("Expected >= 10,000 records in 'icds', found: {$count}");
        }
        echo "[PASS] Master ICD-10 data verified ({$count} records).\n";
        exit(0);
    }

    $required = ['icds_encrypted', 'exact_index', 'ngram_index', 'lsh_index'];
    $missing = array_diff($required, $tables);
    if ($missing !== []) {
        throw new RuntimeException("Table 'icds' is missing, and so are: " . implode(', ', $missing));
    }
    $count = (int) $pdo->query("SELECT COUNT(*) FROM icds_encrypted")->fetchColumn();
    if ($count < 10000) {
        throw new RuntimeException("Expected >= 10,000 records in 'icds_encrypted', found: {$count}");
    }
    echo "[PASS] Server-only database verified: no plaintext table, {$count} encrypted records.\n";
    echo "[INFO] Run the benchmarks with --queries=... (generating new queries needs table 'icds').\n";
} catch (Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    exit(1);
}
