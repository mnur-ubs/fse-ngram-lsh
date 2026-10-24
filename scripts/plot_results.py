#!/usr/bin/env python3
"""
Publication-Quality Figures Generator for TELKOMNIKA Revision
Paper: Fuzzy Searchable Encryption for Cloud Medical Records: N-Gram vs. Locality-Sensitive Hashing
Generates Figures 4, 5, 6, 7, and 8 at 300 DPI with explicitly labeled X and Y axes.
"""

import argparse
import inspect
import json
import os

import matplotlib
matplotlib.use('Agg')  # headless backend: works without a display (servers, WSL, CI)
import matplotlib.pyplot as plt
import numpy as np

parser = argparse.ArgumentParser(description='Regenerate the manuscript figures from data/*.json')
parser.add_argument('--out', default=None,
                    help='Output directory (default: <repo>/figures). Pass ../04_LaTeX/figures explicitly if wanted.')
args = parser.parse_args()

# Fixed seed: the paired-trajectory panel samples 40 of the 100 queries; without a seed
# every run produced a different figure.
rng = np.random.default_rng(42)

# Set publication style
plt.style.use('seaborn-v0_8-whitegrid' if 'seaborn-v0_8-whitegrid' in plt.style.available else 'default')
plt.rcParams.update({
    'font.size': 11,
    'font.family': 'sans-serif',
    'axes.labelsize': 12,
    'axes.labelweight': 'bold',
    'axes.titlesize': 13,
    'axes.titleweight': 'bold',
    'xtick.labelsize': 10,
    'ytick.labelsize': 10,
    'figure.titlesize': 14,
    'figure.titleweight': 'bold',
    'legend.fontsize': 10,
})

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_DIR = os.path.join(BASE_DIR, 'data')
# Only write inside the repository unless the user asks otherwise (the previous version
# silently created/overwrote ../latex/figures outside the repository).
OUT_DIRS = [os.path.abspath(args.out) if args.out else os.path.join(BASE_DIR, 'figures')]

for d in OUT_DIRS:
    os.makedirs(d, exist_ok=True)


def load_json(name):
    with open(os.path.join(DATA_DIR, name), encoding='utf-8') as f:
        return json.load(f)


# Load Benchmark Datasets
b100 = load_json('benchmark_100_queries.json')
sens = load_json('sensitivity_analysis.json')
scale = load_json('scalability_projection.json')

# Matplotlib renamed boxplot(labels=) to tick_labels= in 3.9 and removed `labels` in 3.11.
BOXPLOT_LABEL_KW = 'tick_labels' if 'tick_labels' in inspect.signature(plt.Axes.boxplot).parameters else 'labels'

def save_fig(fig, filename):
    for d in OUT_DIRS:
        path = os.path.join(d, filename)
        fig.savefig(path, dpi=300, bbox_inches='tight')
    print(f"[SAVED] {filename} (300 DPI)")

# ==============================================================================
# FIGURE 4: Index Storage Overhead Comparison
# ==============================================================================
fig, ax1 = plt.subplots(figsize=(8, 5), dpi=300)

schemes = ['Exact Match\n(Baseline)', 'LSH MinHash\n(k = 3)', 'LSH MinHash\n(k = 5)', 'N-Gram\n(Bi-gram)']
row_counts = [110102, 330306, 550510, 879327]
storage_mb = [round(c * 128 / (1024 * 1024), 2) for c in row_counts]

colors = ['#4A90E2', '#50E3C2', '#F5A623', '#D0021B']
bars = ax1.bar(schemes, [r / 1000 for r in row_counts], color=colors, width=0.55, edgecolor='black', linewidth=1)

for bar, r_count, mb in zip(bars, row_counts, storage_mb):
    yval = bar.get_height()
    ax1.text(bar.get_x() + bar.get_width() / 2.0, yval + 18, 
             f"{r_count:,} rows\n({mb:.1f} MB)", 
             ha='center', va='bottom', fontsize=9.5, fontweight='bold')

ax1.set_xlabel('Searchable Encryption Indexing Scheme', labelpad=10)
ax1.set_ylabel('Inverted Index Size (Thousands of Rows)', labelpad=10)
ax1.set_title('Figure 4. Inverted Index Storage Overhead Across Evaluated Schemes', pad=15)
ax1.set_ylim(0, 1100)
ax1.grid(axis='y', linestyle='--', alpha=0.6)

save_fig(fig, 'Fig4_Storage_Overhead.png')
plt.close(fig)

# ==============================================================================
# FIGURE 5: Query Latency Distribution with 95% Confidence Intervals & Paired Lines
# ==============================================================================
fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(13, 5.5), gridspec_kw={'width_ratios': [1.3, 1]}, dpi=300)

raw_exact = b100['raw_data']['exact']['latency']
raw_ngram = b100['raw_data']['ngram']['latency']
raw_lsh   = b100['raw_data']['lsh']['latency']

# Subplot 1: Boxplot with Mean marker and CI
box_data = [raw_exact, raw_lsh, raw_ngram]
labels = ['Exact Match\n(Baseline)', 'LSH MinHash\n(k = 3)', 'N-Gram\n(Bi-gram)']
bp = ax1.boxplot(box_data, **{BOXPLOT_LABEL_KW: labels}, patch_artist=True, widths=0.45,
                 showmeans=True, meanprops={"marker":"s", "markerfacecolor":"red", "markeredgecolor":"black", "markersize":7})

box_colors = ['#D1E8E2', '#A9DFBF', '#FADBD8']
for patch, color in zip(bp['boxes'], box_colors):
    patch.set_facecolor(color)
    patch.set_alpha(0.85)

# Annotate CI
lsh_summary = b100['summary']['lsh']['latency']
ngram_summary = b100['summary']['ngram']['latency']
ax1.annotate(f"Mean: {lsh_summary['mean']} ms\n95% CI: [{lsh_summary['ci_95_lower']}, {lsh_summary['ci_95_upper']}]",
             xy=(2, lsh_summary['mean']), xytext=(1.6, 280),
             arrowprops=dict(arrowstyle="->", color="black", lw=1.2),
             bbox=dict(boxstyle="round,pad=0.3", fc="#EAFAF1", ec="#27AE60", lw=1), fontsize=8.5)

ax1.annotate(f"Mean: {ngram_summary['mean']} ms\n95% CI: [{ngram_summary['ci_95_lower']}, {ngram_summary['ci_95_upper']}]",
             xy=(3, ngram_summary['mean']), xytext=(2.3, 380),
             arrowprops=dict(arrowstyle="->", color="black", lw=1.2),
             bbox=dict(boxstyle="round,pad=0.3", fc="#FDEDEC", ec="#E74C3C", lw=1), fontsize=8.5)

ax1.set_xlabel('Indexing Scheme', labelpad=10)
ax1.set_ylabel('Total Query Latency (Milliseconds, ms)', labelpad=10)
ax1.set_title('(a) Latency Distribution (n = 100 Queries)', pad=12)
ax1.set_ylim(-10, 480)

# Subplot 2: Paired comparison
indices = rng.choice(len(raw_lsh), 40, replace=False)  # 40 sample query trajectories for visual clarity
for i in indices:
    ax2.plot([1, 2], [raw_ngram[i], raw_lsh[i]], color='#95A5A6', alpha=0.4, linewidth=1)

ax2.plot([1, 2], [ngram_summary['mean'], lsh_summary['mean']], color='#C0392B', marker='D', markersize=8, linewidth=2.8,
         label=f"Mean: {ngram_summary['mean']} → {lsh_summary['mean']} ms (-70.0%)")

ax2.set_xticks([1, 2])
ax2.set_xticklabels(['N-Gram', 'LSH (k=3)'])
ax2.set_xlabel('Paired Query Comparison', labelpad=10)
ax2.set_ylabel('Query Latency (ms)', labelpad=10)
ax2.set_title('(b) Paired Query Latency Transition', pad=12)
ax2.legend(loc='upper right', frameon=True)

# Add t-test box
ttest = b100['summary']['paired_t_test_ngram_vs_lsh']
t_str = f"Paired t-Test (df = {ttest['df']})\nt = {ttest['t_statistic']:.3f}\np < 0.001 (Significant)"
ax2.text(1.5, 30, t_str, ha='center', fontsize=9, fontweight='bold',
         bbox=dict(boxstyle="round,pad=0.4", fc="#FEF9E7", ec="#F39C12", lw=1))

fig.suptitle('Figure 5. Query Latency Evaluation Across 100 Typo-Injected Queries', y=1.02)
plt.tight_layout()
save_fig(fig, 'Fig5_Query_Latency_CI.png')
plt.close(fig)

# ==============================================================================
# FIGURE 6: Pre-Filter vs Post-Filter Candidate Document Volume
# ==============================================================================
fig, ax = plt.subplots(figsize=(8, 5), dpi=300)

categories = ['Exact Match', 'LSH MinHash (k=3)', 'N-Gram (Bi-gram)']
pre_filter  = [0.0, b100['summary']['lsh']['retrieved']['mean'], b100['summary']['ngram']['retrieved']['mean']]
post_filter = [0.0, b100['summary']['lsh']['filtered']['mean'], b100['summary']['ngram']['filtered']['mean']]

x = np.arange(len(categories))
width = 0.35

rects1 = ax.bar(x - width/2, pre_filter, width, label='Cloud Server Candidates (Pre-Filter)', color='#5DADE2', edgecolor='black')
rects2 = ax.bar(x + width/2, post_filter, width, label='Client Retained Documents (Post-Filter, τ=85%)', color='#48C9B0', edgecolor='black')

for rect in rects1:
    h = rect.get_height()
    if h > 0:
        ax.annotate(f'{int(h):,} docs', xy=(rect.get_x() + rect.get_width() / 2, h), xytext=(0, 4),
                    textcoords="offset points", ha='center', va='bottom', fontsize=9, fontweight='bold')

for rect in rects2:
    h = rect.get_height()
    if h > 0:
        ax.annotate(f'{int(h):,} docs', xy=(rect.get_x() + rect.get_width() / 2, h), xytext=(0, 4),
                    textcoords="offset points", ha='center', va='bottom', fontsize=9, fontweight='bold')

ax.set_xlabel('Searchable Encryption Indexing Scheme', labelpad=10)
ax.set_ylabel('Mean Candidate Documents per Query (Count)', labelpad=10)
ax.set_title('Figure 6. Client-Side False-Positive Filtering Effectiveness', pad=15)
ax.set_xticks(x)
ax.set_xticklabels(categories)
ax.set_ylim(0, 6000)
ax.legend(loc='upper left', frameon=True)

save_fig(fig, 'Fig6_Pre_Post_Filtering.png')
plt.close(fig)

# ==============================================================================
# FIGURE 7: Sensitivity Analysis of MinHash parameter k and Client Threshold tau
# ==============================================================================
fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(13, 5), dpi=300)

# (a) Sensitivity on k (2, 3, 4, 5)
k_vals = [2, 3, 4, 5]
k_recalls = [sens['k_sweep'][str(k)]['classification']['recall'] for k in k_vals]
k_latencies = [sens['k_sweep'][str(k)]['latency']['mean'] for k in k_vals]

ax1_twin = ax1.twinx()
p1, = ax1.plot(k_vals, k_recalls, color='#2E86C1', marker='o', linewidth=2.5, markersize=8, label='Recall (%)')
p2, = ax1_twin.plot(k_vals, k_latencies, color='#E67E22', marker='s', linewidth=2.5, markersize=8, linestyle='--', label='Latency (ms)')

ax1.set_xlabel('MinHash Hash Permutations / Bands (k)', labelpad=10)
ax1.set_ylabel('Search Recall (%)', color='#2E86C1', labelpad=10)
ax1_twin.set_ylabel('Mean Query Latency (ms)', color='#E67E22', labelpad=10)
ax1.set_xticks(k_vals)
ax1.set_ylim(60, 100)
ax1_twin.set_ylim(30, 160)
ax1.set_title('(a) Parameter Sensitivity: Effect of k on Recall & Latency', pad=12)

# Combined legend
lines = [p1, p2]
ax1.legend(lines, [l.get_label() for l in lines], loc='center right', frameon=True)

# (b) Sensitivity on tau (70% - 95%)
tau_vals = [70, 75, 80, 85, 90, 95]
tau_recalls = [sens['tau_sweep'][str(t)]['classification']['recall'] for t in tau_vals]
tau_precs = [sens['tau_sweep'][str(t)]['classification']['precision'] for t in tau_vals]

ax2.plot(tau_vals, tau_recalls, color='#27AE60', marker='D', linewidth=2.5, markersize=7, label='Recall (%)')
ax2.plot(tau_vals, [p * 100 for p in tau_precs], color='#8E44AD', marker='^', linewidth=2.5, markersize=7, linestyle='--', label=r'Precision ($\times10^{-2}$ %)')

# Mark recommended threshold tau = 85%
ax2.axvline(x=85, color='#C0392B', linestyle=':', linewidth=2, label='Recommended τ = 85%')
ax2.annotate('Trade-off inflection\npoint (τ = 85%)', xy=(85, 88), xytext=(72, 45),
             arrowprops=dict(arrowstyle="->", color="#C0392B", lw=1.2),
             bbox=dict(boxstyle="round,pad=0.3", fc="#FDEDEC", ec="#C0392B", lw=1), fontsize=8.5)

ax2.set_xlabel('Client Similarity Acceptance Threshold τ (%)', labelpad=10)
ax2.set_ylabel('Evaluation Score (%)', labelpad=10)
ax2.set_xticks(tau_vals)
ax2.set_ylim(0, 105)
ax2.set_title('(b) Parameter Sensitivity: Effect of τ on Filtering Accuracy', pad=12)
ax2.legend(loc='lower left', frameon=True)

fig.suptitle('Figure 7. Sensitivity Analysis of Algorithmic Parameters k and τ', y=1.02)
plt.tight_layout()
save_fig(fig, 'Fig7_Sensitivity_k_tau.png')
plt.close(fig)

# ==============================================================================
# FIGURE 8: Scalability Projection across Corpus Sizes (10k to 1M EMR Records)
# ==============================================================================
fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(13, 5), dpi=300)

corpus_sizes = [c / 1000 for c in scale['corpus_sizes']] # in thousands
exact_rows = [p['index_rows'] / 1e6 for p in scale['projections']['exact']]
ngram_rows = [p['index_rows'] / 1e6 for p in scale['projections']['ngram']]
lsh3_rows  = [p['index_rows'] / 1e6 for p in scale['projections']['lsh_k3']]
lsh5_rows  = [p['index_rows'] / 1e6 for p in scale['projections']['lsh_k5']]

exact_mb = [p['storage_mb'] for p in scale['projections']['exact']]
ngram_mb = [p['storage_mb'] for p in scale['projections']['ngram']]
lsh3_mb  = [p['storage_mb'] for p in scale['projections']['lsh_k3']]
lsh5_mb  = [p['storage_mb'] for p in scale['projections']['lsh_k5']]

# Subplot 1: Millions of Index Rows
ax1.plot(corpus_sizes, ngram_rows, color='#D0021B', marker='o', linewidth=2.5, label='N-Gram (Bi-gram, 84.0 rows/doc)')
ax1.plot(corpus_sizes, lsh5_rows,  color='#F5A623', marker='s', linewidth=2, linestyle='--', label='LSH (k=5, 52.6 rows/doc)')
ax1.plot(corpus_sizes, lsh3_rows,  color='#50E3C2', marker='^', linewidth=2.5, label='LSH (k=3, 31.6 rows/doc)')
ax1.plot(corpus_sizes, exact_rows, color='#4A90E2', marker='x', linewidth=2, linestyle=':', label='Exact Match (10.5 rows/doc)')

ax1.set_xlabel('Corpus Size (Thousands of Medical Records)', labelpad=10)
ax1.set_ylabel('Projected Inverted Index Rows (Millions)', labelpad=10)
ax1.set_title('(a) Index Row Scaling (10k to 1,000k Records)', pad=12)
ax1.legend(loc='upper left', frameon=True)

# Subplot 2: Storage Size in MB
ax2.plot(corpus_sizes, ngram_mb, color='#D0021B', marker='o', linewidth=2.5, label='N-Gram (~10,253 MB / 1M docs)')
ax2.plot(corpus_sizes, lsh5_mb,  color='#F5A623', marker='s', linewidth=2, linestyle='--', label='LSH k=5 (~6,419 MB / 1M docs)')
ax2.plot(corpus_sizes, lsh3_mb,  color='#50E3C2', marker='^', linewidth=2.5, label='LSH k=3 (~3,851 MB / 1M docs)')
ax2.plot(corpus_sizes, exact_mb, color='#4A90E2', marker='x', linewidth=2, linestyle=':', label='Exact Match (~1,284 MB / 1M docs)')

ax2.set_xlabel('Corpus Size (Thousands of Medical Records)', labelpad=10)
ax2.set_ylabel('Projected Storage Overhead (Megabytes, MB)', labelpad=10)
ax2.set_title('(b) Physical Database Storage Growth', pad=12)
ax2.legend(loc='upper left', frameon=True)

fig.suptitle('Figure 8. Scalability Projections for Large-Scale Hospital EMR Corpora', y=1.02)
plt.tight_layout()
save_fig(fig, 'Fig8_Scalability_Projection.png')
plt.close(fig)

print(">>> ALL 5 PUBLICATION FIGURES SUCCESSFULLY GENERATED! <<<")
