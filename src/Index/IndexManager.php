<?php

declare(strict_types=1);

namespace Fkse\Index;

use Fkse\Config\Database;
use Fkse\Crypto\AesCipher;
use Fkse\Crypto\HmacTrapdoor;
use Fkse\Tokenizer\NgramTokenizer;
use Fkse\Lsh\MinHashEngine;
use PDO;

final class IndexManager
{
    private PDO $pdo;
    private string $secretKey;

    public function __construct(?PDO $pdo = null, ?string $secretKey = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->secretKey = $secretKey ?? Database::getSecretKey();
    }

    /**
     * Initializes all schema tables. Drops existing index tables if $dropExisting is true.
     */
    public function initializeTables(bool $dropExisting = false): void
    {
        if ($dropExisting) {
            $this->pdo->exec("
                DROP TABLE IF EXISTS icds_encrypted;
                DROP TABLE IF EXISTS exact_index;
                DROP TABLE IF EXISTS ngram_index;
                DROP TABLE IF EXISTS lsh_index;
            ");
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS icds_encrypted (
                doc_id VARCHAR(50) PRIMARY KEY,
                enc_name_en TEXT NOT NULL,
                enc_name_id TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS exact_index (
                id INT AUTO_INCREMENT PRIMARY KEY,
                trapdoor VARCHAR(64) NOT NULL,
                doc_id VARCHAR(50) NOT NULL,
                INDEX idx_exact_trapdoor (trapdoor)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ngram_index (
                id INT AUTO_INCREMENT PRIMARY KEY,
                trapdoor VARCHAR(64) NOT NULL,
                doc_id VARCHAR(50) NOT NULL,
                INDEX idx_ngram_trapdoor (trapdoor)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS lsh_index (
                id INT AUTO_INCREMENT PRIMARY KEY,
                trapdoor VARCHAR(64) NOT NULL,
                doc_id VARCHAR(50) NOT NULL,
                INDEX idx_lsh_trapdoor (trapdoor)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * Builds and populates all indexes from the master `icds` table.
     *
     * @param int $maxBands Maximum MinHash bands to index (default: 5 to support k in {2, 3, 4, 5})
     * @param callable|null $progressCallback Optional callback for progress reporting (current, total)
     * @return array<string, int>
     */
    public function buildIndices(int $maxBands = 5, ?callable $progressCallback = null): array
    {
        $this->initializeTables(true);

        $stmt = $this->pdo->query("SELECT code, name_en, name_id FROM icds");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalRecords = count($records);

        $batchSize = 500;
        $batchDocs = [];
        $batchExact = [];
        $batchNgram = [];
        $batchLsh = [];

        $insertBatch = function(string $table, array &$batch, array $columns): void {
            if (empty($batch)) {
                return;
            }
            $colNames = implode(', ', $columns);
            $rowPlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $allPlaceholders = implode(', ', array_fill(0, count($batch), $rowPlaceholders));

            $flatValues = [];
            foreach ($batch as $row) {
                foreach ($columns as $col) {
                    $flatValues[] = $row[$col];
                }
            }

            $sql = "INSERT INTO {$table} ({$colNames}) VALUES {$allPlaceholders}";
            $insertStmt = $this->pdo->prepare($sql);
            $insertStmt->execute($flatValues);
            $batch = [];
        };

        $this->pdo->beginTransaction();

        $processed = 0;
        foreach ($records as $row) {
            $docId = (string) $row['code'];
            $nameEn = (string) $row['name_en'];
            $nameId = (string) $row['name_id'];

            // 1. Encrypt documents
            $encEn = AesCipher::encrypt($nameEn, $this->secretKey);
            $encId = AesCipher::encrypt($nameId, $this->secretKey);
            $batchDocs[] = [
                'doc_id'      => $docId,
                'enc_name_en' => $encEn,
                'enc_name_id' => $encId,
            ];

            // 2. Extract unique keywords (length >= 3)
            $wordsId = explode(' ', $nameId);
            $wordsEn = explode(' ', $nameEn);
            $allWords = array_unique(array_map('strtolower', array_merge($wordsId, $wordsEn)));

            foreach ($allWords as $word) {
                if (strlen($word) < 3) {
                    continue;
                }
                $cleanWord = preg_replace('/[^a-z0-9]/i', '', $word);
                if ($cleanWord === null || $cleanWord === '') {
                    continue;
                }

                // Exact match trapdoor
                $batchExact[] = [
                    'trapdoor' => HmacTrapdoor::generate($cleanWord, $this->secretKey),
                    'doc_id'   => $docId,
                ];

                // N-Gram (Bi-gram) trapdoors
                foreach (NgramTokenizer::extractBigrams($cleanWord) as $bg) {
                    $batchNgram[] = [
                        'trapdoor' => HmacTrapdoor::generate($bg, $this->secretKey),
                        'doc_id'   => $docId,
                    ];
                }

                // LSH (MinHash) band trapdoors (up to maxBands)
                foreach (MinHashEngine::getBandTokens($cleanWord, $maxBands) as $bandToken) {
                    $batchLsh[] = [
                        'trapdoor' => HmacTrapdoor::generate($bandToken, $this->secretKey),
                        'doc_id'   => $docId,
                    ];
                }
            }

            // Flush batches when reaching batch threshold
            if (count($batchDocs) >= $batchSize) {
                $insertBatch('icds_encrypted', $batchDocs, ['doc_id', 'enc_name_en', 'enc_name_id']);
            }
            if (count($batchExact) >= $batchSize) {
                $insertBatch('exact_index', $batchExact, ['trapdoor', 'doc_id']);
            }
            if (count($batchNgram) >= $batchSize) {
                $insertBatch('ngram_index', $batchNgram, ['trapdoor', 'doc_id']);
            }
            if (count($batchLsh) >= $batchSize) {
                $insertBatch('lsh_index', $batchLsh, ['trapdoor', 'doc_id']);
            }

            $processed++;
            if ($progressCallback !== null && $processed % 1000 === 0) {
                $progressCallback($processed, $totalRecords);
            }
        }

        // Flush remaining rows
        $insertBatch('icds_encrypted', $batchDocs, ['doc_id', 'enc_name_en', 'enc_name_id']);
        $insertBatch('exact_index', $batchExact, ['trapdoor', 'doc_id']);
        $insertBatch('ngram_index', $batchNgram, ['trapdoor', 'doc_id']);
        $insertBatch('lsh_index', $batchLsh, ['trapdoor', 'doc_id']);

        $this->pdo->commit();

        if ($progressCallback !== null) {
            $progressCallback($totalRecords, $totalRecords);
        }

        return $this->getStats();
    }

    /**
     * Retrieves row counts and storage metrics for all index tables.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return [
            'icds_encrypted' => (int) $this->pdo->query("SELECT COUNT(*) FROM icds_encrypted")->fetchColumn(),
            'exact_index'    => (int) $this->pdo->query("SELECT COUNT(*) FROM exact_index")->fetchColumn(),
            'ngram_index'    => (int) $this->pdo->query("SELECT COUNT(*) FROM ngram_index")->fetchColumn(),
            'lsh_index'      => (int) $this->pdo->query("SELECT COUNT(*) FROM lsh_index")->fetchColumn(),
        ];
    }
}
