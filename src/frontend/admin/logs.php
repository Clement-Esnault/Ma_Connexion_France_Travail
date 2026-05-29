<?php
require_once __DIR__ . '/../../backend/includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../../backend/admin/logs_admin.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Logs admin'; require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/statistique.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="../css/logs_admin.css?v=<?= APP_VERSION ?>">
</head>
<body>
    
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="page">

    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

    <?php if ($messageOk): ?>
        <div class="msg-success">✓ <?= htmlspecialchars($messageOk) ?></div>
    <?php endif; ?>
    <?php if ($messageErreur): ?>
        <div class="msg-error">✗ <?= htmlspecialchars($messageErreur) ?></div>
    <?php endif; ?>

    <!-- ── Suppression par plage (admin) ── -->
    <?php if ($_SESSION['is_admin']): ?>
    <div class="chart-card mb-section">
        <div class="chart-header">
            <div>
                <div class="chart-title">Supprimer des logs par période</div>
                <div class="chart-subtitle">Suppression définitive — irréversible</div>
            </div>
        </div>
        <form method="POST" class="delete-range-form">
            <input type="hidden" name="action" value="delete_range">
            <?= csrfField() ?>
            <div class="form-row">
                <label>Du <input type="date" name="date_debut" required></label>
                <label>Au <input type="date" name="date_fin"   required></label>
                <button type="submit" class="btn-delete">Supprimer</button>
            </div>
        </form>
    </div>

    <!-- ── Suppression des logs antérieurs à X jours ── -->
    <div class="chart-card mb-section">
        <div class="chart-header">
            <div>
                <div class="chart-title">Supprimer les logs de plus de X jours</div>
                <div class="chart-subtitle">Suppression définitive — irréversible</div>
            </div>
        </div>
        <form method="POST" class="delete-range-form">
            <input type="hidden" name="action" value="delete_older_than">
            <?= csrfField() ?>
            <div class="form-row">
                <label>Supprimer les logs de plus de
                    <input type="number" id="nb_jours" name="nb_jours" min="1" value="30"
                           class="input-nb-jours">
                    jours
                </label>
                <button type="submit" class="btn-delete">Supprimer</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── Filtres de recherche ── -->
    <div class="chart-card mb-section">
        <div class="chart-header">
            <div>
                <div class="chart-title">Recherche</div>
                <div class="chart-subtitle"><?= number_format($total, 0, ',', ' ') ?> log(s) trouvé(s)</div>
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
                    Interrégion
                    <input type="text" name="interregion" value="<?= htmlspecialchars($fInterregion) ?>"
                           placeholder="Nom d'interrégion...">
                </label>
                <label>
                    IP client
                    <input type="text" name="ip" value="<?= htmlspecialchars($fIp) ?>"
                           placeholder="ex: 10.30...">
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
            <div class="form-row form-row--mt">
                <button type="submit" class="btn-filter">Rechercher</button>
                <a href="logs.php" class="btn-reset-link">Réinitialiser</a>
                <a href="<?= htmlspecialchars('?export=csv' . $chaineUrl) ?>"
                   class="btn-filter btn-filter--csv">
                    ↓ Export CSV
                </a>
            </div>
        </form>
    </div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 . $chaineUrl ?>">← Précédent</a>
    <?php endif; ?>

    <span>Page <?= $page ?> / <?= $totalPages ?></span>

    <!-- Aller à la page -->
    <form method="GET" class="pagination-form">
        <span class="pagination-label">Aller à :</span>
        <input type="number" name="goto_page"
               min="1" max="<?= $totalPages ?>"
               value="<?= $page ?>"
               class="pagination-input">
        <span class="pagination-label">/ <?= $totalPages ?></span>
    </form>

    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 . $chaineUrl ?>">Suivant →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

    <!-- ── Table des logs ── -->
    <div class="chart-card">
        <?php if (!$logs): ?>
            <div class="empty">Aucun log trouvé.</div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="logs-table">
                <colgroup>
                    <col style="width:50px">   <!-- # -->
                    <col style="width:130px">  <!-- Date -->
                    <col style="width:220px">  <!-- Site -->
                    <col style="width:120px">  <!-- Région -->
                    <col style="width:110px">  <!-- Interrégion -->
                    <col style="width:130px">  <!-- IP client -->
                    <col style="width:90px">   <!-- Ping -->
                    <col style="width:110px">  <!-- Download -->
                    <col style="width:110px">  <!-- Upload -->
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Site</th>
                        <th>Région</th>
                        <th>Interrégion</th>
                        <th>IP client</th>
                        <th>Ping</th>
                        <th>Téléchargement</th>
                        <th>Envoi</th>
                        <?php if ($_SESSION['is_admin']): ?>
                        <th>Supprimer</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="muted"><?= $l['ID_LOGS'] ?></td>
                        <td><?= htmlspecialchars($l['DATE_LOGS']) ?></td>
                        <td>
                            <span class="code-badge"><?= htmlspecialchars($l['CODE_GX_SITE']) ?></span>
                            <span class="muted"><?= htmlspecialchars($l['NOM_SITE']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($l['NOM_REGION']) ?></td>
                        <td><?= htmlspecialchars($l['NOM_INTERREGION']) ?></td>
                        <td class="mono"><?= htmlspecialchars($l['IP_CLIENT']) ?></td>
                        <td><?= $l['PING_LOGS'] ?> ms</td>
                        <td><?= $l['DOWNLOAD_LOGS'] ?> Mbit/s</td>
                        <td><?= $l['UPLOAD_LOGS'] ?> Mbit/s</td>
                        <?php if ($_SESSION['is_admin']): ?>
                       <td>
                            <form method="POST">
                                <input type="hidden" name="action" value="delete_one">
                                <input type="hidden" name="id"     value="<?= $l['ID_LOGS'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn-delete btn-sm">Supprimer</button>
                            </form>
                        </td>
                        <?php endif; ?>
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

    <!-- Aller à la page -->
    <form method="GET" class="pagination-form">
        <span class="pagination-label">Aller à :</span>
        <input type="number" name="goto_page"
               min="1" max="<?= $totalPages ?>"
               value="<?= $page ?>"
               class="pagination-input">
        <span class="pagination-label">/ <?= $totalPages ?></span>
    </form>

    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 . $chaineUrl ?>">Suivant →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

        <?php endif; ?>
    </div>

</div>
<script src="../js/logs_admin.js?v=<?= APP_VERSION ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>