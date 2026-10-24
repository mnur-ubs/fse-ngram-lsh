<?php

declare(strict_types=1);

/**
 * Excel Exporter Script for TELKOMNIKA Revision Benchmark Evidence
 * Generates publication-grade, fully formula-linked spreadsheet matching
 * all figures, tables, and projections in the manuscript.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$dataDir = __DIR__ . '/../data';
$benchmarkFile = $dataDir . '/benchmark_100_queries.json';
$sensitivityFile = $dataDir . '/sensitivity_analysis.json';
$scalabilityFile = $dataDir . '/scalability_projection.json';
$diversityFile = $dataDir . '/diversity_benchmark.json';

if (!file_exists($benchmarkFile)) {
    die("Error: {$benchmarkFile} not found! Please run scripts/run_benchmark.php first.\n");
}

$b100 = json_decode(file_get_contents($benchmarkFile), true);
$sens = file_exists($sensitivityFile) ? json_decode(file_get_contents($sensitivityFile), true) : [];
$scale = file_exists($scalabilityFile) ? json_decode(file_get_contents($scalabilityFile), true) : [];
$diverse = file_exists($diversityFile) ? json_decode(file_get_contents($diversityFile), true) : [];

$spreadsheet = new Spreadsheet();

// Common styles
$headerStylePrimary = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1F497D']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
    ]
];

$headerStyleExact = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C00000']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];

$headerStyleNgram = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '385723']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];

$headerStyleLsh = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B365D']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];

// =============================================================================
// SHEET 1: HASIL PENGUJIAN 100 SAMPEL (TABLE 7, TABLE 8, FIGURE 6, FIGURE 7 - numbering of manuscript v3)
// =============================================================================
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Pengujian_100_Sampel');

// Super Headers (Row 1)
$sheet1->mergeCells('A1:F1');
$sheet1->setCellValue('A1', 'IDENTITAS KUERI & DOKUMEN MEDIS UJI');
$sheet1->getStyle('A1:F1')->applyFromArray($headerStylePrimary);

$sheet1->mergeCells('G1:K1');
$sheet1->setCellValue('G1', '1. EXACT MATCH (BASELINE DETERMINISTIK)');
$sheet1->getStyle('G1:K1')->applyFromArray($headerStyleExact);

$sheet1->mergeCells('L1:P1');
$sheet1->setCellValue('L1', '2. N-GRAM (BI-GRAM EXPANSION, theta = 0.40)');
$sheet1->getStyle('L1:P1')->applyFromArray($headerStyleNgram);

$sheet1->mergeCells('Q1:U1');
$sheet1->setCellValue('Q1', '3. LSH (MINHASH k = 3, FILTER tau = 85%)');
$sheet1->getStyle('Q1:U1')->applyFromArray($headerStyleLsh);

$sheet1->setCellValue('V1', 'UJI BERPASANGAN');
$sheet1->getStyle('V1')->applyFromArray($headerStylePrimary);

$sheet1->getRowDimension(1)->setRowHeight(24);

// Column Headers (Row 2)
$subHeaders = [
    'A2' => 'No',
    'B2' => 'Doc ID',
    'C2' => 'Kata Asli',
    'D2' => 'Kueri Typo',
    'E2' => 'Kategori Typo',
    'F2' => 'Diagnosis ICD-10 (Deskripsi)',
    
    // Exact Match
    'G2' => 'Waktu (ms)',
    'H2' => 'Recall (%)',
    'I2' => 'Kandidat Server',
    'J2' => 'Lolos Filter',
    'K2' => 'False Positive',
    
    // N-Gram
    'L2' => 'Waktu (ms)',
    'M2' => 'Recall (%)',
    'N2' => 'Kandidat Server',
    'O2' => 'Lolos Filter',
    'P2' => 'False Positive',
    
    // LSH MinHash
    'Q2' => 'Waktu (ms)',
    'R2' => 'Recall (%)',
    'S2' => 'Kandidat Server',
    'T2' => 'Lolos Filter',
    'U2' => 'False Positive',

    // Paired difference used by the paired t-test (Table 7)
    'V2' => 'Selisih N-Gram - LSH (ms)',
];

foreach ($subHeaders as $cell => $title) {
    $sheet1->setCellValue($cell, $title);
}
$sheet1->getStyle('A2:F2')->applyFromArray($headerStylePrimary);
$sheet1->getStyle('G2:K2')->applyFromArray($headerStyleExact);
$sheet1->getStyle('L2:P2')->applyFromArray($headerStyleNgram);
$sheet1->getStyle('Q2:U2')->applyFromArray($headerStyleLsh);
$sheet1->getStyle('V2')->applyFromArray($headerStylePrimary);
$sheet1->getRowDimension(2)->setRowHeight(28);

// Populate 100 query rows (Rows 3 to 102)
$testCases = $b100['test_cases'];
$rawExact = $b100['raw_data']['exact'];
$rawNgram = $b100['raw_data']['ngram'];
$rawLsh   = $b100['raw_data']['lsh'];

$rowNum = 3;
for ($i = 0; $i < count($testCases); $i++) {
    $case = $testCases[$i];
    $detEx = $rawExact['details'][$i];
    $detNg = $rawNgram['details'][$i];
    $detLs = $rawLsh['details'][$i];

    $sheet1->setCellValue("A{$rowNum}", $i + 1);
    $sheet1->setCellValue("B{$rowNum}", $case['doc_id']);
    $sheet1->setCellValue("C{$rowNum}", $case['original_word']);
    $sheet1->setCellValue("D{$rowNum}", $case['typo_query']);
    $sheet1->setCellValue("E{$rowNum}", ucfirst($case['typo_type']));
    $sheet1->setCellValue("F{$rowNum}", $case['disease_name']);

    // Exact Match
    $sheet1->setCellValue("G{$rowNum}", round((float)$detEx['latency_total'], 2));
    $sheet1->setCellValue("H{$rowNum}", $detEx['is_recalled'] ? 100 : 0);
    $sheet1->setCellValue("I{$rowNum}", $detEx['retrieved_count']);
    $sheet1->setCellValue("J{$rowNum}", $detEx['filtered_count']);
    $sheet1->setCellValue("K{$rowNum}", $detEx['fp_count']);

    // N-Gram
    $sheet1->setCellValue("L{$rowNum}", round((float)$detNg['latency_total'], 2));
    $sheet1->setCellValue("M{$rowNum}", $detNg['is_recalled'] ? 100 : 0);
    $sheet1->setCellValue("N{$rowNum}", $detNg['retrieved_count']);
    $sheet1->setCellValue("O{$rowNum}", $detNg['filtered_count']);
    $sheet1->setCellValue("P{$rowNum}", $detNg['fp_count']);

    // LSH MinHash
    $sheet1->setCellValue("Q{$rowNum}", round((float)$detLs['latency_total'], 2));
    $sheet1->setCellValue("R{$rowNum}", $detLs['is_recalled'] ? 100 : 0);
    $sheet1->setCellValue("S{$rowNum}", $detLs['retrieved_count']);
    $sheet1->setCellValue("T{$rowNum}", $detLs['filtered_count']);
    $sheet1->setCellValue("U{$rowNum}", $detLs['fp_count']);

    // Paired difference d_i = latency_N-Gram - latency_LSH
    $sheet1->setCellValue("V{$rowNum}", "=L{$rowNum}-Q{$rowNum}");

    $rowNum++;
}

$lastDataRow = $rowNum - 1; // 102

// Data range styling
$sheet1->getStyle("A3:V{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet1->getStyle("V3:V{$lastDataRow}")->getNumberFormat()->setFormatCode('0.00');

// Data-quality flag: a latency can never be negative. Negative values in the raw data come
// from the wall-clock timer (microtime) stepping backwards during the run; they are kept
// as recorded (no silent editing) but highlighted so every reader can see them.
foreach (['G', 'L', 'Q'] as $col) {
    for ($rr = 3; $rr <= $lastDataRow; $rr++) {
        $v = $sheet1->getCell("{$col}{$rr}")->getValue();
        if (is_numeric($v) && (float) $v < 0) {
            $sheet1->getStyle("{$col}{$rr}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF9999');
            $sheet1->getStyle("{$col}{$rr}")->getFont()->setBold(true);
        }
    }
}
$sheet1->getStyle("A3:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet1->getStyle("E3:E{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Numeric alignments & formats
foreach (['H', 'I', 'J', 'K', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U'] as $col) {
    $sheet1->getStyle("{$col}3:{$col}{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet1->getStyle("{$col}3:{$col}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
}
foreach (['G', 'L', 'Q'] as $col) {
    $sheet1->getStyle("{$col}3:{$col}{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet1->getStyle("{$col}3:{$col}{$lastDataRow}")->getNumberFormat()->setFormatCode('0.00');
}

// -----------------------------------------------------------------------------
// TABLE A: PARAMETER STATISTIK & UJI HIPOTESIS LATENSI (TABLE 7 DI NASKAH)
// -----------------------------------------------------------------------------
$t7Start = $lastDataRow + 3; // 105

$sheet1->mergeCells("E{$t7Start}:U{$t7Start}");
$sheet1->setCellValue("E{$t7Start}", "TABLE 7. PARAMETER STATISTIK & UJI HIPOTESIS LATENSI KOMPUTASI (PAIRED T-TEST, n = 100)");
$sheet1->getStyle("E{$t7Start}:U{$t7Start}")->applyFromArray($headerStylePrimary);
$sheet1->getStyle("E{$t7Start}")->getFont()->setSize(11);

$t7HeaderRow = $t7Start + 1; // 106
$sheet1->setCellValue("E{$t7HeaderRow}", "Parameter Statistik (Latensi Komputasi)");
$sheet1->setCellValue("G{$t7HeaderRow}", "Exact Match (ms)");
$sheet1->setCellValue("L{$t7HeaderRow}", "N-Gram (ms)");
$sheet1->setCellValue("Q{$t7HeaderRow}", "LSH MinHash (ms)");
$sheet1->setCellValue("T{$t7HeaderRow}", "Keterangan Rumus / Penjelasan Matematis");

$sheet1->getStyle("E{$t7HeaderRow}:U{$t7HeaderRow}")->getFont()->setBold(true);
$sheet1->getStyle("E{$t7HeaderRow}:U{$t7HeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
$sheet1->getStyle("E{$t7HeaderRow}:U{$t7HeaderRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$r = $t7HeaderRow + 1; // 107

// 1. Mean
$rowMean = $r;
$sheet1->setCellValue("E{$r}", "Mean (Rata-rata Latensi μ)");
$sheet1->setCellValue("G{$r}", "=AVERAGE(G3:G{$lastDataRow})");
$sheet1->setCellValue("L{$r}", "=AVERAGE(L3:L{$lastDataRow})");
$sheet1->setCellValue("Q{$r}", "=AVERAGE(Q3:Q{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Rata-rata aritmatika total latensi round-trip 100 kueri");
$r++;

// 2. Standar Deviasi
$rowStd = $r;
$sheet1->setCellValue("E{$r}", "Standar Deviasi Sampel (s)");
$sheet1->setCellValue("G{$r}", "=STDEV.S(G3:G{$lastDataRow})");
$sheet1->setCellValue("L{$r}", "=STDEV.S(L3:L{$lastDataRow})");
$sheet1->setCellValue("Q{$r}", "=STDEV.S(Q3:Q{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Deviasi standar sampel (rumus STDEV.S Excel)");
$r++;

// 3. Varians Sampel
$rowVar = $r;
$sheet1->setCellValue("E{$r}", "Varians Sampel (s²)");
$sheet1->setCellValue("G{$r}", "=VAR.S(G3:G{$lastDataRow})");
$sheet1->setCellValue("L{$r}", "=VAR.S(L3:L{$lastDataRow})");
$sheet1->setCellValue("Q{$r}", "=VAR.S(Q3:Q{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Variansi sampel (kuadrat deviasi standar)");
$r++;

// 4. Jumlah Observasi (N)
$rowN = $r;
$sheet1->setCellValue("E{$r}", "Jumlah Observasi (N)");
$sheet1->setCellValue("G{$r}", "=COUNT(G3:G{$lastDataRow})");
$sheet1->setCellValue("L{$r}", "=COUNT(L3:L{$lastDataRow})");
$sheet1->setCellValue("Q{$r}", "=COUNT(Q3:Q{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Total kueri uji klinis berpasangan (paired)");
$r++;

// 5. Margin of Error
$rowMargin = $r;
$sheet1->setCellValue("E{$r}", "Margin of Error (95% CI) = nilai ± di Table 8");
$sheet1->setCellValue("G{$r}", "=ROUND(T.INV.2T(0.05,G{$rowN}-1),3)*(G{$rowStd}/SQRT(G{$rowN}))");
$sheet1->setCellValue("L{$r}", "=ROUND(T.INV.2T(0.05,L{$rowN}-1),3)*(L{$rowStd}/SQRT(L{$rowN}))");
$sheet1->setCellValue("Q{$r}", "=ROUND(T.INV.2T(0.05,Q{$rowN}-1),3)*(Q{$rowStd}/SQRT(Q{$rowN}))");
$sheet1->setCellValue("T{$r}", "t_crit * (s / √n); t_crit = T.INV.2T(0.05; n-1) dibulatkan 3 desimal = 1.984 (df = 99, α = 0.05), sama dengan t_crit yang tercetak di Table 7");
$r++;

// 6. CI Lower Bound
$sheet1->setCellValue("E{$r}", "95% CI Lower Bound");
$sheet1->setCellValue("G{$r}", "=G{$rowMean}-G{$rowMargin}");
$sheet1->setCellValue("L{$r}", "=L{$rowMean}-L{$rowMargin}");
$sheet1->setCellValue("Q{$r}", "=Q{$rowMean}-Q{$rowMargin}");
$sheet1->setCellValue("T{$r}", "Batas bawah interval kepercayaan 95% (Mean - Margin of Error)");
$r++;

// 7. CI Upper Bound
$sheet1->setCellValue("E{$r}", "95% CI Upper Bound");
$sheet1->setCellValue("G{$r}", "=G{$rowMean}+G{$rowMargin}");
$sheet1->setCellValue("L{$r}", "=L{$rowMean}+L{$rowMargin}");
$sheet1->setCellValue("Q{$r}", "=Q{$rowMean}+Q{$rowMargin}");
$sheet1->setCellValue("T{$r}", "Batas atas interval kepercayaan 95% (Mean + Margin of Error)");
$r++;

// 8. Mean Difference (D̄)
$sheet1->setCellValue("E{$r}", "Mean Difference (D̄ = μ_Ngram - μ_LSH)");
$sheet1->setCellValue("L{$r}", "=L{$rowMean}-Q{$rowMean}");
$sheet1->setCellValue("T{$r}", "Selisih penghematan rata-rata latensi LSH terhadap N-Gram");
$r++;

// 9. Pearson Correlation
$sheet1->setCellValue("E{$r}", "Pearson Correlation (r)");
$sheet1->setCellValue("L{$r}", "=CORREL(L3:L{$lastDataRow}, Q3:Q{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Korelasi linier kueri berpasangan N-Gram dan LSH");
$r++;

// 10. Degrees of Freedom
$sheet1->setCellValue("E{$r}", "Degrees of Freedom (df = N - 1)");
$sheet1->setCellValue("L{$r}", "=L{$rowN}-1");
$sheet1->setCellValue("T{$r}", "Derajat kebebasan pengujian berpasangan (paired degrees of freedom)");
$r++;

// 11. t-statistic
$rowTstat = $r;
$sheet1->setCellValue("E{$r}", "t-statistic (t_calc)");
$sheet1->setCellValue("L{$r}", "=AVERAGE(V3:V{$lastDataRow})/(STDEV.S(V3:V{$lastDataRow})/SQRT(COUNT(V3:V{$lastDataRow})))");
$sheet1->setCellValue("T{$r}", "t = rata-rata selisih / (SD selisih / √n), dihitung dari kolom V (bukan angka ketikan)");
$r++;

// 12. P-Value
$rowPval = $r;
$sheet1->setCellValue("E{$r}", "P-Value (Two-Tailed)");
$sheet1->setCellValue("L{$r}", "=T.TEST(L3:L{$lastDataRow}, Q3:Q{$lastDataRow}, 2, 1)");
$sheet1->setCellValue("T{$r}", "Uji t berpasangan dua arah (T.TEST tipe 1 paired, tail 2)");
$r++;

// 13. Decision
$sheet1->setCellValue("E{$r}", "Keputusan Statistik (alpha = 0.05)");
$sheet1->setCellValue("L{$r}", 'Reject H0 (p < 0.001)');
$sheet1->setCellValue("T{$r}", 'Keunggulan latensi LSH terbukti signifikan secara statistik');
$sheet1->getStyle("L{$r}")->getFont()->setBold(true)->getColor()->setRGB('C00000');
$r++;

// 14. Critical t value
$rowTcrit = $r;
$sheet1->setCellValue("E{$r}", "t kritis (two-tailed, α = 0.05)");
$sheet1->setCellValue("L{$r}", "=T.INV.2T(0.05,L{$rowN}-1)");
$sheet1->setCellValue("T{$r}", "Nilai t_crit di Table 7 naskah (1.984)");
$sheet1->getStyle("L{$r}")->getNumberFormat()->setFormatCode('0.000');
$r++;

// 15-17. Evidence of the "mean ± x" strings printed in the manuscript
$rowPm8 = $r;
$sheet1->setCellValue("E{$r}", "Teks Table 8 naskah: Latensi (Mean ± Margin of Error 95%)");
$sheet1->setCellValue("G{$r}", "=TEXT(G{$rowMean},\"0.00\")&\" ± \"&TEXT(G{$rowMargin},\"0.00\")");
$sheet1->setCellValue("L{$r}", "=TEXT(L{$rowMean},\"0.00\")&\" ± \"&TEXT(L{$rowMargin},\"0.00\")");
$sheet1->setCellValue("Q{$r}", "=TEXT(Q{$rowMean},\"0.00\")&\" ± \"&TEXT(Q{$rowMargin},\"0.00\")");
$sheet1->setCellValue("T{$r}", "Angka sesudah ± di Table 8 adalah MARGIN OF ERROR CI 95% (= t_crit·s/√n), bukan standar deviasi. Contoh LSH: 1.984 × 125.09 / √100 = 24.82");
$r++;

$sheet1->setCellValue("E{$r}", "Pembanding: Mean ± Standar Deviasi (s)");
$sheet1->setCellValue("G{$r}", "=TEXT(G{$rowMean},\"0.00\")&\" ± \"&TEXT(G{$rowStd},\"0.00\")");
$sheet1->setCellValue("L{$r}", "=TEXT(L{$rowMean},\"0.00\")&\" ± \"&TEXT(L{$rowStd},\"0.00\")");
$sheet1->setCellValue("Q{$r}", "=TEXT(Q{$rowMean},\"0.00\")&\" ± \"&TEXT(Q{$rowStd},\"0.00\")");
$sheet1->setCellValue("T{$r}", "Bukan format yang dipakai di Table 8; ditampilkan agar perbedaan SD vs margin of error jelas (SD ada di Table 7)");
$r++;

$rowNeg = $r;
$sheet1->setCellValue("E{$r}", "Kontrol kualitas: jumlah latensi negatif");
$sheet1->setCellValue("G{$r}", "=COUNTIF(G3:G{$lastDataRow},\"<0\")");
$sheet1->setCellValue("L{$r}", "=COUNTIF(L3:L{$lastDataRow},\"<0\")");
$sheet1->setCellValue("Q{$r}", "=COUNTIF(Q3:Q{$lastDataRow},\"<0\")");
$sheet1->setCellValue("T{$r}", "Latensi tidak mungkin negatif. Nilai negatif (sel merah) berasal dari timer microtime() yang mundur saat run; dibiarkan apa adanya, bukan diedit");
$sheet1->getStyle("G{$r}:Q{$r}")->getNumberFormat()->setFormatCode('0');
$sheet1->getStyle("E{$r}:T{$r}")->getFont()->getColor()->setRGB('C00000');
$r++;

$sheet1->getStyle("E{$t7HeaderRow}:U" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet1->getStyle("G{$rowMean}:Q" . ($rowPval - 1))->getNumberFormat()->setFormatCode('0.00');
$sheet1->getStyle("L{$rowPval}")->getNumberFormat()->setFormatCode('0.00E+00');
$sheet1->getStyle("L" . ($rowPval - 1))->getNumberFormat()->setFormatCode('0.000');

// -----------------------------------------------------------------------------
// TABLE B: METRIK KLASIFIKASI & RETRIEVAL (TABLE 8 & FIGURE 6 DI NASKAH)
// -----------------------------------------------------------------------------
$r += 2;
$t8Start = $r;

$sheet1->mergeCells("E{$t8Start}:U{$t8Start}");
$sheet1->setCellValue("E{$t8Start}", "TABLE 8 & FIGURE 7. RINGKASAN METRIK KLASIFIKASI & RETRIEVAL PASCA CLIENT FILTERING (tau = 85%)");
$sheet1->getStyle("E{$t8Start}:U{$t8Start}")->applyFromArray($headerStylePrimary);
$sheet1->getStyle("E{$t8Start}")->getFont()->setSize(11);

$t8HeaderRow = $t8Start + 1;
$sheet1->setCellValue("E{$t8HeaderRow}", "Metrik Evaluasi & Deskripsi");
$sheet1->setCellValue("G{$t8HeaderRow}", "Exact Match");
$sheet1->setCellValue("L{$t8HeaderRow}", "N-Gram (theta = 0.40)");
$sheet1->setCellValue("Q{$t8HeaderRow}", "LSH MinHash (k = 3)");
$sheet1->setCellValue("T{$t8HeaderRow}", "Keterangan Rumus / Penjelasan Sumber Data");

$sheet1->getStyle("E{$t8HeaderRow}:U{$t8HeaderRow}")->getFont()->setBold(true);
$sheet1->getStyle("E{$t8HeaderRow}:U{$t8HeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2EFDA');
$sheet1->getStyle("E{$t8HeaderRow}:U{$t8HeaderRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$r = $t8HeaderRow + 1;

// Row 1: True Positives (TP)
$rowTp = $r;
$sheet1->setCellValue("E{$r}", "True Positives (TP) [Target Ditemukan]");
$sheet1->setCellValue("G{$r}", "=COUNTIF(H3:H{$lastDataRow}, 100)");
$sheet1->setCellValue("L{$r}", "=COUNTIF(M3:M{$lastDataRow}, 100)");
$sheet1->setCellValue("Q{$r}", "=COUNTIF(R3:R{$lastDataRow}, 100)");
$sheet1->setCellValue("T{$r}", "Jumlah kueri di mana dokumen target berhasil ditemukan (Recall = 100%)");
$r++;

// Row 2: False Negatives (FN)
$rowFn = $r;
$sheet1->setCellValue("E{$r}", "False Negatives (FN) [Target Terlewat]");
$sheet1->setCellValue("G{$r}", "=COUNTIF(H3:H{$lastDataRow}, 0)");
$sheet1->setCellValue("L{$r}", "=COUNTIF(M3:M{$lastDataRow}, 0)");
$sheet1->setCellValue("Q{$r}", "=COUNTIF(R3:R{$lastDataRow}, 0)");
$sheet1->setCellValue("T{$r}", "Jumlah kueri di mana dokumen target gagal ditemukan (Recall = 0%)");
$r++;

// Row 3: Total False Positives (FP)
$rowFp = $r;
$sheet1->setCellValue("E{$r}", "Total False Positives (FP) [Akumulasi Lolos Klien]");
$sheet1->setCellValue("G{$r}", "=SUM(K3:K{$lastDataRow})");
$sheet1->setCellValue("L{$r}", "=SUM(P3:P{$lastDataRow})");
$sheet1->setCellValue("Q{$r}", "=SUM(U3:U{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Akumulasi total dokumen bukan-target yang tidak sengaja lolos penyaringan klien");
$r++;

// Row 4: Recall (%)
$rowRecall = $r;
$sheet1->setCellValue("E{$r}", "Search Recall (%)");
$sheet1->setCellValue("G{$r}", "=AVERAGE(H3:H{$lastDataRow})");
$sheet1->setCellValue("L{$r}", "=AVERAGE(M3:M{$lastDataRow})");
$sheet1->setCellValue("Q{$r}", "=AVERAGE(R3:R{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Rumus: TP / (TP + FN) * 100% atau rata-rata kolom Recall 100 kueri");
$r++;

// Row 5: Precision (%)
$rowPrec = $r;
$sheet1->setCellValue("E{$r}", "Precision (%)");
$sheet1->setCellValue("G{$r}", "=IF(G{$rowTp}+G{$rowFp}>0, (G{$rowTp}/(G{$rowTp}+G{$rowFp}))*100, 0)");
$sheet1->setCellValue("L{$r}", "=IF(L{$rowTp}+L{$rowFp}>0, (L{$rowTp}/(L{$rowTp}+L{$rowFp}))*100, 0)");
$sheet1->setCellValue("Q{$r}", "=IF(Q{$rowTp}+Q{$rowFp}>0, (Q{$rowTp}/(Q{$rowTp}+Q{$rowFp}))*100, 0)");
$sheet1->setCellValue("T{$r}", "Rumus: TP / (TP + FP) * 100% (persentase target dari seluruh dokumen lolos klien)");
$r++;

// Row 6: F1-Score (%)
$rowF1 = $r;
$sheet1->setCellValue("E{$r}", "F1-Score (%)");
$sheet1->setCellValue("G{$r}", "=IF(G{$rowPrec}+G{$rowRecall}>0, 2*G{$rowPrec}*G{$rowRecall}/(G{$rowPrec}+G{$rowRecall}), 0)");
$sheet1->setCellValue("L{$r}", "=IF(L{$rowPrec}+L{$rowRecall}>0, 2*L{$rowPrec}*L{$rowRecall}/(L{$rowPrec}+L{$rowRecall}), 0)");
$sheet1->setCellValue("Q{$r}", "=IF(Q{$rowPrec}+Q{$rowRecall}>0, 2*Q{$rowPrec}*Q{$rowRecall}/(Q{$rowPrec}+Q{$rowRecall}), 0)");
$sheet1->setCellValue("T{$r}", "Harmonic Mean: 2 * (Precision * Recall) / (Precision + Recall)");
$r++;

// Row 7: False Positive Rate (FPR) (%)
$sheet1->setCellValue("E{$r}", "False Positive Rate (FPR) (%)");
$sheet1->setCellValue("G{$r}", "=G{$rowFp}/(100*(10469-1))*100");
$sheet1->setCellValue("L{$r}", "=L{$rowFp}/(100*(10469-1))*100");
$sheet1->setCellValue("Q{$r}", "=Q{$rowFp}/(100*(10469-1))*100");
$sheet1->setCellValue("T{$r}", "FP / (FP + TN) * 100%. Tiap kueri punya 1 target; 10.468 record lain = negatif, jadi FP + TN = 100 kueri x 10.468 = 1.046.800 (perbaikan 2026-10-18; versi lama membagi FP dengan 1.000)");
$r++;

// Row 8: Server Candidate Average (Pre-Filter, Fig. 7)
$rowCandAvg = $r;
$sheet1->setCellValue("E{$r}", "Rata-rata Kandidat Cloud Server (Pre-Filter, Fig. 7)");
$sheet1->setCellValue("G{$r}", "=AVERAGE(I3:I{$lastDataRow})");
$sheet1->setCellValue("L{$r}", "=AVERAGE(N3:N{$lastDataRow})");
$sheet1->setCellValue("Q{$r}", "=AVERAGE(S3:S{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Rata-rata jumlah dokumen ciphertext yang ditarik dari Cloud Server per kueri");
$r++;

// Row 9: Client Retained Average (Post-Filter, Fig. 7)
$rowRetAvg = $r;
$sheet1->setCellValue("E{$r}", "Rata-rata Dokumen Lolos Klien (Post-Filter, Fig. 7)");
$sheet1->setCellValue("G{$r}", "=AVERAGE(J3:J{$lastDataRow})");
$sheet1->setCellValue("L{$r}", "=AVERAGE(O3:O{$lastDataRow})");
$sheet1->setCellValue("Q{$r}", "=AVERAGE(T3:T{$lastDataRow})");
$sheet1->setCellValue("T{$r}", "Rata-rata jumlah dokumen yang lolos verifikasi kesamaan string (tau = 85%)");
$r++;

// Row 10: Pruning Rate (%)
$sheet1->setCellValue("E{$r}", "Candidate Pruning Rate (%)");
$sheet1->setCellValue("G{$r}", "=IF(G{$rowCandAvg}>0, (G{$rowCandAvg}-G{$rowRetAvg})/G{$rowCandAvg}*100, 0)");
$sheet1->setCellValue("L{$r}", "=IF(L{$rowCandAvg}>0, (L{$rowCandAvg}-L{$rowRetAvg})/L{$rowCandAvg}*100, 0)");
$sheet1->setCellValue("Q{$r}", "=IF(Q{$rowCandAvg}>0, (Q{$rowCandAvg}-Q{$rowRetAvg})/Q{$rowCandAvg}*100, 0)");
$sheet1->setCellValue("T{$r}", "Efisiensi pemangkasan false positive di sisi klien: (Pre - Post) / Pre * 100%");
$r++;

$sheet1->getStyle("E{$t8HeaderRow}:U" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet1->getStyle("G{$rowTp}:Q" . ($rowFp))->getNumberFormat()->setFormatCode('#,##0');
$sheet1->getStyle("G{$rowRecall}:Q" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');

// Column widths
foreach (range('A', 'V') as $col) {
    $sheet1->getColumnDimension($col)->setAutoSize(true);
}

// =============================================================================
// SHEET 2: ANALISIS SENSITIVITAS MULTI-PARAMETER (FIGURE 7 DI NASKAH)
// =============================================================================
if (!empty($sens)) {
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Analisis_Sensitivitas');

    $sheet2->setCellValue('A1', 'FIGURE 8. ANALISIS SENSITIVITAS MULTI-PARAMETER ALGORITMA (k, tau, theta)');
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(12);

    // Section 1: k sweep
    $sheet2->setCellValue('A3', '1. PENGARUH DIMENSI MINHASH (k) TERHADAP RECALL, LATENSI, DAN KANDIDAT (tau = 85%) [Fig. 8a]');
    $sheet2->getStyle('A3')->getFont()->setBold(true);

    $kHeaders = ['k (Bands)', 'Rata-rata Latensi (ms)', 'Standar Deviasi (ms)', 'Rata-rata Kandidat Server', 'Recall (%)', 'Precision (%)'];
    $col = 'A';
    foreach ($kHeaders as $h) {
        $sheet2->setCellValue($col . '4', $h);
        $col++;
    }
    $sheet2->getStyle('A4:F4')->applyFromArray($headerStylePrimary);

    $r = 5;
    foreach ($sens['k_sweep'] as $k => $data) {
        $sheet2->setCellValue("A{$r}", $k);
        $sheet2->setCellValue("B{$r}", $data['latency']['mean']);
        $sheet2->setCellValue("C{$r}", $data['latency']['std_dev']);
        $sheet2->setCellValue("D{$r}", $data['candidates']['mean']);
        $sheet2->setCellValue("E{$r}", $data['classification']['recall']);
        $sheet2->setCellValue("F{$r}", $data['classification']['precision']);
        $r++;
    }
    $sheet2->getStyle("A4:F" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet2->getStyle("A5:A" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("B5:F" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');

    // Section 2: tau sweep
    $r += 2;
    $sheet2->setCellValue("A{$r}", '2. PENGARUH AMBANG BATAS KESAMAAN KLIEN (tau) TERHADAP RECALL DAN PRECISION (k = 3) [Fig. 8b]');
    $sheet2->getStyle("A{$r}")->getFont()->setBold(true);
    $r++;

    $tauHeaders = ['Ambang Batas tau (%)', 'Rata-rata Latensi (ms)', 'Recall (%)', 'Precision (%)', 'Keterangan Analitis'];
    $col = 'A';
    foreach ($tauHeaders as $h) {
        $sheet2->setCellValue($col . $r, $h);
        $col++;
    }
    $sheet2->getStyle("A{$r}:E{$r}")->applyFromArray($headerStylePrimary);
    $headR = $r;
    $r++;

    $notes = [
        '70' => 'Recall sangat tinggi, namun meloloskan banyak noise kandidat non-target',
        '75' => 'Penyaringan false positive mulai membaik, recall tetap terjaga tinggi',
        '80' => 'Zona transisi toleransi kesalahan klinis yang stabil',
        '85' => 'Titik kompromi optimal (Recommended): Recall 88% dengan 96% false positive dipangkas',
        '90' => 'Threshold terlalu ketat: Recall anjlok karena banyak typo klinis ditolak filter',
        '95' => 'Kegagalan toleransi typo: Hanya kueri yang hampir identik yang diloloskan filter klien',
    ];

    foreach ($sens['tau_sweep'] as $tau => $data) {
        $sheet2->setCellValue("A{$r}", $tau . '%');
        $sheet2->setCellValue("B{$r}", $data['latency']['mean']);
        $sheet2->setCellValue("C{$r}", $data['classification']['recall']);
        $sheet2->setCellValue("D{$r}", $data['classification']['precision']);
        $sheet2->setCellValue("E{$r}", $notes[$tau] ?? '');
        $r++;
    }
    $sheet2->getStyle("A{$headR}:E" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet2->getStyle("A" . ($headR + 1) . ":A" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("B" . ($headR + 1) . ":D" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');

    // Section 3: theta sweep
    $r += 2;
    $sheet2->setCellValue("A{$r}", '3. PENGARUH HIT-RATIO N-GRAM (theta) TERHADAP KANDIDAT CLOUD DAN LATENSI');
    $sheet2->getStyle("A{$r}")->getFont()->setBold(true);
    $r++;

    $thHeaders = ['Ambang Batas theta', 'Rata-rata Latensi (ms)', 'Rata-rata Kandidat Server', 'Recall (%)'];
    $col = 'A';
    foreach ($thHeaders as $h) {
        $sheet2->setCellValue($col . $r, $h);
        $col++;
    }
    $sheet2->getStyle("A{$r}:D{$r}")->applyFromArray($headerStylePrimary);
    $headR = $r;
    $r++;

    foreach ($sens['theta_sweep'] as $th => $data) {
        $sheet2->setCellValue("A{$r}", $th);
        $sheet2->setCellValue("B{$r}", $data['latency']['mean']);
        $sheet2->setCellValue("C{$r}", $data['candidates']['mean']);
        $sheet2->setCellValue("D{$r}", $data['classification']['recall']);
        $r++;
    }
    $sheet2->getStyle("A{$headR}:D" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet2->getStyle("A" . ($headR + 1) . ":A" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle("B" . ($headR + 1) . ":D" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');

    foreach (range('A', 'F') as $col) {
        $sheet2->getColumnDimension($col)->setAutoSize(true);
    }
}

// =============================================================================
// SHEET 3: PROYEKSI SKALABILITAS ENTERPRISE (TABLE 11 & FIGURE 8 DI NASKAH)
// =============================================================================
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Proyeksi_Skalabilitas');

$sheet3->setCellValue('A1', 'FIGURE 9 (SECTION 3.5). ANALYTICAL SCALABILITY PROJECTION CALIBRATED ON OBSERVED ICD-10 DENSITY');
$sheet3->getStyle('A1')->getFont()->setBold(true)->setSize(12);

$sheet3->setCellValue('A2', 'Kalibrasi Densitas Dasar (10.469 Dokumen): Exact = 10.51 baris/doc, N-Gram = 83.99 baris/doc, LSH k=3 = 31.55 baris/doc, LSH k=5 = 52.58 baris/doc (128 bytes/row)');
$sheet3->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

$scaleHeaders = [
    'A4' => 'Jumlah Rekam Medis (Dokumen N)',
    'B4' => 'Baris Indeks Exact',
    'C4' => 'Storage Exact (MB)',
    'D4' => 'Baris Indeks N-Gram',
    'E4' => 'Storage N-Gram (MB)',
    'F4' => 'Baris Indeks LSH (k=3)',
    'G4' => 'Storage LSH k=3 (MB)',
    'H4' => 'Baris Indeks LSH (k=5)',
    'I4' => 'Storage LSH k=5 (MB)',
    'J4' => 'Efisiensi Storage LSH vs N-Gram (%)'
];

foreach ($scaleHeaders as $cell => $h) {
    $sheet3->setCellValue($cell, $h);
}
$sheet3->getStyle('A4:J4')->applyFromArray($headerStylePrimary);

$corpusSizes = [10469, 50000, 100000, 250000, 500000, 1000000];

$r = 5;
foreach ($corpusSizes as $idx => $n) {
    $sheet3->setCellValue("A{$r}", $n);
    
    if ($n === 10469) {
        // Baseline measured values
        $sheet3->setCellValue("B{$r}", 110102);
        $sheet3->setCellValue("C{$r}", "=(B{$r}*128)/(1024*1024)");
        $sheet3->setCellValue("D{$r}", 879327);
        $sheet3->setCellValue("E{$r}", "=(D{$r}*128)/(1024*1024)");
        $sheet3->setCellValue("F{$r}", 330306);
        $sheet3->setCellValue("G{$r}", "=(F{$r}*128)/(1024*1024)");
        $sheet3->setCellValue("H{$r}", 550510);
        $sheet3->setCellValue("I{$r}", "=(H{$r}*128)/(1024*1024)");
    } else {
        // Projections via pure Excel scaling formulas
        $sheet3->setCellValue("B{$r}", "=ROUND(110102*(A{$r}/10469), 0)");
        $sheet3->setCellValue("C{$r}", "=(B{$r}*128)/(1024*1024)");
        $sheet3->setCellValue("D{$r}", "=ROUND(879327*(A{$r}/10469), 0)");
        $sheet3->setCellValue("E{$r}", "=(D{$r}*128)/(1024*1024)");
        $sheet3->setCellValue("F{$r}", "=ROUND(330306*(A{$r}/10469), 0)");
        $sheet3->setCellValue("G{$r}", "=(F{$r}*128)/(1024*1024)");
        $sheet3->setCellValue("H{$r}", "=ROUND(550510*(A{$r}/10469), 0)");
        $sheet3->setCellValue("I{$r}", "=(H{$r}*128)/(1024*1024)");
    }
    
    // Efficiency formula
    $sheet3->setCellValue("J{$r}", "=(D{$r}-F{$r})/D{$r}");
    $r++;
}

$sheet3->getStyle("A4:J" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet3->getStyle("A5:B" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0');
$sheet3->getStyle("D5:D" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0');
$sheet3->getStyle("F5:F" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0');
$sheet3->getStyle("H5:H" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0');
$sheet3->getStyle("C5:C" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$sheet3->getStyle("E5:E" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$sheet3->getStyle("G5:G" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$sheet3->getStyle("I5:I" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$sheet3->getStyle("J5:J" . ($r - 1))->getNumberFormat()->setFormatCode('0.0%');

foreach (range('A', 'J') as $col) {
    $sheet3->getColumnDimension($col)->setAutoSize(true);
}

// =============================================================================
// SHEET 4: DIVERSITAS KUERI & STRATIFIKASI LINGUISTIK (TABLE 10 DI NASKAH)
// =============================================================================
if (!empty($diverse)) {
    $sheet4 = $spreadsheet->createSheet();
    $sheet4->setTitle('Diversitas_Kueri_Tabel9');

    $sheet4->setCellValue('A1', 'TABLE 9 (SECTION 3.4). EVALUASI DIVERSITAS KUERI KLINIS & STRATIFIKASI LINGUISTIK/KESALAHAN');
    $sheet4->getStyle('A1')->getFont()->setBold(true)->setSize(12);

    $sheet4->setCellValue('A3', 'RINGKASAN STRATIFIKASI BERDASARKAN 5 DIMENSI DIVERSITAS KUERI (FORMULA NATIVE DARI DATA DI BAWAH)');
    $sheet4->getStyle('A3')->getFont()->setBold(true);

    $divHeaders = [
        'A4' => 'Dimensi Diversitas Kueri',
        'B4' => 'Jumlah Sampel (n)',
        'C4' => 'Recall Exact (%)',
        'D4' => 'Recall N-Gram (%)',
        'E4' => 'Recall LSH (%)',
        'F4' => 'Latensi N-Gram (ms)',
        'G4' => 'Latensi LSH (ms)',
        'H4' => 'Efisiensi Latensi LSH (%)',
        'I4' => 'SD N-Gram (ms)',
        'J4' => 'SD LSH (ms)',
        'K4' => 'Teks Table 9: N-Gram (Mean ± SD)',
        'L4' => 'Teks Table 9: LSH (Mean ± SD)',
        'M4' => 'Paired t',
        'N4' => 'df',
    ];
    foreach ($divHeaders as $cell => $h) {
        $sheet4->setCellValue($cell, $h);
    }
    $sheet4->getStyle('A4:N4')->applyFromArray($headerStylePrimary);

    // Dynamic row formula ranges for 5 strata
    $strataRanges = [
        // Raw rows start at 16: 6 summary rows (5-10), note (12), title (14), header (15).
        // Verified after generation: the loop below asserts the stratum label of each row.
        'Short Terms (4-6 chars)'            => [16, 35],
        'Medium Terms (7-10 chars)'          => [36, 55],
        'Long Polysyllabic (>=11 chars)'     => [56, 75],
        'Compound Typo (Edit Distance d=2)'  => [76, 95],
        'Phonetic/Orthographic Shifts'       => [96, 115],
        'Pooled (n = 100)'                   => [16, 115],
    ];

    $r = 5;
    foreach ($strataRanges as $dimName => $rng) {
        $startRow = $rng[0];
        $endRow = $rng[1];

        $sheet4->setCellValue("A{$r}", $dimName);
        $sheet4->setCellValue("B{$r}", "=COUNT(H{$startRow}:H{$endRow})");
        $sheet4->setCellValue("C{$r}", "=AVERAGE(I{$startRow}:I{$endRow})");
        $sheet4->setCellValue("D{$r}", "=AVERAGE(K{$startRow}:K{$endRow})");
        $sheet4->setCellValue("E{$r}", "=AVERAGE(M{$startRow}:M{$endRow})");
        $sheet4->setCellValue("F{$r}", "=AVERAGE(J{$startRow}:J{$endRow})");
        $sheet4->setCellValue("G{$r}", "=AVERAGE(L{$startRow}:L{$endRow})");
        $sheet4->setCellValue("H{$r}", "=(F{$r}-G{$r})/F{$r}");
        $sheet4->setCellValue("I{$r}", "=STDEV.S(J{$startRow}:J{$endRow})");
        $sheet4->setCellValue("J{$r}", "=STDEV.S(L{$startRow}:L{$endRow})");
        $sheet4->setCellValue("K{$r}", "=TEXT(F{$r},\"0.00\")&\" ± \"&TEXT(I{$r},\"0.0\")");
        $sheet4->setCellValue("L{$r}", "=TEXT(G{$r},\"0.00\")&\" ± \"&TEXT(J{$r},\"0.0\")");
        $sheet4->setCellValue("M{$r}", "=AVERAGE(N{$startRow}:N{$endRow})/(STDEV.S(N{$startRow}:N{$endRow})/SQRT(COUNT(N{$startRow}:N{$endRow})))");
        $sheet4->setCellValue("N{$r}", "=COUNT(N{$startRow}:N{$endRow})-1");
        $r++;
    }
    $sheet4->getStyle("I5:J" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');
    $sheet4->getStyle("M5:M" . ($r - 1))->getNumberFormat()->setFormatCode('0.000');
    $sheet4->getStyle("A" . ($r - 1) . ":N" . ($r - 1))->getFont()->setBold(true);
    $sheet4->setCellValue("A" . ($r + 1), 'Catatan: angka sesudah ± di Table 9 adalah STANDAR DEVIASI sampel (STDEV.S), berbeda dengan Table 8 yang memakai margin of error CI 95%. Paired t = rata-rata selisih kolom N / (SD selisih / √n).');
    $sheet4->getStyle("A" . ($r + 1))->getFont()->setItalic(true);
    $r++;
    $sheet4->getStyle("A4:N" . ($r - 2))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet4->getStyle("B5:E" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet4->getStyle("C5:E" . ($r - 1))->getNumberFormat()->setFormatCode('0.0');
    $sheet4->getStyle("F5:G" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');
    $sheet4->getStyle("H5:H" . ($r - 1))->getNumberFormat()->setFormatCode('0.0%');

    // 100 Sample Raw Data Table
    $r += 2;
    $sheet4->setCellValue("A{$r}", 'DATA MENTAH 100 SAMPEL PENGUJIAN DIVERSE (PER-QUERY DETAIL DENGAN 5 STRATA)');
    $sheet4->getStyle("A{$r}")->getFont()->setBold(true);
    $r++;

    $rawHeaders = [
        'A' => 'No', 'B' => 'Dimensi Diversitas', 'C' => 'Doc ID', 'D' => 'Kata Asli', 'E' => 'Kueri Typo',
        'F' => 'Panjang', 'G' => 'Diagnosis Penyakit', 'H' => 'Waktu Exact (ms)', 'I' => 'Recall Exact',
        'J' => 'Waktu N-Gram (ms)', 'K' => 'Recall N-Gram', 'L' => 'Waktu LSH (ms)', 'M' => 'Recall LSH',
        'N' => 'Selisih N-Gram - LSH (ms)'
    ];
    foreach ($rawHeaders as $col => $h) {
        $sheet4->setCellValue($col . $r, $h);
    }
    $sheet4->getStyle("A{$r}:N{$r}")->applyFromArray($headerStylePrimary);
    $headR = $r;
    $r++;

    foreach ($diverse['detailed_results'] as $idx => $item) {
        $c = $item['case'];
        $ex = $item['exact'];
        $ng = $item['ngram'];
        $ls = $item['lsh'];

        $sheet4->setCellValue("A{$r}", $idx + 1);
        $sheet4->setCellValue("B{$r}", $c['diversity_dimension']);
        $sheet4->setCellValue("C{$r}", $c['doc_id']);
        $sheet4->setCellValue("D{$r}", $c['original_word']);
        $sheet4->setCellValue("E{$r}", $c['typo_query']);
        $sheet4->setCellValue("F{$r}", $c['term_length']);
        $sheet4->setCellValue("G{$r}", $c['disease_name']);
        $sheet4->setCellValue("H{$r}", $ex['latency_total']);
        $sheet4->setCellValue("I{$r}", $ex['is_recalled'] ? 100 : 0);
        $sheet4->setCellValue("J{$r}", $ng['latency_total']);
        $sheet4->setCellValue("K{$r}", $ng['is_recalled'] ? 100 : 0);
        $sheet4->setCellValue("L{$r}", $ls['latency_total']);
        $sheet4->setCellValue("M{$r}", $ls['is_recalled'] ? 100 : 0);
        $sheet4->setCellValue("N{$r}", "=J{$r}-L{$r}");
        $r++;
    }

    // Guard: every raw row must sit inside the range declared for its stratum.
    foreach ($strataRanges as $dimName => $rng) {
        if ($dimName === 'Pooled (n = 100)') {
            continue;
        }
        for ($rr = $rng[0]; $rr <= $rng[1]; $rr++) {
            if ($sheet4->getCell("B{$rr}")->getValue() !== $dimName) {
                throw new RuntimeException("Stratum range mismatch at row {$rr} (expected {$dimName})");
            }
        }
    }
    $sheet4->getStyle("N" . ($headR + 1) . ":N" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');
    $sheet4->getStyle("A{$headR}:N" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet4->getStyle("A" . ($headR + 1) . ":A" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet4->getStyle("C" . ($headR + 1) . ":C" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet4->getStyle("F" . ($headR + 1) . ":F" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet4->getStyle("I" . ($headR + 1) . ":I" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet4->getStyle("K" . ($headR + 1) . ":K" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet4->getStyle("M" . ($headR + 1) . ":M" . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet4->getStyle("H" . ($headR + 1) . ":H" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');
    $sheet4->getStyle("J" . ($headR + 1) . ":J" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');
    $sheet4->getStyle("L" . ($headR + 1) . ":L" . ($r - 1))->getNumberFormat()->setFormatCode('0.00');

    foreach (range('A', 'N') as $col) {
        $sheet4->getColumnDimension($col)->setAutoSize(true);
    }
}

// =============================================================================
// SHEET 5: MASTER INDEKS OVERVIEW (TABLE 6 & FIGURE 4 DI NASKAH)
// =============================================================================
$sheet5 = $spreadsheet->createSheet();
$sheet5->setTitle('Master_Indeks_Overview');

$sheet5->setCellValue('A1', 'TABLE 6 & FIGURE 5. MASTER INVERTED INDEX STORAGE OVERHEAD AFTER FULL ICD-10 CORPUS INGESTION');
$sheet5->getStyle('A1')->getFont()->setBold(true)->setSize(12);

$sheet5->setCellValue('A2', 'Dataset Master: 10.469 Rekam Medis ICD-10 (Enkripsi AES-256-CBC Payload, Trapdoor HMAC-SHA256, 128 Bytes/Row Storage Density)');
$sheet5->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

$t6Headers = [
    'A4' => 'Nama Tabel Database',
    'B4' => 'Skema Pengindeksan (Indexing Scheme)',
    'C4' => 'Total Baris Indeks',
    'D4' => 'Rasio Ekspansi vs Baseline',
    'E4' => 'Ukuran Memori Indeks (MB)',
    'F4' => 'Efisiensi Penyimpanan vs N-Gram (%)',
    'G4' => 'Keterangan Arsitektural'
];
foreach ($t6Headers as $cell => $h) {
    $sheet5->setCellValue($cell, $h);
}
$sheet5->getStyle('A4:G4')->applyFromArray($headerStylePrimary);

$indexData = [
    ['icds_encrypted', 'Dokumen Terenkripsi (Payload AES-256-CBC)', 10469, 'N/A', '=(C5*512)/(1024*1024)', 'N/A', 'Tabel penyimpanan ciphertext rekam medis pasien'],
    ['exact_index', 'Exact Match (Baseline Deterministik)', 110102, '=C6/$C$6', '=(C6*128)/(1024*1024)', 'N/A', 'Pemetaan 1:1 kata per trapdoor, gagal total terhadap typo (0% recall)'],
    ['ngram_index', 'N-Gram (Bi-gram Expansion, n = 2)', 879327, '=C7/$C$6', '=(C7*128)/(1024*1024)', 'Baseline (0%)', 'Toleran typo, namun memicu index bloat masif (~8x lipat dari baseline)'],
    ['lsh_index (k=3)', 'LSH MinHash (Recommended, k = 3)', 330306, '=C8/$C$6', '=(C8*128)/(1024*1024)', '=(C$7-C8)/C$7', 'Ukuran konstan 3 band per kata: menghemat 62.4% baris vs N-Gram'],
    ['lsh_index (k=5)', 'LSH MinHash (Extended, k = 5)', 550510, '=C9/$C$6', '=(C9*128)/(1024*1024)', '=(C$7-C9)/C$7', 'Ukuran konstan 5 band per kata: menghemat 37.4% baris vs N-Gram'],
];

$r = 5;
foreach ($indexData as $row) {
    $sheet5->setCellValue("A{$r}", $row[0]);
    $sheet5->setCellValue("B{$r}", $row[1]);
    $sheet5->setCellValue("C{$r}", $row[2]);
    $sheet5->setCellValue("D{$r}", $row[3]);
    $sheet5->setCellValue("E{$r}", $row[4]);
    $sheet5->setCellValue("F{$r}", $row[5]);
    $sheet5->setCellValue("G{$r}", $row[6]);
    $r++;
}

$sheet5->getStyle("A4:G" . ($r - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet5->getStyle("C5:C" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0');
$sheet5->getStyle("D6:D9")->getNumberFormat()->setFormatCode('0.00"x"');
$sheet5->getStyle("E5:E" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$sheet5->getStyle("F8:F9")->getNumberFormat()->setFormatCode('0.0%');

foreach (range('A', 'G') as $col) {
    $sheet5->getColumnDimension($col)->setAutoSize(true);
}

// ---------------------------------------------------------------------------------------------
// Sheet 6: one-page guide to every "±" printed in the manuscript + n = 98 sensitivity scenario
// ---------------------------------------------------------------------------------------------
$sheet6 = $spreadsheet->createSheet();
$sheet6->setTitle('Panduan_Angka_PlusMinus');
$s1 = "'Pengujian_100_Sampel'!";
$s4 = "'Diversitas_Kueri_Tabel9'!";
$last = $lastDataRow;

$sheet6->setCellValue('A1', 'PANDUAN ANGKA "±" DI NASKAH: ARTI, RUMUS, DAN ASAL DATA');
$sheet6->getStyle('A1')->getFont()->setBold(true)->setSize(12);
$sheet6->setCellValue('A2', 'Setiap baris di bawah menunjuk ke sel sumber (formula hidup). Kolom "Cocok?" membandingkan teks hasil rumus dengan teks yang tercetak di naskah.');

$guideHeaders = ['Lokasi di naskah', 'Skema / strata', 'Teks di naskah', 'Teks hasil rumus', 'Cocok?', 'Arti angka sesudah ±', 'Rumus', 'Contoh hitung', 'Sel sumber'];
foreach ($guideHeaders as $i => $h) {
    $sheet6->setCellValue(chr(65 + $i) . '4', $h);
}
$sheet6->getStyle('A4:I4')->applyFromArray($headerStylePrimary);

$moeDef = 'Margin of error interval kepercayaan 95% (BUKAN standar deviasi)';
$moeFormula = 'MoE = t_crit × s / √n, t_crit = T.INV.2T(0,05; n−1) = 1,984 (df = 99)';
$sdDef = 'Standar deviasi sampel s (BUKAN margin of error)';
$sdFormula = 's = STDEV.S(data latensi strata)';

$rowsGuide = [
    ['Table 8 (hlm. 10)', 'Exact Match', '1.51 ± 0.07', "={$s1}G{$rowPm8}", $moeDef, $moeFormula,
        "=\"1,984 × \"&TEXT({$s1}G{$rowStd},\"0.00\")&\" / √100 = \"&TEXT({$s1}G{$rowMargin},\"0.00\")", "{$s1}G{$rowMean} (mean), G{$rowStd} (s), G{$rowMargin} (MoE)"],
    ['Table 8 (hlm. 10)', 'N-Gram (θ = 0.40)', '248.43 ± 13.13', "={$s1}L{$rowPm8}", $moeDef, $moeFormula,
        "=\"1,984 × \"&TEXT({$s1}L{$rowStd},\"0.00\")&\" / √100 = \"&TEXT({$s1}L{$rowMargin},\"0.00\")", "{$s1}L{$rowMean}, L{$rowStd}, L{$rowMargin}"],
    ['Table 8 (hlm. 10)', 'LSH (k = 3, τ = 85%)', '74.58 ± 24.82', "={$s1}Q{$rowPm8}", $moeDef, $moeFormula,
        "=\"1,984 × \"&TEXT({$s1}Q{$rowStd},\"0.00\")&\" / √100 = \"&TEXT({$s1}Q{$rowMargin},\"0.00\")", "{$s1}Q{$rowMean}, Q{$rowStd}, Q{$rowMargin}"],
];
$t9 = [
    5 => ['Short Terms (4–6)', '151.87 ± 46.2', '81.26 ± 61.6'],
    6 => ['Medium Terms (7–10)', '197.10 ± 67.1', '81.30 ± 37.7'],
    7 => ['Long Polysyllabic (≥ 11)', '252.58 ± 67.1', '79.43 ± 34.0'],
    8 => ['Compound Typo (d = 2)', '182.85 ± 65.6', '73.36 ± 33.4'],
    9 => ['Phonetic/Orthographic Shifts', '201.40 ± 71.4', '86.35 ± 39.1'],
    10 => ['Pooled (n = 100), juga di teks hlm. 12', '197.16 ± 70.9', '80.34 ± 41.8'],
];
foreach ($t9 as $srcRow => [$name, $ng, $lsh]) {
    $rowsGuide[] = ['Table 9 (hlm. 12)', "{$name}: N-Gram", $ng, "={$s4}K{$srcRow}", $sdDef, $sdFormula,
        "=\"s = \"&TEXT({$s4}I{$srcRow},\"0.00\")", "{$s4}F{$srcRow} (mean), I{$srcRow} (s)"];
    $rowsGuide[] = ['Table 9 (hlm. 12)', "{$name}: LSH", $lsh, "={$s4}L{$srcRow}", $sdDef, $sdFormula,
        "=\"s = \"&TEXT({$s4}J{$srcRow},\"0.00\")", "{$s4}G{$srcRow} (mean), J{$srcRow} (s)"];
}

$r = 5;
foreach ($rowsGuide as [$loc, $scheme, $printed, $live, $def, $formula, $example, $src]) {
    $sheet6->setCellValue("A{$r}", $loc);
    $sheet6->setCellValue("B{$r}", $scheme);
    $sheet6->setCellValueExplicit("C{$r}", $printed, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet6->setCellValue("D{$r}", $live);
    $sheet6->setCellValue("E{$r}", "=IF(D{$r}=C{$r},\"YA\",\"TIDAK\")");
    $sheet6->setCellValue("F{$r}", $def);
    $sheet6->setCellValue("G{$r}", $formula);
    $sheet6->setCellValue("H{$r}", $example);
    $sheet6->setCellValue("I{$r}", $src);
    $r++;
}
$guideEnd = $r - 1;
$sheet6->getStyle("A4:I{$guideEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet6->getStyle("A5:I{$guideEnd}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
$sheet6->getStyle("A5:B7")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
$sheet6->getStyle("A8:B{$guideEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2EFDA');

$r += 1;
$sheet6->setCellValue("A{$r}", 'Catatan untuk naskah: kedua tabel memakai "±" dengan arti berbeda (Table 8 = margin of error 95%, Table 9 = standar deviasi). Sebaiknya arti "±" ditulis di caption masing-masing tabel.');
$sheet6->getStyle("A{$r}")->getFont()->setBold(true);
$r += 3;

// --- Sensitivity: the two negative LSH latencies --------------------------------------------
$sheet6->setCellValue("A{$r}", 'SKENARIO SENSITIVITAS: DAMPAK 2 LATENSI LSH NEGATIF (Table 7 & 8)');
$sheet6->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12);
$r++;
$sheet6->setCellValue("A{$r}", 'Latensi tidak mungkin < 0. Dua nilai negatif berasal dari timer microtime() (jam dinding) yang mundur saat benchmark. Data mentah TIDAK diubah; skenario B hanya menghitung ulang dengan mengecualikan pasangan kueri yang LSH-nya < 0.');
$r += 2;
$senHead = $r;
foreach (['Statistik', 'A. Seperti di naskah (n = 100)', 'B. Tanpa 2 nilai negatif (n = 98)', 'Rumus skenario B'] as $i => $h) {
    $sheet6->setCellValue(chr(65 + $i) . $r, $h);
}
$sheet6->getStyle("A{$r}:D{$r}")->applyFromArray($headerStylePrimary);
$r++;

$L = "{$s1}L3:L{$last}";
$Q = "{$s1}Q3:Q{$last}";
$V = "{$s1}V3:V{$last}";
$ok = "({$Q}>=0)";
$rN = $r;
$sen = [
    ['Jumlah pasangan kueri (n)', "={$s1}Q{$rowN}", "=COUNTIF({$Q},\">=0\")", 'COUNTIF(LSH ≥ 0)'],
    ['Jumlah latensi LSH negatif', "={$s1}Q{$rowNeg}", '=0', 'dikecualikan'],
    ['Mean LSH (ms)', "={$s1}Q{$rowMean}", "=AVERAGEIF({$Q},\">=0\")", 'AVERAGEIF(LSH ≥ 0)'],
    ['SD LSH (ms)', "={$s1}Q{$rowStd}", "=SQRT(SUMPRODUCT({$ok}*({$Q}-C" . ($rN + 2) . ")^2)/(C{$rN}-1))", 'SD sampel hanya baris LSH ≥ 0'],
    ['t_crit (α = 0.05, df = n − 1)', "=T.INV.2T(0.05,B{$rN}-1)", "=T.INV.2T(0.05,C{$rN}-1)", 'T.INV.2T(0,05; n−1)'],
    ['Margin of error LSH (± Table 8)', "={$s1}Q{$rowMargin}", "=C" . ($rN + 4) . "*C" . ($rN + 3) . "/SQRT(C{$rN})", 't_crit × s / √n'],
    ['Teks "Mean ± MoE" LSH', "={$s1}Q{$rowPm8}", "=TEXT(C" . ($rN + 2) . ",\"0.00\")&\" ± \"&TEXT(C" . ($rN + 5) . ",\"0.00\")", ''],
    ['Mean N-Gram pada pasangan yang sama (ms)', "={$s1}L{$rowMean}", "=AVERAGEIFS({$L},{$Q},\">=0\")", 'AVERAGEIFS(N-Gram; LSH ≥ 0)'],
    ['Penurunan latensi LSH vs N-Gram', "=1-B" . ($rN + 2) . "/B" . ($rN + 7), "=1-C" . ($rN + 2) . "/C" . ($rN + 7), '1 − mean LSH / mean N-Gram'],
    ['Mean selisih berpasangan D̄ (ms)', "=AVERAGE({$V})", "=AVERAGEIFS({$V},{$Q},\">=0\")", 'selisih N-Gram − LSH'],
    ['SD selisih (ms)', "=STDEV.S({$V})", "=SQRT(SUMPRODUCT({$ok}*({$V}-C" . ($rN + 9) . ")^2)/(C{$rN}-1))", ''],
    ['Paired t', "={$s1}L{$rowTstat}", "=C" . ($rN + 9) . "/(C" . ($rN + 10) . "/SQRT(C{$rN}))", 'D̄ / (SD selisih / √n)'],
    ['p-value (dua arah)', "=T.DIST.2T(B" . ($rN + 11) . ",B{$rN}-1)", "=T.DIST.2T(C" . ($rN + 11) . ",C{$rN}-1)", 'T.DIST.2T(t; n−1)'],
];
foreach ($sen as [$label, $a, $b, $note]) {
    $sheet6->setCellValue("A{$r}", $label);
    $sheet6->setCellValue("B{$r}", $a);
    $sheet6->setCellValue("C{$r}", $b);
    $sheet6->setCellValue("D{$r}", $note);
    $r++;
}
$senEnd = $r - 1;
$sheet6->getStyle("A{$senHead}:D{$senEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet6->getStyle("B" . ($rN + 2) . ":C" . ($rN + 5))->getNumberFormat()->setFormatCode('0.00');
$sheet6->getStyle("B" . ($rN + 4) . ":C" . ($rN + 4))->getNumberFormat()->setFormatCode('0.000');
$sheet6->getStyle("B" . ($rN + 7) . ":C" . ($rN + 7))->getNumberFormat()->setFormatCode('0.00');
$sheet6->getStyle("B" . ($rN + 8) . ":C" . ($rN + 8))->getNumberFormat()->setFormatCode('0.0%');
$sheet6->getStyle("B" . ($rN + 9) . ":C" . ($rN + 11))->getNumberFormat()->setFormatCode('0.000');
$sheet6->getStyle("B" . ($rN + 12) . ":C" . ($rN + 12))->getNumberFormat()->setFormatCode('0.00E+00');
$r++;
$sheet6->setCellValue("A{$r}", 'Kesimpulan statistik (LSH signifikan lebih cepat, p < 0,001) sama di kedua skenario; yang berubah adalah besarnya angka (mean, ±, persentase penurunan).');

$sheet6->getColumnDimension('A')->setWidth(34);
$sheet6->getColumnDimension('B')->setWidth(30);
$sheet6->getColumnDimension('C')->setWidth(30);
$sheet6->getColumnDimension('D')->setWidth(30);
$sheet6->getColumnDimension('E')->setWidth(9);
$sheet6->getColumnDimension('F')->setWidth(34);
$sheet6->getColumnDimension('G')->setWidth(40);
$sheet6->getColumnDimension('H')->setWidth(30);
$sheet6->getColumnDimension('I')->setWidth(42);

// Reset active sheet to sheet 1
$spreadsheet->setActiveSheetIndex(0);

// Save to destination files
$destinations = [
    __DIR__ . '/../Hasil_Pengujian_SE_Cloud.xlsx',
];

$writer = new Xlsx($spreadsheet);

foreach ($destinations as $dest) {
    $writer->save($dest);
    echo "[SUCCESS] Enhanced formula-linked Excel report saved to: {$dest} (" . number_format(filesize($dest)) . " bytes)\n";
}
