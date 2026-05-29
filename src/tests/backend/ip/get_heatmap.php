<?php
/**
 * get_heatmap.php — Retourne les moyennes de débit par heure et jour de la semaine.
 *
 * GET :
 *   site     string  CODE_GX_SITE ou 'all' (défaut: all)
 *   metrique string  'download' | 'upload' | 'ping' (défaut: download)
 *   periode  string  '7j' | '30j' | '90j' | 'tout' (défaut: 30j)
 *   mode     string  'fast' | 'precise' | 'all' (défaut: all)
 *
 * Retourne :
 *   {
 *     success: true,
 *     metrique: 'download',
 *     heatmap: [
 *       { heure: 8, jour: 2, valeur: 36.2, nb_tests: 12 },
 *       ...
 *     ]
 *   }
 *
 * Accès : techniciens et admins connectés.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SeuilService.php';

requireLogin();

// ── Paramètres ────────────────────────────────────────────────────────
$site     = trim($_GET['site']     ?? 'all');
$metrique = trim($_GET['metrique'] ?? 'download');
$periode  = trim($_GET['periode']  ?? '30j');
$mode     = trim($_GET['mode']     ?? 'all');

// ── Colonne métrique ──────────────────────────────────────────────────
$colonne = match ($metrique) {
    'ping'   => 'PING_LOGS',
    'upload' => 'UPLOAD_LOGS',
    default  => 'DOWNLOAD_LOGS',
};

// ── Filtre date ───────────────────────────────────────────────────────
$filtreDate = match ($periode) {
    '7j'  => 'AND l.DATE_LOGS >= DATE_SUB(NOW(), INTERVAL 7  DAY)',
    '30j' => 'AND l.DATE_LOGS >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    '90j' => 'AND l.DATE_LOGS >= DATE_SUB(NOW(), INTERVAL 90 DAY)',
    default => '',
};

// ── Filtre site ───────────────────────────────────────────────────────
$filtreSite  = '';
$paramsSite  = [];
if ($site !== 'all' && $site !== '') {
    $filtreSite = 'AND l.CODE_GX_SITE = :site';
    $paramsSite = [':site' => $site];
}

// ── Filtre mode (fast/precise) ────────────────────────────────────────
$filtreMode = '';
if ($mode === 'fast')    $filtreMode = 'AND l.IS_FAST = 1';
if ($mode === 'precise') $filtreMode = 'AND l.IS_FAST = 0';

// ── Requête principale ────────────────────────────────────────────────
// DAYOFWEEK : 1=dimanche, 2=lundi ... 7=samedi
// On retourne les jours 2-6 (lundi-vendredi) seulement
// et les heures 7-19 (plage horaire bureau)
try {
    $sql = "
        SELECT
            HOUR(l.DATE_LOGS)        AS heure,
            DAYOFWEEK(l.DATE_LOGS)   AS jour,
            ROUND(AVG(l.$colonne), 2) AS valeur,
            COUNT(l.ID_LOGS)          AS nb_tests
        FROM FT_LOGS l
        WHERE DAYOFWEEK(l.DATE_LOGS) BETWEEN 2 AND 6
          AND HOUR(l.DATE_LOGS) BETWEEN 7 AND 19
          $filtreDate
          $filtreSite
          $filtreMode
        GROUP BY HOUR(l.DATE_LOGS), DAYOFWEEK(l.DATE_LOGS)
        HAVING COUNT(l.ID_LOGS) >= 2
        ORDER BY heure, jour
    ";

    $requete = $pdo->prepare($sql);
    $requete->execute($paramsSite);
    $lignes = $requete->fetchAll(PDO::FETCH_ASSOC);

    // Convertit en tableau indexé heure → jour → {valeur, nb_tests}
    // pour accès rapide côté JS
    $heatmap = [];
    foreach ($lignes as $enreg) {
        $heatmap[] = [
            'heure'    => (int)   $enreg['heure'],
            'jour'     => (int)   $enreg['jour'],
            'valeur'   => (float) $enreg['valeur'],
            'nb_tests' => (int)   $enreg['nb_tests'],
        ];
    }

    // Charge les seuils pour que le JS puisse colorier les cellules
    $seuilService = new SeuilService($pdo);
    $seuils       = $seuilService->charger();

    echo json_encode([
        'success'  => true,
        'metrique' => $metrique,
        'periode'  => $periode,
        'site'     => $site,
        'seuils'   => $seuils,
        'heatmap'  => $heatmap,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}