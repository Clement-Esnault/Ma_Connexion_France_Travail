<?php
/**
 * index.php — Page publique de test de débit
 *
 * Page isolée (pas de session requise).
 * Charge config.php pour APP_VERSION et la connexion PDO (non utilisée directement ici).
 */
require_once __DIR__ . '/backend/config.php';

$ip = $_SERVER['REMOTE_ADDR'];

// ── Données des métriques ─────────────────────────────────────────────────────
/** @var array<string, array{label: string, unite: string, classe: string, tooltip_titre: string, tooltip_corps: string, desc: string}> */
const METRIQUES = [
    'ping' => [
        'label'         => 'Ping',
        'unite'         => 'ms',
        'classe'        => 'ping',
        'tooltip_titre' => "C'est quoi le Ping ?",
        'tooltip_corps' => "Le ping mesure le temps (en millisecondes) que met un signal pour aller de votre poste au serveur et revenir. Plus c'est bas, mieux c'est. En dessous de 50 ms : excellent. Au-dessus de 100 ms : vous risquez des lenteurs.",
        'desc'          => "Temps de réponse entre votre poste et le serveur. Plus la valeur est basse, meilleure est la réactivité de votre connexion.",
    ],
    'download' => [
        'label'         => 'Téléchargement',
        'unite'         => 'Mbit/s',
        'classe'        => '',
        'tooltip_titre' => "C'est quoi le Téléchargement ?",
        'tooltip_corps' => "C'est la vitesse à laquelle votre poste reçoit des données depuis le réseau. Ça conditionne la fluidité des pages web, des visioconférences et des applications métier. Au-dessus de 5 Mbit/s : confort. En dessous de 3 Mbit/s : difficultés.",
        'desc'          => "Vitesse de téléchargement depuis le serveur vers votre poste. Impacte la navigation web, les visioconférences et le chargement des applications.",
    ],
    'upload' => [
        'label'         => 'Envoi',
        'unite'         => 'Mbit/s',
        'classe'        => '',
        'tooltip_titre' => "C'est quoi l'Envoi ?",
        'tooltip_corps' => "C'est la vitesse à laquelle votre poste envoie des données vers le réseau. Ça conditionne l'envoi de fichiers, la qualité de votre caméra en visioconférence et les sauvegardes réseau. Au-dessus de 5 Mbit/s : confort. En dessous de 3 Mbit/s : difficultés.",
        'desc'          => "Vitesse d'envoi depuis votre poste vers le serveur. Impacte l'envoi de fichiers, les visioconférences et les sauvegardes réseau.",
    ],
];

const BAR_PREFIX = ['ping' => 'ping', 'download' => 'dl', 'upload' => 'ul'];

function renderCarte(string $mode, string $metrique): void
{
    $c          = METRIQUES[$metrique];
    $barPfx     = BAR_PREFIX[$metrique];
    $idVal      = "{$metrique}-{$mode}";
    $idBar      = "{$barPfx}-bar-{$mode}";
    $idVerdict  = "verdict-{$metrique}-{$mode}";
    $idDesc     = "desc-{$metrique}-{$mode}";
    $clsVal     = $c['classe'] ? "card-value {$c['classe']}" : 'card-value';
    $clsFill    = $c['classe'] ? "progress-fill {$c['classe']}" : 'progress-fill progress-fill--init';
    if ($c['classe']) $clsFill .= ' progress-fill--init';
    ?>
    <div class="card-col">
        <div class="card">
            <div class="card-label-row">
                <span class="card-label"><?= htmlspecialchars($c['label']) ?></span>
                <button class="tooltip-btn"
                        type="button"
                        aria-label="En savoir plus sur <?= htmlspecialchars($c['label']) ?>"
                        data-tooltip-titre="<?= htmlspecialchars($c['tooltip_titre']) ?>"
                        data-tooltip-corps="<?= htmlspecialchars($c['tooltip_corps']) ?>">?</button>
            </div>
            <div class="<?= $clsVal ?>" id="<?= $idVal ?>">--</div>
            <div class="card-unit"><?= htmlspecialchars($c['unite']) ?></div>
            <div class="progress-bar">
                <div class="<?= $clsFill ?>" id="<?= $idBar ?>"></div>
            </div>
            <div class="card-desc"><?= htmlspecialchars($c['desc']) ?></div>
            <div class="card-verdict" id="<?= $idVerdict ?>"></div>
        </div>
        <div class="card-verdict-desc" id="<?= $idDesc ?>"></div>
    </div>
    <?php
}

function renderSectionResultats(string $mode): void
{
    ?>
    <div id="section-<?= $mode ?>" class="section-resultats hidden">
        <div class="results">
            <?php renderCarte($mode, 'ping');     ?>
            <?php renderCarte($mode, 'download'); ?>
            <?php renderCarte($mode, 'upload');   ?>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Test de débit — Ma Connexion'; require_once __DIR__ . '/frontend/includes/head.php'; ?>

    <script>
    window.FT_API = {
        getIP:           'backend/ip/getIP.php',
        queueJoin:       'backend/ip/queue.php?action=join',
        queueStatus:     'backend/ip/queue.php?action=status&token=',
        queueDone:       'backend/ip/queue.php?action=done&token=',
        saveResult:      'backend/ip/save_result.php',
        getSeuils:       'backend/admin/get_seuils.php',
        getConfig:       'backend/ip/get_config_speedtest.php',
        pingEndpoint:    'backend/ip/empty.php',
        garbageEndpoint: 'backend/ip/garbage.php',
        uploadEndpoint:  'backend/ip/upload_measure.php',
    };
    </script>

    <link rel="stylesheet" href="frontend/css/index.css?v=<?= APP_VERSION ?>">
    <script src="speedtest.js?v=<?= APP_VERSION ?>"></script>
    <script src="frontend/js/index.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body>

<!-- ═══ EN-TÊTE ══════════════════════════════════════════════════════ -->
<div class="bandeau-dsi">
    Outil interne &nbsp;·&nbsp; DGA Tech &nbsp;·&nbsp; Réservé aux agents France Travail
</div>

<div class="header">
    <div class="logo-bar">
        <img src="frontend/fonts/logo_ft.svg" alt="France Travail" class="logo-ft-img">
        <div class="logo-separateur"></div>
        <span class="logo-text">Ma Connexion</span>
    </div>
</div>

<!-- ═══ CORPS ════════════════════════════════════════════════════════ -->
<p class="description-test">
    Diagnostiquez la qualité de votre connexion réseau interne en quelques secondes.
</p>

<!-- Encart pédagogique ─────────────────────────────────────────────── -->
<div class="encart-aide" role="note" aria-label="Comment lire les résultats">
    <div class="encart-aide-header" id="encart-aide-header">
        <div class="encart-aide-titre">Comment lire les résultats ?</div>
        <button class="encart-aide-toggle" id="encart-aide-toggle" type="button"
                aria-expanded="false" aria-controls="encart-aide-corps">Afficher ▼</button>
    </div>
    <div class="encart-aide-corps hidden" id="encart-aide-corps">
        <div class="encart-aide-grille">
            <div class="encart-aide-item">
                <span class="encart-aide-icone" aria-hidden="true">🔁</span>
                <div>
                    <strong>Ping (ms)</strong> — Réactivité de la connexion.<br>
                    <span class="encart-aide-seuils">
                        <span class="puce-confort" aria-hidden="true">●</span> <span id="encart-ping-confort">&lt;&nbsp;50&nbsp;ms</span> : Confort &nbsp;
                        <span class="puce-fonctionnel" aria-hidden="true">●</span> <span id="encart-ping-fonctionnel">50–100&nbsp;ms</span> : Fonctionnel &nbsp;
                        <span class="puce-insuffisant" aria-hidden="true">●</span> <span id="encart-ping-insuffisant">&gt;&nbsp;100&nbsp;ms</span> : Insuffisant
                    </span>
                </div>
            </div>
            <div class="encart-aide-item">
                <span class="encart-aide-icone" aria-hidden="true">⬇</span>
                <div>
                    <strong>Téléchargement (Mbit/s)</strong> — Vitesse de réception des données.<br>
                    <span class="encart-aide-seuils">
                        <span class="puce-confort" aria-hidden="true">●</span> <span id="encart-dl-confort">&gt;&nbsp;5&nbsp;Mbit/s</span> : Confort &nbsp;
                        <span class="puce-fonctionnel" aria-hidden="true">●</span> <span id="encart-dl-fonctionnel">3–5&nbsp;Mbit/s</span> : Fonctionnel &nbsp;
                        <span class="puce-insuffisant" aria-hidden="true">●</span> <span id="encart-dl-insuffisant">&lt;&nbsp;3&nbsp;Mbit/s</span> : Insuffisant
                    </span>
                </div>
            </div>
            <div class="encart-aide-item">
                <span class="encart-aide-icone" aria-hidden="true">⬆</span>
                <div>
                    <strong>Envoi (Mbit/s)</strong> — Vitesse d'émission des données.<br>
                    <span class="encart-aide-seuils">
                        <span class="puce-confort" aria-hidden="true">●</span> <span id="encart-ul-confort">&gt;&nbsp;5&nbsp;Mbit/s</span> : Confort &nbsp;
                        <span class="puce-fonctionnel" aria-hidden="true">●</span> <span id="encart-ul-fonctionnel">3–5&nbsp;Mbit/s</span> : Fonctionnel &nbsp;
                        <span class="puce-insuffisant" aria-hidden="true">●</span> <span id="encart-ul-insuffisant">&lt;&nbsp;3&nbsp;Mbit/s</span> : Insuffisant
                    </span>
                </div>
            </div>
        </div>
    </div><!-- /.encart-aide-corps -->
</div>

<div class="ip-badges-wrapper">
    <div class="ip-badge" id="ip" aria-live="polite">Détection de l'IP...</div>
    <div class="ip-badge" id="site" aria-live="polite">Détection du site...</div>
</div>

<!-- ═══ BLOC TEST ════════════════════════════════════════════════════ -->
<div class="bloc-test">

    <div class="btn-tester-wrapper">
        <button id="btn-lancer" type="button">Lancer le test</button>
    </div>

    <!-- Notice de sauvegarde (résultat enregistré / erreur) -->
    <div id="save-notice" class="save-notice" role="status" aria-live="polite"></div>

    <!-- File d'attente -->
    <div id="queue-box" class="queue-box-bloc hidden" role="status">
        <div class="spinner" aria-hidden="true"></div>
        <div id="queue-position" aria-live="polite">Recherche d'un créneau...</div>
    </div>

    <!-- Barre de progression globale -->
    <div id="global-progress-box" class="global-progress-box-bloc hidden" role="progressbar"
         aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="progress-header">
            <span id="global-progress-label" aria-live="polite">Ping...</span>
            <span id="global-progress-pct" aria-hidden="true">0 %</span>
        </div>
        <div class="progress-track">
            <div id="global-progress-fill" class="progress-fill--init"></div>
        </div>
    </div>

    <!-- Section de résultats -->
    <?php renderSectionResultats('precise'); ?>

</div>

<!-- ═══ TOOLTIP (overlay modal) ══════════════════════════════════════ -->
<div id="tooltip-overlay" class="tooltip-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="tooltip-titre">
    <div class="tooltip-panel">
        <div class="tooltip-panel-titre" id="tooltip-titre"></div>
        <div class="tooltip-panel-corps" id="tooltip-corps"></div>
        <button class="tooltip-panel-fermer" id="tooltip-fermer" type="button">Fermer</button>
    </div>
</div>

<!-- ═══ PIED DE PAGE ════════════════════════════════════════════════ -->
<footer class="footer">
    &copy; <?= date('Y') ?> France Travail &mdash; DGA Tech &nbsp;&middot;&nbsp; Usage interne uniquement &nbsp;&middot;&nbsp; Ma Connexion
</footer>

</body>
</html>