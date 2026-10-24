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
     * @param int $tn True Negatives (non-target records not returned after filtering)
     * @return array{precision: float, recall: float, f1: float, fpr: float}
     */
    public static function computeClassificationMetrics(int $tp, int $fp, int $fn, int $tn): array
    {
        if (min($tp, $fp, $fn, $tn) < 0) {
            throw new \InvalidArgumentException("Negative confusion-matrix count: TP={$tp} FP={$fp} FN={$fn} TN={$tn}");
        }

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

    /**
     * Two-tailed 95% critical value of Student's t (exact quantile, obtained by bisection
     * on the exact CDF). Replaces the former step table, which returned 2.086 for df = 19
     * (true value 2.093) and 1.984 for every df in 61..100.
     */
    private static function getTCritical(int $df): float
    {
        $lo = 0.0;
        $hi = 20.0;
        for ($i = 0; $i < 100; $i++) {
            $mid = ($lo + $hi) / 2.0;
            if (self::twoTailedPValue($mid, $df) > 0.05) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        return round(($lo + $hi) / 2.0, 4);
    }

    /**
     * Exact two-tailed p-value of Student's t distribution:
     * p = I_{df/(df+t^2)}(df/2, 1/2), the regularized incomplete beta function.
     * Replaces the former asymptotic approximation, which reported p = 1.44e-23 for
     * t = 13.418, df = 99 (exact value 5.23e-24, identical to scipy.stats / Excel T.TEST).
     */
    private static function twoTailedPValue(float $t, int $df): float
    {
        if ($t == 0.0) {
            return 1.0;
        }
        $x = $df / ($df + $t * $t);
        return self::regularizedIncompleteBeta($x, $df / 2.0, 0.5);
    }

    private static function approximateTwoTailedPValue(float $t, int $df): float
    {
        return self::twoTailedPValue($t, $df);
    }

    /** Regularized incomplete beta I_x(a, b) (Numerical Recipes, continued fraction). */
    private static function regularizedIncompleteBeta(float $x, float $a, float $b): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }
        if ($x >= 1.0) {
            return 1.0;
        }
        $lnFront = self::lnGamma($a + $b) - self::lnGamma($a) - self::lnGamma($b)
            + $a * log($x) + $b * log(1.0 - $x);
        if ($x < ($a + 1.0) / ($a + $b + 2.0)) {
            return exp($lnFront) * self::betaContinuedFraction($x, $a, $b) / $a;
        }
        return 1.0 - exp($lnFront) * self::betaContinuedFraction(1.0 - $x, $b, $a) / $b;
    }

    private static function betaContinuedFraction(float $x, float $a, float $b): float
    {
        $tiny = 1e-300;
        $qab = $a + $b;
        $qap = $a + 1.0;
        $qam = $a - 1.0;
        $c = 1.0;
        $d = 1.0 - $qab * $x / $qap;
        $d = abs($d) < $tiny ? $tiny : $d;
        $d = 1.0 / $d;
        $h = $d;
        for ($m = 1; $m <= 300; $m++) {
            $m2 = 2 * $m;
            $aa = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1.0 + $aa * $d;
            $d = abs($d) < $tiny ? $tiny : $d;
            $c = 1.0 + $aa / $c;
            $c = abs($c) < $tiny ? $tiny : $c;
            $d = 1.0 / $d;
            $h *= $d * $c;
            $aa = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1.0 + $aa * $d;
            $d = abs($d) < $tiny ? $tiny : $d;
            $c = 1.0 + $aa / $c;
            $c = abs($c) < $tiny ? $tiny : $c;
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;
            if (abs($del - 1.0) < 1e-15) {
                break;
            }
        }
        return $h;
    }

    /** Lanczos approximation of ln(Gamma(z)), z > 0. */
    private static function lnGamma(float $z): float
    {
        $g = [76.18009172947146, -86.50532032941677, 24.01409824083091,
              -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];
        $y = $z;
        $tmp = $z + 5.5;
        $tmp -= ($z + 0.5) * log($tmp);
        $ser = 1.000000000190015;
        foreach ($g as $coef) {
            $ser += $coef / ++$y;
        }
        return -$tmp + log(2.5066282746310005 * $ser / $z);
    }
}
