<?php
require_once __DIR__ . '/../backend/includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Statistiques'; require_once __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="css/statistique.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page" id="page-stats">

    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

    <!-- ── KPIs ── -->
    <div class="kpi-grid">
        <div class="kpi-card kpi-dl">
            <div class="kpi-label">Download moyen</div>
            <div class="kpi-value">
                <span id="kpi-dl-val">—</span>
                <span class="kpi-unit" id="kpi-dl-unit" style="display:none">Mbit/s</span>
            </div>
            <div class="kpi-sub" id="kpi-sub"></div>
        </div>
        <div class="kpi-card kpi-ul">
            <div class="kpi-label">Upload moyen</div>
            <div class="kpi-value">
                <span id="kpi-ul-val">—</span>
                <span class="kpi-unit" id="kpi-ul-unit" style="display:none">Mbit/s</span>
            </div>
            <div class="kpi-sub" id="kpi-ul-sub"></div>
        </div>
        <div class="kpi-card kpi-ping">
            <div class="kpi-label">Ping moyen</div>
            <div class="kpi-value">
                <span id="kpi-ping-val">—</span>
                <span class="kpi-unit" id="kpi-ping-unit" style="display:none">ms</span>
            </div>
            <div class="kpi-sub" id="kpi-ping-sub"></div>
        </div>
        <div class="kpi-card kpi-tests">
            <div class="kpi-label">Tests effectués</div>
            <div class="kpi-value" id="kpi-tests-val">—</div>
            <div class="kpi-sub">total</div>
        </div>
    </div>

    <!-- ── Santé globale ── -->
    <div class="kpi-sante" id="kpi-sante" style="display:none">
        <span class="sante-badge confort"     id="sante-confort"></span>
        <span class="sante-badge fonctionnel" id="sante-fonctionnel"></span>
        <span class="sante-badge insuffisant" id="sante-insuffisant"></span>
        <span class="sante-label">des sites</span>
    </div>

    <!-- ── Top / Flop ── -->
    <div class="topflop-grid">
        <div class="chart-card">
            <div class="chart-header"><div>
                <div class="chart-title">Top 5 — Meilleur download</div>
                <div class="chart-subtitle">sites les plus performants</div>
            </div></div>
            <table class="topflop-table">
                <thead><tr><th>Site</th><th>DL</th><th>UL</th><th>Ping</th></tr></thead>
                <tbody id="tbody-top">
                    <tr><td colspan="4" class="tf-loading">Chargement…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="chart-card">
            <div class="chart-header"><div>
                <div class="chart-title">Flop 5 — Download le plus faible</div>
                <div class="chart-subtitle">sites les moins performants</div>
            </div></div>
            <table class="topflop-table">
                <thead><tr><th>Site</th><th>DL</th><th>UL</th><th>Ping</th></tr></thead>
                <tbody id="tbody-flop">
                    <tr><td colspan="4" class="tf-loading">Chargement…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Barre de recherche + filtres globaux ── -->
    <div class="search-section">
        <div class="search-field">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="8.5" cy="8.5" r="5.5"/><path d="M15 15l-3-3"/>
            </svg>
            <input type="text" class="search-input" id="recherche-globale"
                   placeholder="Filtrer par site, région ou interrégion…">
        </div>
        <div class="search-sep"></div>
        <div class="search-scope" id="scope-btns">
            <button class="scope-btn active" data-val="all">Tout</button>
            <button class="scope-btn" data-val="site">Site</button>
            <button class="scope-btn" data-val="region">Région</button>
            <button class="scope-btn" data-val="interregion">Interrégion</button>
        </div>
        <div class="search-sep"></div>
        <span class="search-results-count" id="search-count"></span>
        <button class="btn-reset" id="btn-reset-recherche" style="display:none">✕ Effacer</button>
        <div class="search-sep"></div>
        <label class="filter-label filter-label--nowrap">Période :</label>
        <select class="filter-select filter-select--inline" id="select-periode">
            <option value="">Toute l'histoire</option>
            <option value="7">7 jours</option>
            <option value="30" selected>30 jours</option>
            <option value="90">90 jours</option>
            <option value="365">1 an</option>
        </select>
        <div class="search-sep"></div>
        <label class="filter-label filter-label--nowrap">Mode :</label>
        <button class="mode-filter-btn active" data-mode="">Tous</button>
        <button class="mode-filter-btn" data-mode="precise">🎯 Précis</button>
        <button class="mode-filter-btn" data-mode="fast">⚡ Rapide</button>
        <div class="search-sep"></div>
        <button class="btn-export btn-export-csv" id="btn-export-stat-csv" onclick="exporterCSVStat()">⬇ CSV</button>
        <button class="btn-export btn-export-pdf" id="btn-export-stat-pdf" onclick="exporterPDFStat()">🖨 PDF</button>
    </div>

    <!-- ── Bandeau filtre actif ── -->
    <div class="filter-banner" id="filter-banner" style="display:none">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="icon-flex-shrink"><path d="M3 5h14M6 10h8M9 15h2"/></svg>
        Filtre actif :
        <span class="filter-banner-tag" id="banner-portee"></span>
        <strong id="banner-recherche"></strong>
        — <span id="banner-nb-sites"></span>
    </div>

    <!-- ── Bandeau nationale ── -->
    <div class="nationale-banner" id="nationale-banner" style="display:none">
        <div class="nat-bloc">
            <span class="nat-label">Nationale</span>
            <span id="nat-dl">—</span>
            <span id="nat-ul">—</span>
            <span id="nat-ping">—</span>
        </div>
        <div class="nat-bloc" id="filtre-bloc-nat" style="display:none">
            <div class="nat-sep">│</div>
            <div class="nat-bloc filtre-bloc">
                <span class="nat-label">Filtre</span>
                <span id="filtre-dl"></span>
                <span id="filtre-ul"></span>
                <span id="filtre-ping"></span>
            </div>
        </div>
        <div class="nat-bloc">Moyenne de tous les logs</div>
    </div>

    <!-- ── Onglets ── -->
    <div class="tabs" id="tabs-nav">
        <button class="tab-btn active" data-tab="sites">Par site</button>
        <button class="tab-btn" data-tab="departements">Par département</button>
        <button class="tab-btn" data-tab="regions">Par région</button>
        <button class="tab-btn" data-tab="interregions">Par interrégion</button>
        <button class="tab-btn" data-tab="evolution">Évolution</button>
        <button class="tab-btn" data-tab="comparaison">⚡ vs 🎯 Comparaison</button>
        <button class="tab-btn" data-tab="alertes">Alertes</button>
        <button class="tab-btn" data-tab="carte">Carte</button>
        <button class="tab-btn" data-tab="heatmap">🌡️ Heatmap horaire</button>
    </div>

    <!-- ── Panel Sites ── -->
    <div class="panel active" id="panel-sites">
        <div class="filters filters--mb">
            <span class="filter-label">Rechercher un site :</span>
            <input type="text" class="filter-input filter-input--wide" id="recherche-site"
                   placeholder="Nom du site, code GX…">
            <span class="filter-label filter-label--ml">Trier par :</span>
            <select class="filter-select" id="tri-sites">
                <option value="moy_download">Téléchargement ↓</option>
                <option value="moy_upload">Envoi ↓</option>
                <option value="moy_ping">Ping ↑</option>
                <option value="NOM_SITE">Nom A→Z</option>
                <option value="nb_tests">Nb tests ↓</option>
            </select>
            <span class="search-results-count" id="sites-count"></span>
        </div>
        <div class="chart-card">
            <div class="chart-header"><div>
                <div class="chart-title">Performances par site</div>
                <div class="chart-subtitle" id="sites-subtitle">période : 30 jours</div>
            </div></div>
            <div style="overflow-x:auto;max-height:600px;overflow-y:auto">
                <table class="topflop-table sites-table" style="width:100%">
                    <thead style="position:sticky;top:0;background:var(--ft-white);z-index:1">
                        <tr>
                            <th>Site</th><th>Région</th>
                            <th>Téléchargement (Mbit/s)</th><th>Envoi (Mbit/s)</th>
                            <th>Ping (ms)</th><th>Tests</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-sites">
                        <tr><td colspan="7" class="td-loading"><div class="spinner spinner--center"></div>Chargement…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Panel Départements ── -->
    <div class="panel" id="panel-departements">
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header"><div>
                    <div class="chart-title">Download / Upload par département</div>
                    <div class="chart-subtitle">moyenne en Mbit/s</div>
                </div></div>
                <div class="chart-wrap tall"><canvas id="chart-dept-dl-ul"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><div>
                    <div class="chart-title">Ping par département</div>
                    <div class="chart-subtitle">moyenne en ms</div>
                </div></div>
                <div class="chart-wrap tall"><canvas id="chart-dept-ping"></canvas></div>
            </div>
        </div>
    </div>

    <!-- ── Panel Régions ── -->
    <div class="panel" id="panel-regions">
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header"><div>
                    <div class="chart-title">Download / Upload par région</div>
                    <div class="chart-subtitle">moyenne en Mbit/s</div>
                </div></div>
                <div class="chart-wrap tall"><canvas id="chart-region-dl-ul"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><div>
                    <div class="chart-title">Répartition des tests</div>
                    <div class="chart-subtitle">nombre de tests par région</div>
                </div></div>
                <div class="chart-wrap tall"><canvas id="chart-region-pie"></canvas></div>
            </div>
        </div>
    </div>

    <!-- ── Panel Interrégions ── -->
    <div class="panel" id="panel-interregions">
        <div class="charts-grid full">
            <div class="chart-card">
                <div class="chart-header"><div>
                    <div class="chart-title">Comparaison interrégions</div>
                    <div class="chart-subtitle">download · upload · ping</div>
                </div></div>
                <div class="chart-wrap"><canvas id="chart-interregion"></canvas></div>
            </div>
        </div>
    </div>

    <!-- ── Panel Évolution ── -->
    <div class="panel" id="panel-evolution">
        <div class="filters">
            <span class="filter-label">Site :</span>
            <select class="filter-select" id="select-site-evolution">
                <option value="">Tous les sites</option>
            </select>
            <span class="filter-label">Métrique :</span>
            <select class="filter-select" id="select-metric">
                <option value="moy_download">Téléchargement</option>
                <option value="moy_upload">Envoi</option>
                <option value="moy_ping">Ping</option>
            </select>
            <span class="filter-label filter-label--ml-auto">Vue :</span>
            <button class="scope-btn active" id="btn-vue-ligne" data-vue="ligne">Ligne</button>
            <button class="scope-btn" id="btn-vue-heatmap" data-vue="heatmap">Heatmap</button>
        </div>
        <div id="vue-ligne">
            <div class="charts-grid full">
                <div class="chart-card">
                    <div class="chart-header"><div>
                        <div class="chart-title">Évolution mensuelle</div>
                        <div class="chart-subtitle">par site, sur la période disponible</div>
                    </div></div>
                    <div class="chart-wrap tall"><canvas id="chart-evolution"></canvas></div>
                </div>
            </div>
        </div>
        <div id="vue-heatmap-mensuelle" style="display:none">
            <div class="chart-card">
                <div class="chart-header"><div>
                    <div class="chart-title">Heatmap mensuelle</div>
                    <div class="chart-subtitle">vert = confort · jaune = fonctionnel · rouge = insuffisant</div>
                </div></div>
                <div id="heatmap-container" class="overflow-x-auto">
                    <div class="loader"><div class="spinner"></div> Chargement…</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Panel Comparaison ── -->
    <div class="panel" id="panel-comparaison">
        <div class="filters">
            <span class="filter-label">Site :</span>
            <select class="filter-select" id="select-site-comparaison">
                <option value="">Tous les sites (national)</option>
            </select>
        </div>
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header"><div>
                    <div class="chart-title">Précis vs Rapide</div>
                    <div class="chart-subtitle">moyennes ping · download · upload</div>
                </div></div>
                <div id="comparaison-container">
                    <div class="loader"><div class="spinner"></div> Chargement…</div>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><div>
                    <div class="chart-title">Graphique comparatif</div>
                    <div class="chart-subtitle">barres groupées précis / rapide</div>
                </div></div>
                <div class="chart-wrap"><canvas id="chart-comparaison"></canvas></div>
            </div>
        </div>
    </div>

    <!-- ── Panel Alertes ── -->
    <div class="panel" id="panel-alertes">
        <div class="filters">
            <span class="filter-label">Download &lt;</span>
            <input type="number" class="filter-input" id="seuil-dl" value="10" min="1">
            <span class="filter-label">Mbit/s &nbsp;|&nbsp; Ping &gt;</span>
            <input type="number" class="filter-input" id="seuil-ping" value="100" min="1">
            <span class="filter-label">ms</span>
            <button class="btn-filter" id="btn-appliquer-alertes">Appliquer</button>
        </div>
        <div class="chart-card">
            <div id="alertes-container">
                <div class="loader"><div class="spinner"></div> Chargement…</div>
            </div>
        </div>
    </div>

    <!-- ── Panel Carte ── -->
    <div class="panel" id="panel-carte">
        <div class="carte-controls">
            <div id="carte-granule-btns">
                <button class="scope-btn active" data-granule="sites">Sites</button>
                <button class="scope-btn" data-granule="regions">Régions</button>
                <button class="scope-btn" data-granule="departements">Départements</button>
            </div>
            <div id="carte-metric-btns">
                <button class="scope-btn active" data-metrique="moy_download">Téléchargement</button>
                <button class="scope-btn" data-metrique="moy_upload">Envoi</button>
                <button class="scope-btn" data-metrique="moy_ping">Ping</button>
            </div>
            <div class="carte-legende">
                <span class="legende-item confort">■ Confort</span>
                <span class="legende-item fonctionnel">■ Fonctionnel</span>
                <span class="legende-item insuffisant">■ Insuffisant</span>
                <span class="legende-item nodata">■ Sans données</span>
            </div>
        </div>
        <div id="carte-leaflet"></div>
    </div>

    <!-- ── Panel Heatmap horaire ── -->
    <div class="panel" id="panel-heatmap">
        <div class="mode-filter-row">
            <label class="pagination-info">Site</label>
            <select class="filter-select" id="hm-site">
                <option value="all">Tous les sites</option>
            </select>
            <label class="pagination-info" style="margin-left:.75rem">Métrique</label>
            <select class="filter-select" id="hm-metrique">
                <option value="download">Téléchargement</option>
                <option value="upload">Envoi</option>
                <option value="ping">Ping</option>
            </select>
            <label class="pagination-info" style="margin-left:.75rem">Période</label>
            <select class="filter-select" id="hm-periode">
                <option value="7">7 derniers jours</option>
                <option value="30" selected>30 derniers jours</option>
                <option value="90">90 derniers jours</option>
                <option value="">Tout</option>
            </select>
            <label class="pagination-info" style="margin-left:.75rem">Mode</label>
            <select class="filter-select" id="hm-mode">
                <option value="all">Tous</option>
                <option value="precise">Précis</option>
                <option value="fast">Rapide</option>
            </select>
        </div>
        <div class="hm-legende">
            <span class="hm-leg-item hm-confort">Confort</span>
            <span class="hm-leg-item hm-fonctionnel">Fonctionnel</span>
            <span class="hm-leg-item hm-insuffisant">Insuffisant</span>
            <span class="hm-leg-item hm-vide">Aucune donnée</span>
        </div>
        <div class="chart-card">
            <div class="chart-header"><div>
                <div class="chart-title">Débit moyen par heure et jour de la semaine</div>
                <div class="chart-subtitle">lundi–vendredi · 7h–19h · valeur = moyenne · petit chiffre = nb tests</div>
            </div></div>
            <div id="hm-loading" class="loader" style="display:none">
                <div class="spinner"></div> Chargement…
            </div>
            <div id="hm-empty" class="empty" style="display:none">Aucune donnée sur cette période.</div>
            <div class="table-scroll" id="hm-wrap">
                <table class="hm-table">
                    <thead id="hm-thead"></thead>
                    <tbody id="hm-tbody"></tbody>
                </table>
            </div>
            <div class="pagination-info" id="hm-info" style="margin-top:.5rem"></div>
        </div>
    </div>

</div><!-- /page -->

<script type="module" src="js/statistique.js?v=<?= APP_VERSION ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>