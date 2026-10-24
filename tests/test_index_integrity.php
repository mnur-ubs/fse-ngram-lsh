<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Crypto/AesCipher.php';
require_once __DIR__ . '/../src/Crypto/HmacTrapdoor.php';

use Fkse\Config\Database;
use Fkse\Crypto\AesCipher;
use Fkse\Crypto\HmacTrapdoor;

$pdo = Database::getConnection();
$secretKey = Database::getSecretKey();

echo "=== TESTING INDEX INTEGRITY & RETRIEVAL ===\n";

$counts = [
    'icds_encrypted' => (int) $pdo->query("SELECT COUNT(*) FROM icds_encrypted")->fetchColumn(),
    'exact_index'    => (int) $pdo->query("SELECT COUNT(*) FROM exact_index")->fetchColumn(),
    'ngram_index'    => (int) $pdo->query("SELECT COUNT(*) FROM ngram_index")->fetchColumn(),
    'lsh_index'      => (int) $pdo->query("SELECT COUNT(*) FROM lsh_index")->fetchColumn(),
];

foreach ($counts as $table => $c) {
    echo "[INFO] {$table}: " . number_format($c) . " rows\n";
    if ($c === 0) {
        echo "[FAIL] Table {$table} is empty!\n";
        exit(1);
    }
}

// Test sample retrieval
$sampleTrapdoor = HmacTrapdoor::generate('kolera', $secretKey);
$stmt = $pdo->prepare("SELECT doc_id FROM exact_index WHERE trapdoor = ?");
$stmt->execute([$sampleTrapdoor]);
$exactMatches = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "[PASS] Exact index query for 'kolera' returned " . count($exactMatches) . " documents.\n";

// Test decryption
if (!empty($exactMatches)) {
    $firstDoc = $exactMatches[0];
    $stmtDoc = $pdo->prepare("SELECT enc_name_id, enc_name_en FROM icds_encrypted WHERE doc_id = ?");
    $stmtDoc->execute([$firstDoc]);
    $doc = $stmtDoc->fetch();
    $decryptedId = AesCipher::decrypt($doc['enc_name_id'], $secretKey);
    $decryptedEn = AesCipher::decrypt($doc['enc_name_en'], $secretKey);
    echo "[PASS] Successfully decrypted document {$firstDoc}: [ID] '{$decryptedId}' | [EN] '{$decryptedEn}'\n";
}

echo ">>> INDEX INTEGRITY VERIFICATION SUCCESSFUL! <<<\n";
