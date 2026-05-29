<?php
/**
 * frontend/admin/import_logs.php
 *
 * Page d'import de logs FT_LOGS depuis un fichier CSV.
 * Accessible aux admins uniquement.
 */
require_once __DIR__ . '/../../backend/includes/auth.php';
requireLogin();
requireAdmin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Import logs'; require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/import_logs.css?v=<?= APP_VERSION ?>">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page">
    <a href="../recherche.php" class="lien-retour">← Retour</a>

    <div class="import-box">
        <div class="import-title">📥 Import de logs CSV</div>
        <div class="import-sub">Importer des logs FT_LOGS depuis un fichier CSV. UPSERT sur ID_LOGS.</div>

        <div class="import-format">
            <strong>Format attendu</strong> — séparateur <code>;</code> ou <code>,</code> (auto-détecté), UTF-8 ou UTF-8 BOM<br>
            Colonnes : <code>ID_LOGS</code> · <code>Date (jj/mm/aaaa HH:ii)</code> · <code>CODE_GX_SITE</code> · <code>IP_CLIENT</code> · <code>MODE</code> · <code>PING_LOGS</code> · <code>DOWNLOAD_LOGS</code> · <code>UPLOAD_LOGS</code><br>
            Les lignes commençant par <code>#</code> (stats) et les lignes vides sont ignorées.
        </div>

        <div class="import-drop" id="import-drop" onclick="document.getElementById('import-file-input').click()">
            <div class="import-drop-icon">📂</div>
            <div class="import-drop-label">Cliquez ou déposez un fichier CSV ici</div>
            <div class="import-drop-file" id="import-file-name"></div>
        </div>
        <input type="file" id="import-file-input" accept=".csv,text/csv">

        <button class="import-btn" id="import-btn" disabled onclick="lancerImport()">
            Importer
        </button>

        <div class="import-result" id="import-result">
            <div class="import-result-titre" id="import-result-titre"></div>
            <div class="import-result-stats" id="import-result-stats"></div>
            <ul class="import-erreurs" id="import-erreurs"></ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="../js/import_logs.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>