<?php
/**
 * frontend/mon_historique.php
 *
 * Page "Mon historique" — visible par tous les agents connectés.
 * Affiche les logs de tests effectués depuis le poste courant (IP_CLIENT).
 * Pas de CODE_GX_SITE : le backend filtre sur l'IP de session.
 */

require_once __DIR__ . '/../backend/includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Mon historique'; require_once __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="css/logs.css?v=<?= APP_VERSION ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page">

    <a href="profil.php" class="lien-retour">← Mon profil</a>

    <h2 class="page-title">📋 Mon historique de tests</h2>
    <p style="font-size:13px; color:var(--ft-muted); margin-bottom:1.25rem;">
        Tests effectués depuis votre poste. Les résultats sont filtrés automatiquement sur votre IP.
    </p>

    <div class="export-bar">
        <button onclick="ouvrirExportPDFHistorique()" class="btn-export btn-export-pdf">🖨 Imprimer / PDF</button>
        <button onclick="exporterCSV()" class="btn-export btn-export-csv">⬇ Exporter CSV</button>
    </div>

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

    <!-- Filtre par verdict -->
    <div class="mode-filter-row">
        <span class="filter-label">Verdict :</span>
        <button class="mode-filter-btn active" onclick="filtrerVerdict(null, this)">Tous</button>
        <button class="mode-filter-btn" onclick="filtrerVerdict('confort', this)">✅ Confort</button>
        <button class="mode-filter-btn" onclick="filtrerVerdict('fonctionnel', this)">🟡 Fonctionnel</button>
        <button class="mode-filter-btn" onclick="filtrerVerdict('insuffisant', this)">❌ Insuffisant</button>
    </div>

    <div id="total-logs" class="total-logs"></div>
    <div id="stats-box"></div>

    <!-- Mini graphique évolution -->
    <div id="chart-box" class="chart-card chart-box-logs" style="display:none">
        <div class="chart-header">
            <div class="chart-title">Évolution de mes résultats</div>
            <div class="chart-subtitle" id="chart-subtitle"></div>
        </div>
        <div class="chart-wrap"><canvas id="chart-logs"></canvas></div>
    </div>

    <div id="no-results" class="no-results" style="display:none;">
        Aucun test trouvé depuis votre poste.
    </div>

    <div id="pagination"></div>

    <table class="results-table" id="table" style="display:none">
        <thead>
            <tr>
                <th>Date</th>
                <th>Site</th>
                <th>Mode</th>
                <th>Ping (ms)</th>
                <th>Téléchargement (Mbit/s)</th>
                <th>Envoi (Mbit/s)</th>
                <th>Verdict</th>
            </tr>
        </thead>
        <tbody id="tbody"></tbody>
    </table>

    <div id="pagination-bottom"></div>
</div>

<script src="js/mon_historique.js?v=<?= APP_VERSION ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>