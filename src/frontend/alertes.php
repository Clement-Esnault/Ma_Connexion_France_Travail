<?php
/**
 * sites_insuffisants.php
 * Page technicien — Sites avec au moins une métrique moyenne insuffisante.
 * Accessible depuis tech.php après connexion.
 */

require_once __DIR__ . '/../backend/includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Sites insuffisants'; require_once __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="css/alertes.css?v=<?= APP_VERSION ?>">
</head>
<body>


<?php require_once __DIR__ . '/includes/header.php'; ?>
        <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>
<!-- ── En-tête de la page ─────────────────────────────────────────── -->
<div class="page-header">

    <!-- Icône alerte -->
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
    <h1>Sites avec performances insuffisantes</h1>
    <span class="badge-count" id="badge-count">0 site</span>
</div>

<!-- ── Alerte seuils (affichée si les seuils ne sont pas configurés) -->
<div class="alert" id="alert-seuils" class="hidden">
    !! Certains seuils ne sont pas configurés — les métriques correspondantes ne peuvent pas être évaluées.
    <a href="admin/seuils.php" class="alert-link">Configurer les seuils →</a>
</div>

<!-- ── Filtres ────────────────────────────────────────────────────── -->
<div class="filters">
    <label for="sel-periode">Période</label>
    <select id="sel-periode">
        <option value="7j">7 derniers jours</option>
        <option value="30j" selected>30 derniers jours</option>
        <option value="90j">90 derniers jours</option>
        <option value="tout">Tout</option>
    </select>

    <label for="sel-metrique" class="filter-label filter-label--ml">Métrique</label>
    <select id="sel-metrique">
        <option value="all">Toutes</option>
        <option value="ping">Ping insuffisant</option>
        <option value="download">Téléchargement insuffisant</option>
        <option value="upload">Envoi insuffisant</option>
    </select>

    <label for="inp-search" class="filter-label filter-label--ml">Recherche</label>
    <input type="text" id="inp-search" placeholder="Site, département, région…" class="filter-input">

    <label class="filter-label filter-label--ml filter-chk" for="chk-regression">
        <input type="checkbox" id="chk-regression">
        📉 Régressions seulement
    </label>

    <button class="btn-export" id="btn-export" title="Exporter en CSV">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Exporter CSV
    </button>
</div>

<!-- ── KPIs ───────────────────────────────────────────────────────── -->
<div class="kpi-bar" id="kpi-bar">
    <div class="kpi"><div class="kpi-val" id="kpi-total">—</div><div class="kpi-lbl">Sites insuffisants<span class="ft-aide" data-aide="Nombre de sites ayant au moins une m&eacute;trique (ping, t&eacute;l&eacute;chargement ou envoi) en dessous du seuil minimum sur la p&eacute;riode s&eacute;lectionn&eacute;e.">?</span></div></div>
    <div class="kpi"><div class="kpi-val" id="kpi-ping">—</div><div class="kpi-lbl">Ping insuffisant<span class="ft-aide" data-aide="Sites dont le ping moyen d&eacute;passe le seuil critique. Un ping &eacute;lev&eacute; indique une latence importante entre le poste et le serveur de Montpellier.">?</span></div></div>
    <div class="kpi"><div class="kpi-val" id="kpi-dl">—</div><div class="kpi-lbl">T&eacute;l&eacute;chargement insuffisant<span class="ft-aide" data-aide="Sites dont le d&eacute;bit moyen de t&eacute;l&eacute;chargement est inf&eacute;rieur au seuil minimum. Impacte le t&eacute;l&eacute;chargement de fichiers et l'affichage des applications m&eacute;tier.">?</span></div></div>
    <div class="kpi"><div class="kpi-val" id="kpi-ul">—</div><div class="kpi-lbl">Envoi insuffisant<span class="ft-aide" data-aide="Sites dont le d&eacute;bit moyen d'envoi est inf&eacute;rieur au seuil minimum. Impacte l'envoi de pi&egrave;ces jointes, les sauvegardes et les applications cloud.">?</span></div></div>
    <div class="kpi kpi--sep" id="kpi-regression-box"><div class="kpi-val" id="kpi-regression">—</div><div class="kpi-lbl">En r&eacute;gression<span class="ft-aide" data-aide="Sites ayant enregistr&eacute; au moins 3 tests insuffisants cons&eacute;cutifs apr&egrave;s une p&eacute;riode correcte. Indique une d&eacute;gradation r&eacute;cente du r&eacute;seau.">?</span></div></div>
</div>

<!-- ── Tableau ────────────────────────────────────────────────────── -->
<div class="table-wrap">
    <div id="loading" class="state-msg">
        <div><div class="spinner"></div></div>
        <div>Chargement des données…</div>
    </div>

    <table id="sites-table" class="hidden">
        <thead>
            <tr>
                <th data-col="CODE_SITE">Code site<span class="sort-icon"></span></th>
                <th data-col="NOM_SITE">Nom du site<span class="sort-icon"></span></th>
                <th data-col="NOM_DEPARTEMENT">Département<span class="sort-icon"></span></th>
                <th data-col="NOM_REGION">Région<span class="sort-icon"></span></th>
                <th data-col="moy_ping">Ping moy.<span class="ft-aide" data-aide="Temps de r&eacute;ponse moyen en millisecondes entre le poste et le serveur. Plus la valeur est basse, meilleure est la r&eacute;activit&eacute;.">?</span><span class="sort-icon"></span></th>
                <th data-col="moy_download">T&eacute;l&eacute;ch. moy.<span class="ft-aide" data-aide="D&eacute;bit moyen de t&eacute;l&eacute;chargement en Mbit/s mesur&eacute; sur la p&eacute;riode. La valeur est la moyenne de tous les tests du site.">?</span><span class="sort-icon"></span></th>
                <th data-col="moy_upload">Envoi moy.<span class="ft-aide" data-aide="D&eacute;bit moyen d'envoi en Mbit/s mesur&eacute; sur la p&eacute;riode.">?</span><span class="sort-icon"></span></th>
                <th>R&eacute;gression</th>
                <th>M&eacute;triques insuffisantes</th>
                <th data-col="nb_tests">Tests<span class="sort-icon"></span></th>
                <th>Logs</th>
            </tr>
        </thead>
        <tbody id="sites-tbody"></tbody>
    </table>

    <div id="empty-msg" class="state-msg" class="hidden">
        <div class="icon">✅</div>
        <div>Aucun site avec des performances insuffisantes sur cette période.</div>
    </div>

    <div class="pagination" id="pagination"></div>
</div>
<script src="js/alertes.js?v=<?= APP_VERSION ?>">
   
</script>

</body>
</html>