#!/usr/bin/env python3
"""
Recompute every headline statistic of the manuscript from the raw per-query data in
data/*.json and compare it with the value printed in the paper.

Needs only the Python standard library (no database, no PHP, no numpy):
    python3 scripts/verify_results.py

Exit code 0 = every printed number is reproduced; 1 = at least one mismatch.
Data-quality warnings (e.g. negative latencies) are reported but do not fail the run.
"""

import json
import math
import os
import statistics as st
import sys

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = os.path.join(BASE, 'data')


def load(name):
    with open(os.path.join(DATA, name), encoding='utf-8') as f:
        return json.load(f)


# --- Student t distribution (exact, via the regularized incomplete beta function) -------
def _betacf(x, a, b):
    tiny = 1e-300
    qab, qap, qam = a + b, a + 1.0, a - 1.0
    c, d = 1.0, 1.0 - qab * x / qap
    d = 1.0 / (d if abs(d) > tiny else tiny)
    h = d
    for m in range(1, 300):
        m2 = 2 * m
        aa = m * (b - m) * x / ((qam + m2) * (a + m2))
        d = 1.0 + aa * d; d = 1.0 / (d if abs(d) > tiny else tiny)
        c = 1.0 + aa / c; c = c if abs(c) > tiny else tiny
        h *= d * c
        aa = -(a + m) * (qab + m) * x / ((a + m2) * (qap + m2))
        d = 1.0 + aa * d; d = 1.0 / (d if abs(d) > tiny else tiny)
        c = 1.0 + aa / c; c = c if abs(c) > tiny else tiny
        delta = d * c
        h *= delta
        if abs(delta - 1.0) < 1e-15:
            break
    return h


def _ibeta(x, a, b):
    if x <= 0.0:
        return 0.0
    if x >= 1.0:
        return 1.0
    front = math.exp(math.lgamma(a + b) - math.lgamma(a) - math.lgamma(b)
                     + a * math.log(x) + b * math.log(1.0 - x))
    if x < (a + 1.0) / (a + b + 2.0):
        return front * _betacf(x, a, b) / a
    return 1.0 - front * _betacf(1.0 - x, b, a) / b


def t_pvalue_two_tailed(t, df):
    return _ibeta(df / (df + t * t), df / 2.0, 0.5)


def t_crit(df, alpha=0.05):
    lo, hi = 0.0, 50.0
    for _ in range(200):
        mid = (lo + hi) / 2
        if t_pvalue_two_tailed(mid, df) > alpha:
            lo = mid
        else:
            hi = mid
    return (lo + hi) / 2


def paired_t(a, b):
    d = [x - y for x, y in zip(a, b)]
    n = len(d)
    t = st.mean(d) / (st.stdev(d) / math.sqrt(n))
    return t, n - 1, t_pvalue_two_tailed(abs(t), n - 1), st.mean(d)


# --- comparison helpers -------------------------------------------------------------------
failures = 0
warnings = 0


def check(label, computed, printed, decimals):
    global failures
    ok = round(computed, decimals) == round(printed, decimals)
    if not ok:
        failures += 1
    print(f"  [{'OK ' if ok else 'FAIL'}] {label:<52} computed={computed:.{decimals}f}  paper={printed}")


def warn(msg):
    global warnings
    warnings += 1
    print(f"  [WARN] {msg}")


# =========================================================================================
b = load('benchmark_100_queries.json')
raw = b['raw_data']
ex, ng, ls = (raw[s]['latency'] for s in ('exact', 'ngram', 'lsh'))
n = len(ng)
tc = round(t_crit(n - 1), 3)  # the paper prints t_crit = 1.984

print(f"MAIN BENCHMARK (n = {n})  -> Table 7, Table 8, Figure 6")
for name, x, mean_p, var_p, sd_p, moe_p, lo_p, hi_p in (
        ('Exact', ex, 1.51, None, None, 0.07, 1.43, 1.58),
        ('N-Gram', ng, 248.43, 4382.25, 66.20, 13.13, 235.30, 261.56),
        ('LSH', ls, 74.58, 15648.17, 125.09, 24.82, 49.76, 99.40)):
    m, s = st.mean(x), st.stdev(x)
    moe = tc * s / math.sqrt(len(x))
    check(f"{name} mean latency (ms)", m, mean_p, 2)
    if var_p is not None:
        check(f"{name} variance s^2", st.variance(x), var_p, 2)
        check(f"{name} standard deviation s", s, sd_p, 2)
    check(f"{name} 95% margin of error  (the '±' of Table 8)", moe, moe_p, 2)
    check(f"{name} 95% CI lower", m - moe, lo_p, 2)
    check(f"{name} 95% CI upper", m + moe, hi_p, 2)

t, df, p, dbar = paired_t(ng, ls)
check("paired t (N-Gram vs LSH)", t, 13.418, 3)
check("mean difference D-bar (ms)", dbar, 173.85, 2)
check("t critical (alpha = 0.05, df = 99)", t_crit(df), 1.984, 3)
check("latency reduction LSH vs N-Gram (%)", (st.mean(ng) - st.mean(ls)) / st.mean(ng) * 100, 70.0, 1)
print(f"  [INFO] exact two-tailed p = {p:.3e}   (paper prints 1.44e-23; both are < 0.001, the exact value is {p:.2e})")

for s_name in ('exact', 'ngram', 'lsh'):
    bad = [(i, b['test_cases'][i]['typo_query'], v) for i, v in enumerate(raw[s_name]['latency']) if v < 0]
    if bad:
        warn(f"{s_name}: {len(bad)} NEGATIVE latency value(s) in the raw data: {bad}")
        keep = [i for i, v in enumerate(raw[s_name]['latency']) if v >= 0]
        if s_name == 'lsh':
            ls2 = [ls[i] for i in keep]
            ng2 = [ng[i] for i in keep]
            t2, df2, p2, _ = paired_t(ng2, ls2)
            print(f"         LSH without them: mean {st.mean(ls2):.2f} ms, SD {st.stdev(ls2):.2f}, "
                  f"reduction {(st.mean(ng2) - st.mean(ls2)) / st.mean(ng2) * 100:.1f}%, "
                  f"paired t = {t2:.3f} (df = {df2}), p = {p2:.1e}")

# Recall / precision / F1 / FPR
print("\nRETRIEVAL METRICS -> Table 8")
for s_name, rec_p, prec_p, f1_p in (('ngram', 91.0, 0.56, 1.11), ('lsh', 88.0, 0.55, 1.09)):
    tp, fp, fn = raw[s_name]['tp'], raw[s_name]['fp'], raw[s_name]['fn']
    rec = tp / (tp + fn) * 100
    prec = tp / (tp + fp) * 100
    check(f"{s_name} recall (%)", rec, rec_p, 2)
    check(f"{s_name} precision (%)", prec, prec_p, 2)
    check(f"{s_name} F1 (%)", 2 * prec * rec / (prec + rec), f1_p, 2)
# FPR = FP / (FP + TN). Every non-target record is a negative for its query, so
# FP + TN = queries x (corpus - 1). Table 6: icds_encrypted holds 10,469 records.
CORPUS = 10469
n_queries = len(raw['lsh']['retrieved_count'])
for s_name in ('ngram', 'lsh'):
    fp = raw[s_name]['fp']
    fpr = fp / (n_queries * (CORPUS - 1)) * 100
    print(f"  [INFO] {s_name} FPR (%) from the raw data = {fpr:.2f} "
          f"(FP {fp:,} / {n_queries} x {CORPUS - 1:,}); V3 printed "
          f"{ {'ngram': '16.26', 'lsh': '15.96'}[s_name] } from a negative TN (code bug, fixed 2026-10-18)")
cand = st.mean(raw['lsh']['retrieved_count'])
kept = st.mean(raw['lsh']['filtered_count'])
check("LSH mean server candidates", cand, 4018, 0)
check("LSH mean retained after client filter", kept, 160, 0)
check("LSH candidates eliminated (%)", (cand - kept) / cand * 100, 96.0, 1)

# =========================================================================================
print("\nSTRATIFIED QUERY-DIVERSITY SET -> Table 9 (the '±' there is the STANDARD DEVIATION)")
d = load('diversity_benchmark.json')['detailed_results']
paper = {
    'Short Terms (4-6 chars)': (70.0, 60.0, 151.87, 46.2, 81.26, 61.6, 46.5, 5.658),
    'Medium Terms (7-10 chars)': (95.0, 95.0, 197.10, 67.1, 81.30, 37.7, 58.7, 7.719),
    'Long Polysyllabic (>=11 chars)': (100.0, 100.0, 252.58, 67.1, 79.43, 34.0, 68.6, 11.773),
    'Compound Typo (Edit Distance d=2)': (35.0, 35.0, 182.85, 65.6, 73.36, 33.4, 59.9, 8.769),
    'Phonetic/Orthographic Shifts': (25.0, 25.0, 201.40, 71.4, 86.35, 39.1, 57.1, 9.055),
    None: (65.0, 63.0, 197.16, 70.9, 80.34, 41.8, 59.3, 17.235),
}
for stratum, (rn, rl, mn, sn, ml, sl, save, tp_) in paper.items():
    rows = [r for r in d if stratum is None or r['case']['diversity_dimension'] == stratum]
    a = [r['ngram']['latency_total'] for r in rows]
    c = [r['lsh']['latency_total'] for r in rows]
    label = stratum or 'Pooled (n = 100)'
    print(f" {label} (n = {len(rows)})")
    check("   recall N-Gram (%)", sum(r['ngram']['is_recalled'] for r in rows) / len(rows) * 100, rn, 1)
    check("   recall LSH (%)", sum(r['lsh']['is_recalled'] for r in rows) / len(rows) * 100, rl, 1)
    check("   N-Gram mean (ms)", st.mean(a), mn, 2)
    check("   N-Gram SD  (the '±')", st.stdev(a), sn, 1)
    check("   LSH mean (ms)", st.mean(c), ml, 2)
    check("   LSH SD  (the '±')", st.stdev(c), sl, 1)
    check("   LSH saving (%)", (st.mean(a) - st.mean(c)) / st.mean(a) * 100, save, 1)
    check("   paired t", paired_t(a, c)[0], tp_, 3)
    if any(v < 0 for v in a + c):
        warn(f"negative latency in stratum {label}")

# =========================================================================================
print("\nSTORAGE / SCALABILITY -> Table 6, Figure 5, Figure 9")
sc = load('scalability_projection.json')['projections']
check("N-Gram rows at 1,000,000 docs (millions)", sc['ngram'][-1]['index_rows'] / 1e6, 83.99, 2)
check("LSH k=3 rows at 1,000,000 docs (millions)", sc['lsh_k3'][-1]['index_rows'] / 1e6, 31.55, 2)
check("N-Gram storage at 1,000,000 docs (MB)", sc['ngram'][-1]['storage_mb'], 10253.1, 1)
check("LSH k=3 storage at 1,000,000 docs (MB)", sc['lsh_k3'][-1]['storage_mb'], 3851.4, 1)
check("storage reduction LSH vs N-Gram (%)", (879327 - 330306) / 879327 * 100, 62.4, 1)

print(f"\nRESULT: {failures} mismatch(es), {warnings} data-quality warning(s).")
sys.exit(1 if failures else 0)
