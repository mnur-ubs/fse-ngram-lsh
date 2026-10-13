<?php
require_once 'backend.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard SE Komparasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .card { border-radius: 8px; border: none; }
        .exact-card { border-top: 5px solid #dc3545; }
        .ngram-card { border-top: 5px solid #198754; }
        .lsh-card   { border-top: 5px solid #0d6efd; }
        .cipher-text { font-family: monospace; font-size: 0.75rem; color: #aaa; display: block; margin-top: 4px;}
        .eng-text { font-style: italic; color: #6c757d; font-size: 0.9rem;}
        .fp-item { background-color: #f8d7da !important; opacity: 0.85; }
        .fp-text { text-decoration: line-through; color: #6c757d; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold">Metrik Searchable Encryption (SE)</h2>
        <p class="text-muted">Komparasi Baseline (Exact Match) vs N-Gram vs LSH</p>
    </div>

    <?php if(!empty($indexing_message)): ?>
        <div class="alert alert-success text-center fw-bold"><?= $indexing_message ?></div>
    <?php endif; ?>

    <div class="row mb-4 text-center">
        <div class="col-md-4">
            <div class="alert alert-danger shadow-sm">
                <h6>Beban Memori Exact Match</h6>
                <h4><?= number_format($count_exact) ?> Baris</h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert alert-success shadow-sm">
                <h6>Beban Memori N-Gram</h6>
                <h4><?= number_format($count_ngram) ?> Baris</h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert alert-primary shadow-sm">
                <h6>Beban Memori LSH</h6>
                <h4><?= number_format($count_lsh) ?> Baris</h4>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <form method="POST" action="">
                <div class="input-group input-group-lg">
                    <input type="text" name="query" class="form-control" placeholder="Cari diagnosis (Coba buat typo: 'Tuberkolosis' atau 'Choleraa')" value="<?= htmlspecialchars($search_query) ?>" required autocomplete="off">
                    <button class="btn btn-dark" type="submit">Cari</button>
                </div>
            </form>
        </div>
    </div>

    <?php if(!empty($search_query)): ?>
        <h5 class="mb-3 text-center">Evaluasi Kueri: <b class="text-primary">"<?= htmlspecialchars($search_query) ?>"</b></h5>
        <div class="row g-4">
        
            <div class="col-md-4">
                <div class="card h-100 shadow-sm exact-card">
                    <div class="card-header bg-white fw-bold text-danger">1. EXACT MATCH (Baseline)</div>
                    <div class="card-body">
                        <div class="alert alert-light border p-2 mb-3">
                            <small>Latency: <b><?= number_format($stats['exact']['time'], 2) ?> ms</b></small><br>
                            <small>Dibuang (False Pos): <b><?= $stats['exact']['dropped'] ?></b></small><br>
                        </div>
                        <?php if(empty($results['exact'])): ?>
                            <p class="text-danger small">Baseline GAGAL. Toleransi Typo 0%.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach($results['exact'] as $res): ?>
                                    <li class="list-group-item px-0">
                                        <strong><?= htmlspecialchars($res['doc_id']) ?></strong><br>
                                        ID: <?= htmlspecialchars($res['name_id']) ?><br>
                                        <span class="eng-text">EN: <?= htmlspecialchars($res['name_en']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 shadow-sm ngram-card">
                    <div class="card-header bg-white fw-bold text-success">2. N-GRAM FUZZY SE</div>
                    <div class="card-body">
                        <div class="alert alert-light border p-2 mb-3">
                            <small>Latency: <b class="text-danger"><?= number_format($stats['ngram']['time'], 2) ?> ms</b></small><br>
                            <small>Dibuang (False Pos): <b><?= $stats['ngram']['dropped'] ?></b></small><br>
                        </div>
                    
                        <?php if(empty($results['ngram'])): ?>
                            <p class="text-muted small">Tidak ada data relevan.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush mb-3">
                                <?php foreach(array_slice($results['ngram'], 0, 10) as $res): ?>
                                    <li class="list-group-item px-0">
                                        <strong><?= htmlspecialchars($res['doc_id']) ?></strong><br>
                                        ID: <?= htmlspecialchars($res['name_id']) ?><br>
                                        <span class="eng-text">EN: <?= htmlspecialchars($res['name_en']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    
                        <?php if($stats['ngram']['dropped'] > 0 && !empty($stats['ngram']['fp_list'])): ?>
                            <button class="btn btn-sm btn-outline-danger w-100 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#fpNgram">
                                Lihat Sampel Dibuang
                            </button>
                            <div class="collapse" id="fpNgram">
                                <ul class="list-group list-group-flush border border-danger rounded">
                                    <?php foreach($stats['ngram']['fp_list'] as $fp): ?>
                                        <li class="list-group-item px-2 fp-item">
                                            <strong class="fp-text"><?= htmlspecialchars($fp['doc_id']) ?></strong><br>
                                            <span class="fp-text"><?= htmlspecialchars($fp['name_id']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        
            <div class="col-md-4">
                <div class="card h-100 shadow-sm lsh-card">
                    <div class="card-header bg-white fw-bold text-primary">3. LSH (MinHash) SE</div>
                    <div class="card-body">
                        <div class="alert alert-light border p-2 mb-3">
                            <small>Latency: <b class="text-primary"><?= number_format($stats['lsh']['time'], 2) ?> ms</b></small><br>
                            <small>Dibuang (False Pos): <b class="text-danger"><?= $stats['lsh']['dropped'] ?></b></small><br>
                        </div>
                        
                    
                        <?php if(empty($results['lsh'])): ?>
                            <p class="text-muted small">Tidak ada data relevan.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush mb-3">
                                <?php foreach(array_slice($results['lsh'], 0, 10) as $res): ?>
                                    <li class="list-group-item px-0">
                                        <strong><?= htmlspecialchars($res['doc_id']) ?></strong><br>
                                        ID: <?= htmlspecialchars($res['name_id']) ?><br>
                                        <span class="eng-text">EN: <?= htmlspecialchars($res['name_en']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                    
                        <?php if($stats['lsh']['dropped'] > 0 && !empty($stats['lsh']['fp_list'])): ?>
                            <button class="btn btn-sm btn-outline-danger w-100 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#fpLsh">
                                Lihat Sampel Dibuang
                            </button>
                            <div class="collapse" id="fpLsh">
                                <ul class="list-group list-group-flush border border-danger rounded">
                                    <?php foreach($stats['lsh']['fp_list'] as $fp): ?>
                                        <li class="list-group-item px-2 fp-item">
                                            <strong class="fp-text"><?= htmlspecialchars($fp['doc_id']) ?></strong><br>
                                            <span class="fp-text"><?= htmlspecialchars($fp['name_id']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>