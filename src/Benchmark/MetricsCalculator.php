<?php

declare(strict_types=1);

namespace Fkse\Benchmark;

final class MetricsCalculator
{
    /**
     * Computes retrieval classification metrics (Precision, Recall, F1, FPR).
     *
     * @param int $tp True Positives
     * @param int $fp False Positives
     * @param int $fn False Negatives
     * @param int $tn True Negatives
     * @return array{precision: float, recall: float, f1: float, fpr: float}
     */
    public static function computeClassificationMetrics(int $tp, int $fp, int $fn, int $tn = 0): array
    {
        $precision = ($tp + $fp) > 0 ? (float) ($tp / ($tp + $fp)) : 0.0;
        $recall    = ($tp + $fn) > 0 ? (float) ($tp / ($tp + $fn)) : 0.0;
        $f1        = ($precision + $recall) > 0 ? (float) (2 * ($precision * $recall) / ($precision + $recall)) : 0.0;
        $fpr       = ($fp + $tn) > 0 ? (float) ($fp / ($fp + $tn)) : 0.0;

        return [
            'precision' => round($precision * 100, 2),
            'recall'    => round($recall * 100, 2),
            'f1'        => round($f1 * 100, 2),
            'fpr'       => round($fpr * 100, 2),
        ];
    }

    /**
     * Computes summary statistics: Mean, Sample Variance, Standard Deviation, and 95% Confidence Interval.
     *
     * @param list<float|int> $values
     * @return array{mean: float, variance: float, std_dev: float, ci_95_lower: float, ci_95_upper: float, margin_of_error: float}
     */
    public static function computeDistribution(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return [
                'mean' => 0.0,
                'variance' => 0.0,
                'std_dev' => 0.0,
                'ci_95_lower' => 0.0,
                'ci_95_upper' => 0.0,
                'margin_of_error' => 0.0,
            ];
        }

        $mean = array_sum($values) / $n;

        if ($n < 2) {
            return [
                'mean' => round($mean, 2),
                'variance' => 0.0,
                'std_dev' => 0.0,
                'ci_95_lower' => round($mean, 2),
                'ci_95_upper' => round($mean, 2),
                'margin_of_error' => 0.0,
            ];
        }

        $sumSquares = 0.0;
        foreach ($values as $v) {
            $sumSquares += pow((float) $v - $mean, 2);
        }
        $variance = $sumSquares / ($n - 1);
        $stdDev = sqrt($variance);

        // Approximate t-critical for 95% confidence level
        $tCrit = self::getTCritical($n - 1);
        $marginOfError = $tCrit * ($stdDev / sqrt($n));

        return [
            'mean'            => round($mean, 2),
            'variance'        => round($variance, 2),
            'std_dev'         => round($stdDev, 2),
            'ci_95_lower'     => round($mean - $marginOfError, 2),
            'ci_95_upper'     => round($mean + $marginOfError, 2),
            'margin_of_error' => round($marginOfError, 2),
        ];
    }

    /**
     * Conducts a Paired Sample T-Test between two equal-sized numerical samples.
     *
     * @param list<float|int> $sample1
     * @param list<float|int> $sample2
     * @return array{t_statistic: float, df: int, p_value: float, mean_difference: float, std_diff: float}
     */
    public static function pairedTTest(array $sample1, array $sample2): array
    {
        $n = count($sample1);
        if ($n !== count($sample2) || $n < 2) {
            throw new \InvalidArgumentException('Samples must be of equal size and contain at least 2 observations.');
        }

        $diffs = [];
        for ($i = 0; $i < $n; $i++) {
            $diffs[] = (float) $sample1[$i] - (float) $sample2[$i];
        }

        $meanDiff = array_sum($diffs) / $n;
        $sumSqDiff = 0.0;
        foreach ($diffs as $d) {
            $sumSqDiff += pow($d - $meanDiff, 2);
        }
        $varDiff = $sumSqDiff / ($n - 1);
        $stdDiff = sqrt($varDiff);

        $seDiff = $stdDiff / sqrt($n);
        $tStat = $seDiff > 0 ? ($meanDiff / $seDiff) : 0.0;
        $df = $n - 1;

        $pValue = self::approximateTwoTailedPValue(abs($tStat), $df);

        return [
            't_statistic'     => round($tStat, 4),
            'df'              => $df,
            'p_value'         => $pValue,
            'mean_difference' => round($meanDiff, 2),
            'std_diff'        => round($stdDiff, 2),
        ];
    }

    private static function getTCritical(int $df): float
    {
        if ($df <= 10) return 2.228;
        if ($df <= 20) return 2.086;
        if ($df <= 30) return 2.045;
        if ($df <= 60) return 2.000;
        if ($df <= 100) return 1.984;
        return 1.960;
    }

    /**
     * Approximates two-tailed p-value for Student's t distribution using Hill's approximation.
     */
    private static function approximateTwoTailedPValue(float $t, int $df): float
    {
        if ($t == 0.0) return 1.0;
        $x = $df / ($df + $t * $t);
        // Regularized incomplete beta function approximation
        $a = $df / 2.0;
        $b = 0.5;
        
        // Asymptotic expansion for large t
        if ($t > 6.0) {
            return pow(10, -0.43429 * ($t * $t / 2.0 - log($t) * ($df - 1) / 2.0));
        }

        // Numerical approximation for moderate t
        $z = ($t * (1.0 - 1.0 / (4.0 * $df))) / sqrt(1.0 + ($t * $t) / (2.0 * $df));
        // Normal CDF tail
        $p = 0.5 * erfc($z / sqrt(2.0)) * 2.0;
        return min(max($p, 1e-12), 1.0);
    }
}
