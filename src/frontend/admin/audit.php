<?php
/**
 * frontend/admin/audit.php
 *
 * Consultation de l'historique d'audit des modifications de sites.
 * Accessible aux admins uniquement.
 *
 * Filtres disponibles :
 *   - Par technicien / admin (id_compte)
 *   - Par type d'action (AJOUT / MODIFICATION / SUPPRESSION)
 *   - Par période (nb de jours)
 *   - Par site (CODE_GX_SITE, recherche texte)
 */

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
requireAdmin();

$_navBase    = 'audit.php';
$_navIsAdmin = true;

// ── Charger la liste des comptes pour le filtre ───────────────────────
$comptes = $pdo->query(
    "SELECT ID_COMPTE, ALIAS_COMPTE, IS_ADMIN FROM FT_COMPTES ORDER BY IS_ADMIN DESC, ALIAS_COMPTE"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Audit des sites'; require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/audit.css?v=<?= APP_VERSION ?>">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="audit-main">
    <a href="../recherche.php" class="lien-retour">← Retour à la recherche</a>

    <h1 class="audit-titre">📋 Historique des modifications de sites</h1>

    <!-- ── Filtres ── -->
    <section class="audit-filtres">
        <div class="filtre-groupe">
            <label for="filtre-compte">Technicien / Admin</label>
            <select id="filtre-compte">
                <option value="">Tous</option>
                <?php foreach ($comptes as $c): ?>
                <option value="<?= $c['ID_COMPTE'] ?>">
                    <?= htmlspecialchars($c['ALIAS_COMPTE']) ?>
                    <?= $c['IS_ADMIN'] ? ' (admin)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtre-groupe">
            <label for="filtre-action">Action</label>
            <select id="filtre-action">
                <option value="">Toutes</option>
                <option value="AJOUT">➕ Ajout</option>
                <option value="MODIFICATION">✏️ Modification</option>
                <option value="SUPPRESSION">🗑️ Suppression</option>
            </select>
        </div>

        <div class="filtre-groupe">
            <label for="filtre-jours">Période</label>
            <select id="filtre-jours">
                <option value="7">7 derniers jours</option>
                <option value="30" selected>30 derniers jours</option>
                <option value="90">3 mois</option>
                <option value="0">Tout l'historique</option>
            </select>
        </div>

        <div class="filtre-groupe">
            <label for="filtre-site">Site</label>
            <input type="text" id="filtre-site" placeholder="GX… ou nom du site">
        </div>

        <button id="btn-charger" class="btn-primary">Rechercher</button>
        <button id="btn-export-audit" class="btn-export btn-export-csv" onclick="exporterCSVAudit()">⬇ Exporter CSV</button>
    </section>

    <!-- ── Compteur ── -->
    <p id="audit-compteur" class="audit-compteur"></p>

    <!-- ── Tableau ── -->
    <div class="audit-table-wrapper">
        <table class="audit-table" id="audit-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Technicien</th>
                    <th>Action</th>
                    <th>Site</th>
                    <th>IP action</th>
                    <th>Détail</th>
                </tr>
            </thead>
            <tbody id="audit-tbody">
                <tr><td colspan="6" class="audit-vide">Lancez une recherche pour afficher l'historique.</td></tr>
            </tbody>
        </table>
    </div>

    <!-- ── Pagination ── -->
    <div id="audit-pagination" class="pagination-wrapper"></div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="../js/audit.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>