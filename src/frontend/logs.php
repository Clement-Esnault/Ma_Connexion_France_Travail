<?php
require_once __DIR__ . '/../backend/includes/auth.php';
requireLogin();

$q         = htmlspecialchars($_GET['q']         ?? '');
$from_page = intval($_GET['from_page'] ?? 1);
$retour    = 'recherche.php' . ($q !== '' ? '?q=' . urlencode($q) . '&from_page=' . $from_page : '');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Logs'; require_once __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="css/logs.css?v=<?= APP_VERSION ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>

<body>

<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="page">

    <a href="<?= $retour ?>" class="lien-retour">← Retour à la recherche</a>

    <div class="export-bar">
        <button onclick="ouvrirModalPDF()" id="btn-export-pdf" class="btn-export btn-export-pdf">🖨 Imprimer / PDF</button>
        <button onclick="exporterCSV()" class="btn-export btn-export-csv">⬇ Exporter CSV</button>
    </div>

    <h2 id="titre" class="page-title">Logs du site</h2>

    <!-- Filtre par période -->
    <div class="date-filter-row">
        <label class="filter-label">Du
            <input type="date" id="filtre-debut" class="filter-date-input">
        </label>
        <label class="filter-label">Au
            <input type="date" id="filtre-fin" class="filter-date-input">
        </label>
        <button onclick="appliquerFiltre()" class="btn-filtrer">Filtrer</button>
        <button onclick="reinitialiserFiltre()" id="btn-reset-filtre" class="btn-reinit" style="display:none;">
            ✕ Réinitialiser
        </button>
    </div>

    <!-- Filtre par mode -->
    <div class="mode-filter-row">
        <span class="filter-label">Mode :</span>
        <button class="mode-filter-btn active" onclick="filtrerMode(null, this)">Tous</button>
        <button class="mode-filter-btn" onclick="filtrerMode('precise', this)">🎯 Précis</button>
        <button class="mode-filter-btn" onclick="filtrerMode('balanced', this)">⚖ Équilibré</button>
        <button class="mode-filter-btn" onclick="filtrerMode('fast', this)">⚡ Rapide</button>
    </div>

    <div id="total-logs" class="total-logs"></div>

    <!-- Filtre verdict -->
    <div class="mode-filter-row" id="filtre-verdict-row" style="display:none">
        <span class="filter-label">Verdict :</span>
        <button class="mode-filter-btn active" onclick="filtrerVerdict(null, this)">Tous</button>
        <button class="mode-filter-btn" onclick="filtrerVerdict('confort', this)">✅ Confort</button>
        <button class="mode-filter-btn" onclick="filtrerVerdict('fonctionnel', this)">🟡 Fonctionnel</button>
        <button class="mode-filter-btn" onclick="filtrerVerdict('insuffisant', this)">❌ Insuffisant</button>
    </div>

    <div id="stats-box"></div>

    <!-- Mini graphique évolution -->
    <div id="chart-box" class="chart-card chart-box-logs" style="display:none">
        <div class="chart-header">
            <div class="chart-title">Évolution sur la période</div>
            <div class="chart-subtitle" id="chart-subtitle"></div>
        </div>
        <div class="chart-wrap"><canvas id="chart-logs"></canvas></div>
    </div>

    <div id="no-results" class="no-results" style="display:none;">
        Aucun log trouvé pour ce site.
    </div>

    <div id="pagination"></div>

    <table class="results-table" id="table" style="display:none">
        <thead>
            <tr>
                <th>Date<span class="ft-aide" data-aide="Date et heure du test de d&eacute;bit, au fuseau horaire du serveur (Europe/Paris).">?</span></th>
                <th>IP client<span class="ft-aide" data-aide="Adresse IP du poste qui a effectu&eacute; le test. Permet d'identifier le poste pr&eacute;cis au sein du site.">?</span></th>
                <th>Mode<span class="ft-aide" data-aide="Mode du test : Pr&eacute;cis (~20s, fiable), Rapide (~4s, indicatif) ou &Eacute;quilibr&eacute;. Pr&eacute;f&eacute;rer les mesures Pr&eacute;cises pour l'analyse.">?</span></th>
                <th>Ping (ms)<span class="ft-aide" data-aide="Latence aller-retour en millisecondes entre le poste et le serveur de Montpellier. En t&eacute;l&eacute;travail, le VPN ajoute un plancher d'environ 50-55 ms.">?</span></th>
                <th>T&eacute;l&eacute;chargement (Mbit/s)<span class="ft-aide" data-aide="D&eacute;bit descendant mesur&eacute; sur une dur&eacute;e fixe via un flux HTTP. Repr&eacute;sente la bande passante disponible depuis le serveur vers le poste.">?</span></th>
                <th>Envoi (Mbit/s)<span class="ft-aide" data-aide="D&eacute;bit montant mesur&eacute; sur une dur&eacute;e fixe. Repr&eacute;sente la bande passante disponible depuis le poste vers le serveur.">?</span></th>
            </tr>
        </thead>
        <tbody id="tbody"></tbody>
    </table>

    <div id="pagination-bottom"></div>

    <!-- Modal options export PDF -->
    <div class="pdf-modal-overlay" id="pdf-modal-overlay">
        <div class="pdf-modal">
            <div class="pdf-modal-title">🖨 Export PDF</div>
            <div class="pdf-modal-sub">Choisissez les options avant de générer le PDF.</div>

            <div class="pdf-modal-options">
                <button class="pdf-opt-btn selected" id="opt-couleurs" onclick="selOptionPDF('couleurs')">
                    <span class="opt-icon">🎨</span>
                    Avec couleurs
                </button>
                <button class="pdf-opt-btn" id="opt-neutre" onclick="selOptionPDF('neutre')">
                    <span class="opt-icon">⬜</span>
                    Sans couleurs
                </button>
            </div>

            <div class="pdf-modal-seuils" id="pdf-seuils-recap">
                <!-- Rempli par JS avec les valeurs des seuils -->
            </div>

            <div class="pdf-modal-actions">
                <button class="btn-annuler" onclick="fermerModalPDF()">Annuler</button>
                <button class="btn-lancer" id="btn-lancer-pdf" onclick="lancerExportPDF()">
                    Générer le PDF
                </button>
            </div>
        </div>
    </div>
</div>
<script src="js/logs.js?v=<?= APP_VERSION ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>