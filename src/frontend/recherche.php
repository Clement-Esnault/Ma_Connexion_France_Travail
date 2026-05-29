<?php
require_once __DIR__ . '/../backend/includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Recherche de site'; require_once __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="css/recherche.css?v=<?= APP_VERSION ?>">
    
</head>
<body>
    <script>
        const estConnecte = <?= json_encode($estConnecte) ?>;
        const isAdmin    = <?= json_encode((bool)($_SESSION['is_admin'] ?? false)) ?>;
    </script>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page">
    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

    <h2 style="margin-top:24px;">Recherche de site</h2>
    <p style="color:#888; font-size:14px;">Recherche par code site, nom ou adresse IP</p>

    <div class="search-bar">
        <input type="text" id="q" placeholder="Ex: MAURIAC, 003343, 10.30..."
            onkeydown="if(event.key==='Enter') rechercher()"
            oninput="document.getElementById('btn-clear').style.display = this.value ? 'flex' : 'none'">
        <button id="btn-clear" onclick="viderRecherche()" style="display:none">✕</button>
        <button onclick="rechercher()">Rechercher</button>
        <button id="btn-export-recherche" class="btn-export btn-export-csv" onclick="exporterCSVRecherche()" style="display:none">⬇ Exporter CSV</button>
    </div>

    <div id="pagination" class="pagination-bar"></div>
    <div id="no-results">Aucun résultat trouvé.</div>

    <div class="results-table-wrap">
        <table class="results-table" id="table" style="display:none">
            <thead>
                <tr>
                    <th data-col="CODE_GX_SITE"    onclick="trierColonne('CODE_GX_SITE')">Code</th>
                    <th data-col="NOM_SITE"        onclick="trierColonne('NOM_SITE')">Nom</th>
                    <th data-col="CODE_POSTAL"     onclick="trierColonne('CODE_POSTAL')">CP</th>
                    <th data-col="NOM_REGION"      onclick="trierColonne('NOM_REGION')">Région</th>
                    <th data-col="NOM_INTERREGION" onclick="trierColonne('NOM_INTERREGION')">Interrégion</th>
                    <th data-col="IP_RESEAU"       onclick="trierColonne('IP_RESEAU')">IP réseau</th>
                    <th data-col="MASQUE_SITE"     onclick="trierColonne('MASQUE_SITE')">Masque</th>
                    <?php if ($estConnecte): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tbody"></tbody>
        </table>
    </div>

    <!-- Pagination bas de page -->
    <div id="pagination-bottom" class="pagination-bar pagination-bar--bottom"></div>
</div>

<script src="js/recherche.js?v=<?= APP_VERSION ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>