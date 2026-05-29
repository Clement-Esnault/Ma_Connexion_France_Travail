<?php
/**
 * frontend/admin/rapport_hebdo.php — v1.9.7
 *
 * Rapport de débit réseau — période configurable (7j / 30j / mois en cours).
 * Accessible aux techniciens et admins connectés.
 * Export PDF natif via window.print().
 */
require_once __DIR__ . '/../../backend/includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Rapport de débit'; require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/rapport_hebdo.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page">
    <div class="rapport-toolbar">
        <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

        <!-- Sélecteur de période -->
        <div class="rapport-periode-selector" role="group" aria-label="Période du rapport">
            <button class="rapport-periode-btn active" data-jours="7">7 jours</button>
            <button class="rapport-periode-btn" data-jours="30">30 jours</button>
            <button class="rapport-periode-btn" data-jours="0">Ce mois</button>
        </div>

        <button class="btn-export btn-export-pdf" onclick="window.print()">
            🖨 Imprimer / PDF
        </button>
    </div>

    <!-- En-tête du rapport -->
    <div class="rapport-header" id="rapport-header">
        <div class="rapport-header-logo">
            <img src="../fonts/logo_ft.svg" alt="France Travail" height="36">
        </div>
        <div class="rapport-header-titre">
            <div class="rapport-titre">Rapport de débit réseau</div>
            <div class="rapport-sous-titre" id="rapport-periode">Chargement…</div>
        </div>
        <div class="rapport-header-meta" id="rapport-meta">Génération…</div>
    </div>

    <!-- Indicateurs nationaux -->
    <section class="rapport-section">
        <h2 class="rapport-section-titre">
            Vue nationale<span class="ft-aide" data-aide="Moyennes calcul&eacute;es sur l'ensemble des sites France Travail actifs sur la p&eacute;riode. Un verdict est attribu&eacute; selon les seuils configur&eacute;s dans l'admin.">?</span>
            <span class="rapport-badge" id="badge-periode">—</span>
        </h2>
        <div class="rapport-kpi-grid" id="kpi-nationale">
            <div class="rapport-kpi-skeleton"></div>
            <div class="rapport-kpi-skeleton"></div>
            <div class="rapport-kpi-skeleton"></div>
            <div class="rapport-kpi-skeleton"></div>
        </div>
    </section>

    <!-- Par région -->
    <section class="rapport-section">
        <h2 class="rapport-section-titre">R&eacute;sultats par r&eacute;gion<span class="ft-aide" data-aide="Moyenne des d&eacute;bits et latences par r&eacute;gion France Travail. Permet d'identifier des disparit&eacute;s g&eacute;ographiques sur le r&eacute;seau WAN.">?</span></h2>
        <div class="table-scroll">
            <table class="rapport-table" id="table-regions">
                <thead>
                    <tr>
                        <th>Région</th>
                        <th>Téléchargement</th>
                        <th>Envoi</th>
                        <th>Ping</th>
                        <th>Tests</th>
                        <th>Bilan</th>
                    </tr>
                </thead>
                <tbody id="tbody-regions">
                    <tr><td colspan="6" class="td-loading">Chargement…</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Sites insuffisants -->
    <section class="rapport-section">
        <h2 class="rapport-section-titre">
            Sites sous seuil
            <span class="rapport-badge rapport-badge--alerte" id="badge-insuffisants">0</span>
        </h2>
        <div class="table-scroll">
            <table class="rapport-table" id="table-insuffisants">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Région</th>
                        <th>Téléchargement</th>
                        <th>Envoi</th>
                        <th>Ping</th>
                        <th>Tests</th>
                        <th>Problème</th>
                    </tr>
                </thead>
                <tbody id="tbody-insuffisants">
                    <tr><td colspan="7" class="td-loading">Chargement…</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Top dégradés / Top bons -->
    <section class="rapport-section rapport-topflop">
        <div class="rapport-col">
            <h2 class="rapport-section-titre rapport-section-titre--bad">&#9888; Top 5 les plus d&eacute;grad&eacute;s<span class="ft-aide" data-aide="Les 5 sites avec les performances les plus faibles sur la p&eacute;riode, class&eacute;s par nombre de m&eacute;triques insuffisantes puis par d&eacute;bit moyen.">?</span></h2>
            <table class="rapport-table" id="table-degrades">
                <thead>
                    <tr><th>Site</th><th>Région</th><th>Téléchargement moy.</th></tr>
                </thead>
                <tbody id="tbody-degrades">
                    <tr><td colspan="3" class="td-loading">Chargement…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="rapport-col">
            <h2 class="rapport-section-titre rapport-section-titre--good">&#10003; Top 5 meilleurs<span class="ft-aide" data-aide="Les 5 sites avec les meilleures performances sur la p&eacute;riode, class&eacute;s par d&eacute;bit de t&eacute;l&eacute;chargement moyen.">?</span></h2>
            <table class="rapport-table" id="table-bons">
                <thead>
                    <tr><th>Site</th><th>Région</th><th>Téléchargement moy.</th></tr>
                </thead>
                <tbody id="tbody-bons">
                    <tr><td colspan="3" class="td-loading">Chargement…</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Pied de rapport -->
    <div class="rapport-footer" id="rapport-footer">
        Document généré par <strong>Ma Connexion</strong> — DSI France Travail Normandie
    </div>
</div>

<script src="../js/rapport_hebdo.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>