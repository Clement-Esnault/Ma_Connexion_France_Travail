<?php
// get_config_speedtest.php — Retourne la configuration du moteur de speedtest en JSON.
// Endpoint public (pas d'authentification requise) — appelé par speedtest.js au démarrage du test.
// Retourne un objet structuré identique à CONFIGS_MODE dans speedtest.js pour remplacement direct.
//
// Paramètres v1.10 (mode unique 'precise') :
//   - dureeMsDownload : durée de mesure download en ms (stream arrêté après)
//   - dureeMsUpload   : durée de mesure upload en ms (envoi arrêté après)
//   - parallel        : connexions simultanées
//   - tailleMoBlob    : taille du blob upload en Mo (données aléatoires)
//
// RÉTROPÉDALAGE v1.9 (deux modes fast + precise) :
//   Si besoin de revenir au mode double, réintégrer dans configParDefaut() et $config :
//   'fast' => [
//       'ping'     => ['prechauffage' => 1,  'echantillons' => 5,  'delay' => 30],
//       'download' => ['dureeMsDownload' => 2000, 'parallel' => 1],
//       'upload'   => ['dureeMsUpload'   => 2000, 'parallel' => 1, 'tailleMoBlob' => 4],
//   ],
//   Et dans $config :
//   'fast' => [
//       'ping'     => ['prechauffage' => (int)($rows['fast_ping_prechauffage'] ?? 1), ...],
//       'download' => ['dureeMsDownload' => (int)($rows['fast_download_dureeMsDownload'] ?? 2000), ...],
//       'upload'   => ['dureeMsUpload' => (int)($rows['fast_upload_dureeMsUpload'] ?? 2000), ...],
//   ],
//   Les lignes fast_* sont toujours présentes en BDD — aucune migration nécessaire.

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

/**
 * Retourne la structure CONFIGS_MODE par défaut (valeurs codées en dur).
 * Utilisée si FT_CONFIG_SPEEDTEST est vide ou inaccessible.
 *
 * @return array Structure identique à CONFIGS_MODE dans speedtest.js
 */
function configParDefaut(): array {
    return [
        'precise' => [
            'ping'     => ['prechauffage' => 3,  'echantillons' => 10, 'delay' => 30],
            'download' => ['dureeMsDownload' => 6000, 'parallel' => 1],
            'upload'   => ['dureeMsUpload'   => 6000, 'parallel' => 1, 'tailleMoBlob' => 20],
        ],
        // Plafond de sanité
        'debitMaxMbitps' => 1000,
    ];
}

try {
    // Charge toutes les lignes de FT_CONFIG_SPEEDTEST en une seule requête
    // Retourne un tableau associatif CLE_CONFIG => VALEUR_CONFIG
    $rows = $pdo->query(
        'SELECT CLE_CONFIG, VALEUR_CONFIG FROM FT_CONFIG_SPEEDTEST'
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    // Table vide : fallback silencieux sur les valeurs par défaut
    if (empty($rows)) {
        error_log('[France Débit] FT_CONFIG_SPEEDTEST est vide — fallback valeurs par défaut');
        echo json_encode(['success' => true, 'config' => configParDefaut(), 'fallback' => true]);
        exit;
    }

    // Reconstruit la structure CONFIGS_MODE attendue par speedtest.js
    // ?? : fallback sur valeur par défaut si la clé est absente de la BDD
    $config = [

        // ── Mode précis (~12s) — mesures fiables ──────────────────────
        'precise' => [
            'ping'     => [
                'prechauffage' => (int)   ($rows['precise_ping_prechauffage']          ?? 3),
                'echantillons' => (int)   ($rows['precise_ping_echantillons']          ?? 10),
                'delay'        => (int)   ($rows['precise_ping_delay']                 ?? 30),
            ],
            'download' => [
                'dureeMsDownload' => (int) ($rows['precise_download_dureeMsDownload']  ?? 6000),
                'parallel'        => (int) ($rows['precise_download_parallel']         ?? 1),
            ],
            'upload'   => [
                'dureeMsUpload'   => (int) ($rows['precise_upload_dureeMsUpload']      ?? 6000),
                'parallel'        => (int) ($rows['precise_upload_parallel']           ?? 1),
                'tailleMoBlob'    => (int) ($rows['precise_upload_tailleMoBlob']       ?? 20),
            ],
        ],
    ];

    // Plafond de sanité configurable — 0 = désactivé
    $config['debitMaxMbitps'] = (int) ($rows['debit_max_mbitps'] ?? 1000);

    echo json_encode(['success' => true, 'config' => $config, 'fallback' => false]);

} catch (PDOException $e) {
    // Erreur SQL (table manquante, connexion perdue...) : fallback sans bloquer le test
    error_log('[France Débit] Erreur FT_CONFIG_SPEEDTEST : ' . $e->getMessage());
    echo json_encode(['success' => true, 'config' => configParDefaut(), 'fallback' => true]);
}