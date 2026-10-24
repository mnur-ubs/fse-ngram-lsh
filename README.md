# Fuzzy Keyword Searchable Encryption: Proof of Concept

Code and raw data for the manuscript *Fuzzy Searchable Encryption for Cloud Medical Records:
N-Gram vs. Locality-Sensitive Hashing* (TELKOMNIKA, paper 28158).

You can reproduce the work at three levels. Level 1 needs nothing beyond Python.

Tested on 2026-10-15 by copying only this folder to a clean location:

| Level | Linux (WSL, Python 3.10/3.12/3.14, PHP 8.5) | Windows 11 (Python 3.12.2, PHP 8.5.1) |
|---|---|---|
| 1. `verify_results.py` | OK, 0 mismatches | OK, 0 mismatches |
| 2. `plot_results.py` (fresh venv, matplotlib 3.11.2, numpy 2.5.4) | OK, 5 figures | OK, 5 figures |
| 2. `export_excel.php` | OK | OK |
| `tests/test_crypto_engine.php` | OK | OK |
| 3. full benchmark | script tested on local Docker MySQL 9.7 (see Level 3) | needs MySQL 8, not re-run |

On Windows, type `python` (or `py`) instead of `python3`. The PNG files differ byte-wise between
Windows and Linux because the default fonts differ; the plotted data are the same.

## Level 1: Verify every number in the paper from the raw data (no database)

```bash
python3 scripts/verify_results.py
```

This needs only the Python 3 standard library. It reads the per-query raw measurements in
`data/*.json` and recomputes the following, then compares each value with the number printed in
the paper:

- Tables 6, 7, 8 and 9
- the 95% confidence intervals
- the paired t-tests
- the storage projection

It exits with code 0 when every printed value is reproduced.

## Level 2: Regenerate the figures and the Excel workbook (no database)

```bash
python3 -m venv .venv
. .venv/bin/activate            # Windows: .venv\Scripts\activate
pip install -r requirements.txt
python scripts/plot_results.py  # writes figures/*.png  (use --out DIR for another folder)

composer install                # only if vendor/ is missing (it is included in this folder)
php scripts/export_excel.php    # writes Hasil_Pengujian_SE_Cloud.xlsx (needs ext-zip, ext-gd, ext-xml, ext-mbstring)
```

`plot_results.py` was tested with matplotlib 3.8.4, 3.10.9 and 3.11.2 on Python 3.10 and 3.12.

The figures embedded in the manuscript were later re-exported without the "Figure N." title
line. Layout can therefore differ cosmetically, but the plotted data are identical.

## Level 3: Re-run the full experiment (needs MySQL 8)

```bash
cp .env.example .env                     # Windows: copy .env.example .env  -> fill in DB_PASS
mysql -u root -p -e "CREATE DATABASE master_icd_x"
mysql -u root -p master_icd_x < master_icd_x.sql
php tests/test_db_connection.php         # checks the .env settings
php scripts/seed_database.php            # builds icds_encrypted, exact_index, ngram_index, lsh_index

# Re-run with the SAME queries as the published run, into a new folder (data/ is not overwritten)
php scripts/run_benchmark.php           --queries=data/benchmark_100_queries.json --out=data/rerun_YYYYMMDD
php scripts/run_diversity_benchmark.php --queries=data/diversity_benchmark.json   --out=data/rerun_YYYYMMDD
python3 scripts/compare_rerun.py data/rerun_YYYYMMDD   # recall/candidates must be identical
```

Without `--queries` the scripts generate a new query set. `TypoInjector` uses unseeded `rand()`,
so a new set differs from the published one; use `--queries` to reproduce the paper.

`.env` holds your database password. It is read by `config/database.php`; variables already set
in the shell take precedence. **Do not include `.env` when you share this folder.**

Latencies depend on the hardware, so a re-run will not give the same milliseconds. Row counts,
recall, precision and candidate counts are deterministic for the same corpus, keys and parameters.
`compare_rerun.py` checks exactly that, query by query, and also fails on any negative latency.
The benchmark itself now stops with an error if any measured duration is negative.

Script test on 2026-10-16 (local Docker MySQL 9.7, not the paper's client/VPS setup): same 100
queries, 0 differences in recall, candidates and false positives for all three schemes and all
sweeps, 0 negative latencies.

### Running against a separate database server (VPS)

`deploy/` prepares a fresh Ubuntu 22.04 server for the client/server setup of Tables 1-2:

```bash
DOCKER_CONTAINER=mysql_db bash deploy/export_dump.sh   # or plain `bash deploy/export_dump.sh` with mysqldump installed
scp deploy/master_icd_x_dump.sql.gz deploy/setup_vps_mysql.sh <user>@<server>:~/
ssh <user>@<server> sudo bash setup_vps_mysql.sh <client_public_ip> master_icd_x_dump.sql.gz
```

The server then holds only ciphertexts and HMAC trapdoors (no plaintext `icds` table), accepts
MySQL connections only from `<client_public_ip>` as user `fkse`, and writes `server_spec.txt`
for Tables 1-2. Point `.env` at it and run the benchmarks with `--queries=...` (new queries
cannot be generated without the plaintext table). Tested end to end on 2026-10-16 against an
`ubuntu:22.04` container: `compare_rerun.py` reported identical recall/candidates/FP and no
negative latency.

From the Windows client, one script runs every step (connection test, main benchmark with sweeps,
diversity benchmark, `compare_rerun.py`, and copying `server_spec.txt` back). It asks for the
`fkse` password once, passes the connection through environment variables, and does not modify
`.env`:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\run_vps_benchmark.ps1 -ServerIp <server_ip> [-Key <key.pem>] [-SshUser root]
```

Tested on 2026-10-18 from Windows PowerShell 5.1 against an `ubuntu:22.04` server (SSH + MySQL
8.0.46): all steps passed, `RESULT: OK, only latency changed`.

## False Positive Rate (fixed 2026-10-18)

`FPR = FP / (FP + TN)`. Each query has one target record, so every other record of the corpus is
a negative for that query: `FP + TN = queries × (10,469 − 1) = 100 × 10,468 = 1,046,800`.

The earlier code estimated `TN = queries × 10 − FP`, which is negative here (FP = 16,260), and the
manuscript up to V3 printed 16.26% (N-Gram) and 15.96% (LSH). With the correct denominator:
N-Gram 16,260 / 1,046,800 = **1.55%**, LSH 15,958 / 1,046,800 = **1.52%**. FP itself is
unchanged, so this needs no new measurement. The k/τ/θ sweeps previously passed TN = 0, which
made every sweep FPR 100%; they now use the same denominator.

Changed: `src/Benchmark/SensitivityBenchmark.php` (`countNegatives`),
`src/Benchmark/MetricsCalculator.php` (TN is required and must not be negative),
`scripts/export_excel.php`, and `scripts/verify_results.py` (prints both values). The published
JSON files in `data/` were produced by the old code and still contain the old `fpr` field; the
recomputed values come from their raw FP counts.

## Notes on the statistics

- **Table 8 `mean ± x`:** `x` is the 95% confidence-interval margin of error, `t_crit · s / √n`
  with `t_crit = 1.984` (df = 99). Example for LSH: `1.984 × 125.09 / √100 = 24.82`.
- **Table 9 `mean ± x`:** `x` is the sample standard deviation (`STDEV.S`).
- The workbook shows both forms side by side, built with live formulas in sheets
  `Pengujian_100_Sampel` and `Diversitas_Kueri_Tabel9`.

## Known data-quality issue

Two LSH latencies in `data/benchmark_100_queries.json` are negative: query #21 `uspecified` has
−632.27 ms, and query #22 `advefrse` has −848.41 ms. They are physically impossible.

The cause is the client-filter timing. It was measured with the wall clock (`microtime(true)`),
which jumps backwards when the operating system corrects the clock. The recorded component
`latency_filter` is −667.99 ms and −867.38 ms.

The values are kept as recorded and flagged in red in the workbook. `verify_results.py` reports
how they affect the published LSH statistics. The timers now use the monotonic clock
(`hrtime(true)`), so future runs cannot produce this artefact.
