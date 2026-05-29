<?php
/**
 * difference.php — Endpoint JSON
 *
 * Compare les mesures 'fast' vs 'precise' d'une même SESSION_ID.
 * Utilise des seuils d'écart différenciés par métrique (ping / download / upload)
 * stockés dans FT_SEUILS sous les clés difference_ping, difference_download, difference_upload.
 *
 * GET :
 *   limite  int  Nombre max de sessions (10–2000, défaut : 500)
 *   jours   int  Fenêtre temporelle en jours (0 = tout)
 *
 * Réponse JSON :
 *   {
 *     seuils:   { ping: float, download: float, upload: float },
 *     totaux:   { ping: int, download: int, upload: int },
 *     ok:       { ping: int, download: int, upload: int },
 *     ko:       { ping: int, download: int, upload: int },
 *     sessions: [...]
 *   }
 */

ini_set('display_errors', '0');
ini_set('log_errors',     '1');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// ── Paramètres ────────────────────────────────────────────────────────
$limite     = max(10, min(2000, (int) ($_GET['limite'] ?? 500)));
$jours      = max(0,           (int) ($_GET['jours']  ?? 0));
$clauseDate = $jours > 0
    ? "AND f.DATE_LOGS >= DATE_SUB(NOW(), INTERVAL $jours DAY)"
    : '';

// ── Chargement des seuils différenciés ───────────────────────────────
// Fallbacks : ping 15 %, download 20 %, upload 30 %
// Si les nouvelles lignes n'existent pas encore, on tente l'ancienne clé globale.
$seuilDefautGlobal = 20.0;

try {
    $lignesSeuils = $pdo->query("
        SELECT NOM_SEUIL, VALEUR_BONNE
        FROM FT_SEUILS
        WHERE NOM_SEUIL IN (
            'difference_ping', 'difference_download', 'difference_upload',
            'difference_seuil'
        )
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Priorité aux seuils spécifiques, fallback sur le seuil global puis valeur codée en dur
    $seuilGlobal = isset($lignesSeuils['difference_seuil'])
        ? (float) $lignesSeuils['difference_seuil']
        : $seuilDefautGlobal;

    $seuils = [
        'ping'     => isset($lignesSeuils['difference_ping'])
            ? max(1.0, min(100.0, (float) $lignesSeuils['difference_ping']))
            : $seuilGlobal,
        'download' => isset($lignesSeuils['difference_download'])
            ? max(1.0, min(100.0, (float) $lignesSeuils['difference_download']))
            : $seuilGlobal,
        'upload'   => isset($lignesSeuils['difference_upload'])
            ? max(1.0, min(100.0, (float) $lignesSeuils['difference_upload']))
            : $seuilGlobal,
    ];

} catch (PDOException $e) {
    // Fallback total en cas d'erreur SQL
    $seuils = ['ping' => 15.0, 'download' => 20.0, 'upload' => 30.0];
}

// ── Requête paires fast + precise ────────────────────────────────────
try {
    $requete = $pdo->prepare("
        SELECT
            f.SESSION_ID                                            AS session_id,
            DATE_FORMAT(f.DATE_LOGS, '%d/%m/%Y %H:%i')             AS date_test,
            f.CODE_GX_SITE                                          AS site,

            CAST(f.PING_LOGS     AS DECIMAL(10,2))                  AS ping_fast,
            CAST(p.PING_LOGS     AS DECIMAL(10,2))                  AS ping_precise,
            CASE WHEN p.PING_LOGS > 0
                 THEN ROUND(ABS(f.PING_LOGS - p.PING_LOGS) / p.PING_LOGS * 100, 1)
                 ELSE NULL END                                       AS ping_ecart,

            CAST(f.DOWNLOAD_LOGS AS DECIMAL(10,2))                  AS dl_fast,
            CAST(p.DOWNLOAD_LOGS AS DECIMAL(10,2))                  AS dl_precise,
            CASE WHEN p.DOWNLOAD_LOGS > 0
                 THEN ROUND(ABS(f.DOWNLOAD_LOGS - p.DOWNLOAD_LOGS) / p.DOWNLOAD_LOGS * 100, 1)
                 ELSE NULL END                                       AS dl_ecart,

            CAST(f.UPLOAD_LOGS   AS DECIMAL(10,2))                  AS ul_fast,
            CAST(p.UPLOAD_LOGS   AS DECIMAL(10,2))                  AS ul_precise,
            CASE WHEN p.UPLOAD_LOGS > 0
                 THEN ROUND(ABS(f.UPLOAD_LOGS - p.UPLOAD_LOGS) / p.UPLOAD_LOGS * 100, 1)
                 ELSE NULL END                                       AS ul_ecart

        FROM FT_LOGS f
        INNER JOIN FT_LOGS p
            ON  p.SESSION_ID   = f.SESSION_ID
            AND p.MODE         = 'precise'
            AND p.CODE_GX_SITE = f.CODE_GX_SITE
        WHERE f.MODE = 'fast'
          AND f.SESSION_ID IS NOT NULL
          AND f.SESSION_ID != ''
          $clauseDate
        ORDER BY f.DATE_LOGS DESC
        LIMIT :limite
    ");
    $requete->bindValue(':limite', $limite, PDO::PARAM_INT);
    $requete->execute();
    $paires = $requete->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// ── Comptage avec seuils différenciés ─────────────────────────────────
$totaux   = ['ping' => 0, 'download' => 0, 'upload' => 0];
$ok       = ['ping' => 0, 'download' => 0, 'upload' => 0];
$ko       = ['ping' => 0, 'download' => 0, 'upload' => 0];
$sessions = [];

foreach ($paires as $paire) {
    // Chaque métrique comparée à son propre seuil
    $pingOk = $paire['ping_ecart'] !== null && (float) $paire['ping_ecart'] <= $seuils['ping'];
    $dlOk   = $paire['dl_ecart']   !== null && (float) $paire['dl_ecart']   <= $seuils['download'];
    $ulOk   = $paire['ul_ecart']   !== null && (float) $paire['ul_ecart']   <= $seuils['upload'];

    $totaux['ping']++;     $pingOk ? $ok['ping']++     : $ko['ping']++;
    $totaux['download']++; $dlOk   ? $ok['download']++ : $ko['download']++;
    $totaux['upload']++;   $ulOk   ? $ok['upload']++   : $ko['upload']++;

    $sessions[] = [
        'session_id'   => $paire['session_id'],
        'date'         => $paire['date_test'],
        'site'         => $paire['site'],
        'ping_fast'    => (float) $paire['ping_fast'],
        'ping_precise' => (float) $paire['ping_precise'],
        'ping_ecart'   => $paire['ping_ecart'] !== null ? (float) $paire['ping_ecart'] : null,
        'ping_ok'      => $pingOk,
        'dl_fast'      => (float) $paire['dl_fast'],
        'dl_precise'   => (float) $paire['dl_precise'],
        'dl_ecart'     => $paire['dl_ecart'] !== null ? (float) $paire['dl_ecart'] : null,
        'dl_ok'        => $dlOk,
        'ul_fast'      => (float) $paire['ul_fast'],
        'ul_precise'   => (float) $paire['ul_precise'],
        'ul_ecart'     => $paire['ul_ecart'] !== null ? (float) $paire['ul_ecart'] : null,
        'ul_ok'        => $ulOk,
    ];
}

echo json_encode([
    'seuils'   => $seuils,   // Retourner les 3 seuils pour que le JS les affiche
    'totaux'   => $totaux,
    'ok'       => $ok,
    'ko'       => $ko,
    'sessions' => $sessions,
], JSON_UNESCAPED_UNICODE);