<?php
require_once __DIR__ . '/../backend/includes/auth.php';
requireLogin();


require_once __DIR__ . '/../backend/admin/commentaires.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Commentaires'; require_once __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="css/statistique.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="css/commentaires.css?v=<?= APP_VERSION ?>">
</head>
<body>
    
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="page">
    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

    <!-- ── Filtres ── -->
    <div class="chart-card" style="margin-bottom:1.5rem;">
        <div class="chart-header">
            <div>
                <div class="chart-title">Commentaires utilisateurs</div>
                <div class="chart-subtitle"><?= number_format($total, 0, ',', ' ') ?> commentaire(s) trouvé(s)</div>
            </div>
        </div>

        <form method="GET" class="search-form">
            <div class="filters-grid">
                <label>
                    Site
                    <input type="text" name="site" value="<?= htmlspecialchars($fSite) ?>"
                           placeholder="Code ou nom...">
                </label>
                <label>
                    Région
                    <input type="text" name="region" value="<?= htmlspecialchars($fRegion) ?>"
                           placeholder="Nom de région...">
                </label>
                <label>
                    Contenu
                    <input type="text" name="contenu" value="<?= htmlspecialchars($f_contenu) ?>"
                           placeholder="Mot clé...">
                </label>
                <label>
                    Du
                    <input type="date" name="date_debut" value="<?= htmlspecialchars($fDateDebut) ?>">
                </label>
                <label>
                    Au
                    <input type="date" name="date_fin" value="<?= htmlspecialchars($fDateFin) ?>">
                </label>
            </div>
            <div class="form-row" style="margin-top:0.75rem; display:flex; gap:0.75rem; align-items:center;">
                <button type="submit" class="btn-filter">Rechercher</button>
                <a href="commentaires.php" class="btn-reset-link">Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- ── Liste des commentaires ── -->
    <div class="chart-card">
        <?php if (!$commentaires): ?>
            <div class="empty">Aucun commentaire trouvé.</div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="comm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Site</th>
                        <th>Région</th>
                        <th>IP client</th>
                        <th>Ping</th>
                        <th>DL</th>
                        <th>UL</th>
                        <th>Commentaire</th>
                        <th>Log</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commentaires as $c): ?>
                    <tr>
                        <td class="muted">
                            <?= htmlspecialchars($c['DATE_COMMENTAIRE']) ?>
                        </td>
                        <td>
                            <span class="code-badge"><?= htmlspecialchars($c['CODE_GX_SITE']) ?></span>
                            <span class="muted"><?= htmlspecialchars($c['NOM_SITE']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($c['NOM_REGION']) ?></td>
                        <td class="mono"><?= htmlspecialchars($c['IP_CLIENT']) ?></td>
                        <td><?= $c['PING_LOGS'] ?> ms</td>
                        <td><?= $c['DOWNLOAD_LOGS'] ?> Mbit/s</td>
                        <td><?= $c['UPLOAD_LOGS'] ?> Mbit/s</td>
                        <td class="comm-contenu">
                            <?= htmlspecialchars($c['CONTENU_COMMENTAIRE']) ?>
                        </td>
                        <td>
                            <a href="logs.php?CODE_GX_SITE=<?= urlencode($c['CODE_GX_SITE']) ?>&nom=<?= urlencode($c['NOM_SITE']) ?>&highlight=<?= $c['ID_LOGS'] ?>"
                               class="btn-voir-log" title="Voir le test associé">
                                Logs
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 . $chaineUrl ?>">← Précédent</a>
            <?php endif; ?>
            <span>Page <?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 . $chaineUrl ?>">Suivant →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>