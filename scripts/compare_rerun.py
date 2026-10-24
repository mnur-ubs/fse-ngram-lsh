#!/usr/bin/env python3
"""Compare a benchmark re-run with the published data, query by query.

Usage:
    python3 scripts/compare_rerun.py data/rerun_YYYYMMDD [--published data]

Deterministic fields (recall, retrieved/filtered candidates, false positives) must be identical,
because the corpus, keys, parameters and the 100 queries are the same. Only latency may differ.
Exit code 0 = all deterministic fields identical and no negative latency; 1 otherwise.
Standard library only.
"""
import json
import os
import statistics as st
import sys

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DETERMINISTIC = ("is_recalled", "retrieved_count", "filtered_count", "fp_count")
PHASES = ("latency_trapdoor", "latency_cloud", "latency_filter", "latency_total")


def load(folder, name):
    path = folder if os.path.isabs(folder) else os.path.join(BASE, folder)
    with open(os.path.join(path, name), encoding="utf-8") as f:
        return json.load(f)


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    if not args:
        sys.exit(__doc__)
    rerun = args[0]
    pub = "data"
    for a in sys.argv[1:]:
        if a.startswith("--published="):
            pub = a.split("=", 1)[1]

    old = load(pub, "benchmark_100_queries.json")
    new = load(rerun, "benchmark_100_queries.json")
    ok = True

    oq = [c["typo_query"] for c in old["test_cases"]]
    nq = [c["typo_query"] for c in new["test_cases"]]
    same_q = oq == nq
    print(f"Queries identical to published run: {'YES' if same_q else 'NO'} ({len(nq)} queries)")
    ok &= same_q

    print("\nDeterministic fields (must be identical):")
    for s in ("exact", "ngram", "lsh"):
        od, nd = old["raw_data"][s]["details"], new["raw_data"][s]["details"]
        diffs = [(i + 1, f, od[i][f], nd[i][f]) for i in range(len(od)) for f in DETERMINISTIC
                 if od[i][f] != nd[i][f]]
        tp_o, tp_n = old["raw_data"][s]["tp"], new["raw_data"][s]["tp"]
        fp_o, fp_n = old["raw_data"][s]["fp"], new["raw_data"][s]["fp"]
        print(f"  {s:6} TP {tp_o}->{tp_n}  FP {fp_o}->{fp_n}  per-query differences: {len(diffs)}")
        for d in diffs[:5]:
            print(f"         query #{d[0]} {d[1]}: {d[2]} -> {d[3]}")
        ok &= not diffs and tp_o == tp_n and fp_o == fp_n

    print("\nLatency (ms), published -> re-run:")
    print(f"  {'scheme':6} {'mean':>17} {'SD':>17} {'min':>17} {'negatives':>10}")
    means = {}
    for s in ("exact", "ngram", "lsh"):
        lo = old["raw_data"][s]["latency"]
        ln = new["raw_data"][s]["latency"]
        neg = sum(1 for d in new["raw_data"][s]["details"] for p in PHASES if d[p] < 0)
        means[s] = (st.mean(lo), st.mean(ln))
        print(f"  {s:6} {st.mean(lo):8.2f} -> {st.mean(ln):6.2f} {st.stdev(lo):8.2f} -> {st.stdev(ln):6.2f} "
              f"{min(lo):8.2f} -> {min(ln):6.2f} {neg:>10}")
        ok &= neg == 0
    red_o = (1 - means["lsh"][0] / means["ngram"][0]) * 100
    red_n = (1 - means["lsh"][1] / means["ngram"][1]) * 100
    print(f"  LSH reduction vs N-Gram: {red_o:.1f}% -> {red_n:.1f}%")
    t_o = old["summary"]["paired_t_test_ngram_vs_lsh"]
    t_n = new["summary"]["paired_t_test_ngram_vs_lsh"]
    print(f"  paired t: {t_o['t_statistic']} -> {t_n['t_statistic']} (df {t_n['df']}), "
          f"p -> {t_n['p_value']:.3g}")

    try:
        so = load(pub, "sensitivity_analysis.json")
        sn = load(rerun, "sensitivity_analysis.json")
        print("\nSensitivity sweep k (recall must be identical; latency may differ):")
        for k in ("2", "3", "4", "5"):
            ro, rn = so["k_sweep"][k]["classification"]["recall"], sn["k_sweep"][k]["classification"]["recall"]
            print(f"  k={k}: recall {ro} -> {rn}   latency {so['k_sweep'][k]['latency']['mean']} -> "
                  f"{sn['k_sweep'][k]['latency']['mean']}")
            ok &= ro == rn
        print("  tau sweep recall identical:",
              all(so["tau_sweep"][t]["classification"]["recall"] == sn["tau_sweep"][t]["classification"]["recall"]
                  for t in so["tau_sweep"]))
        print("  theta sweep recall identical:",
              all(so["theta_sweep"][t]["classification"]["recall"] == sn["theta_sweep"][t]["classification"]["recall"]
                  for t in so["theta_sweep"]))
    except FileNotFoundError:
        pass

    print("\nRESULT:", "OK, only latency changed" if ok else "DIFFERENCES FOUND (see above)")
    sys.exit(0 if ok else 1)


if __name__ == "__main__":
    main()
