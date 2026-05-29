<?php
// save_config_speedtest.php — Sauvegarde la configuration du moteur de speedtest.
// Méthode : POST JSON. Réservé aux administrateurs (requireAdmin).
//
// Reçoit un objet { precise: {...}, fast: {...} }
// et met à jour chaque ligne de FT_CONFIG_SPEEDTEST.
//
// Paramètres v1.9 (ReadableStream durée fixe) :
//   - dureeMsDownload : durée de mesure download en ms
//   - dureeMsUpload   : durée de mesure upload en ms
//   - parallel        : connexions simultanées
//   - tailleMoBlob    : taille du blob upload en Mo
//
// Les anciens paramètres (size, series, pause, trim, tailleMo) ont été
// supprimés en v1.9 — ne plus les inclure ici.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/CacheService.php';
requireAdmin();
header('Content-Type: application/json; charset=utf-8');

// ── Vérification CSRF ─────────────────────────────────────────────────
// Token transmis dans le header X-CSRF-Token (appel AJAX depuis config_speedtest.js)
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide.']);
    exit;
}

// ── Lecture du corps JSON ─────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corps JSON invalide.']);
    exit;
}

// ── Mapping clé BDD → chemin dans l'objet reçu ───────────────────────
// Format : [cle_bdd, mode, mesure, parametre, type_cast]
// Chaque entrée correspond à une ligne de FT_CONFIG_SPEEDTEST.
// 16 paramètres au total : 8 par mode (precise / fast)
$champs = [

    // ── Mode précis ───────────────────────────────────────────────
    ['precise_ping_prechauffage',          'precise', 'ping',     'prechauffage',    'int'],
    ['precise_ping_echantillons',          'precise', 'ping',     'echantillons',    'int'],
    ['precise_ping_delay',                 'precise', 'ping',     'delay',           'int'],
    ['precise_download_dureeMsDownload',   'precise', 'download', 'dureeMsDownload', 'int'],
    ['precise_download_parallel',          'precise', 'download', 'parallel',        'int'],
    ['precise_upload_dureeMsUpload',       'precise', 'upload',   'dureeMsUpload',   'int'],
    ['precise_upload_parallel',            'precise', 'upload',   'parallel',        'int'],
    ['precise_upload_tailleMoBlob',        'precise', 'upload',   'tailleMoBlob',    'int'],


    // ── Mode rapide ───────────────────────────────────────────────
    ['fast_ping_prechauffage',             'fast',    'ping',     'prechauffage',    'int'],
    ['fast_ping_echantillons',             'fast',    'ping',     'echantillons',    'int'],
    ['fast_ping_delay',                    'fast',    'ping',     'delay',           'int'],
    ['fast_download_dureeMsDownload',      'fast',    'download', 'dureeMsDownload', 'int'],
    ['fast_download_parallel',             'fast',    'download', 'parallel',        'int'],
    ['fast_upload_dureeMsUpload',          'fast',    'upload',   'dureeMsUpload',   'int'],
    ['fast_upload_parallel',               'fast',    'upload',   'parallel',        'int'],
    ['fast_upload_tailleMoBlob',           'fast',    'upload',   'tailleMoBlob',    'int'],

    // ── Plafond de sanité commun — 0 = désactivé ─────────────────
    ['debit_max_mbitps',                   null,      null,       null,              'int'],
];

// ── Contraintes de validation ─────────────────────────────────────────
// Valeurs min/max acceptables pour chaque paramètre.
// Les valeurs hors limites sont clampées (pas d'erreur, juste ajustées).
$limites = [
    'prechauffage'    => [0,    20],
    'echantillons'    => [1,    50],
    'delay'           => [0,   500],
    'dureeMsDownload' => [500, 30000],  // min 0.5s, max 30s
    'dureeMsUpload'   => [500, 30000],  // min 0.5s, max 30s
    'parallel'        => [1,    10],
    'tailleMoBlob'    => [1,    50],
    'debit_max'       => [0, 10000],  // 0 = désactivé (fibre datacenter)
];

try {
    // Requête préparée réutilisée pour chaque paramètre — évite N préparations
    $stmt = $pdo->prepare(
        'UPDATE FT_CONFIG_SPEEDTEST SET VALEUR_CONFIG = ? WHERE CLE_CONFIG = ?'
    );

    foreach ($champs as [$cle, $mode, $mesure, $param, $type]) {
        // Paramètre global (mode = null) : lu directement à la racine du JSON
        if ($mode === null) {
            $valeur = $body[$cle] ?? null;
        } else {
            $valeur = $body[$mode][$mesure][$param] ?? null;
        }
        if ($valeur === null) continue;

        $valeur = $type === 'float' ? floatval($valeur) : intval($valeur);

        // Clamp — paramètres globaux utilisent leur propre clé de limite
        $limiteKey = $param ?? 'debit_max';
        [$min, $max] = $limites[$limiteKey] ?? [0, PHP_INT_MAX];
        $valeur = max($min, min($max, $valeur));

        $stmt->execute([$valeur, $cle]);
    }

    // Invalide le cache — les durées de test ont potentiellement changé
    // CacheService::vider() supprime toutes les entrées en cache BDD
    (new CacheService())->vider();

    echo json_encode(['success' => true, 'message' => 'Configuration enregistrée avec succès.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}