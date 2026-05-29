<?php
/**
 * backend/admin/import_logs.php
 *
 * Import de logs FT_LOGS depuis un fichier CSV.
 * UPSERT sur ID_LOGS : si l'ID existe, écrase ; sinon insère.
 *
 * Format CSV attendu (séparateur ; ou , auto-détecté) :
 *   ID_LOGS ; Date (dd/mm/yyyy HH:ii) ; CODE_GX_SITE ; IP_CLIENT ;
 *   MODE ; PING_LOGS ; DOWNLOAD_LOGS ; UPLOAD_LOGS
 *
 * Accès : admins uniquement.
 * Méthode : POST multipart (champ "fichier_csv")
 *
 * Réponse JSON :
 *   { success, inseres, ecrases, ignores, erreurs[], total_lignes }
 */

ini_set('display_errors', '0');
ini_set('log_errors',     '1');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireAdmin();   // admins uniquement

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

if (empty($_FILES['fichier_csv']) || $_FILES['fichier_csv']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier manquant ou erreur d\'upload']);
    exit;
}

// ── Lecture du fichier ────────────────────────────────────────────────
$tmpPath = $_FILES['fichier_csv']['tmp_name'];
$handle  = fopen($tmpPath, 'r');
if (!$handle) {
    echo json_encode(['error' => 'Impossible de lire le fichier']);
    exit;
}

// Supprimer le BOM UTF-8 éventuel
$premiere = fread($handle, 3);
if ($premiere !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
    rewind($handle); // Pas de BOM, remettre au début
} // sinon le BOM est consommé

// Auto-détection du séparateur sur la première ligne
$posApres = ftell($handle);
$ligne1   = fgets($handle);
$sep      = substr_count($ligne1, ';') >= substr_count($ligne1, ',') ? ';' : ',';
fseek($handle, $posApres);

// ── Colonnes attendues ────────────────────────────────────────────────
// Index des colonnes dans le CSV (0-based)
// Ordre : ID_LOGS ; Date ; CODE_GX_SITE ; IP_CLIENT ; MODE ; PING ; DL ; UL
const COL_ID       = 0;
const COL_DATE     = 1;
const COL_SITE     = 2;
const COL_IP       = 3;
const COL_MODE     = 4;
const COL_PING     = 5;
const COL_DL       = 6;
const COL_UL       = 7;
const NB_COLS      = 8;

$modesValides = ['precise', 'fast', 'balanced'];

// ── Lecture de l'en-tête ──────────────────────────────────────────────
$entete = fgetcsv($handle, 0, $sep);
if (!$entete || count($entete) < NB_COLS) {
    fclose($handle);
    echo json_encode(['error' => 'Format CSV invalide — en-tête manquant ou insuffisant (' . count($entete ?? []) . ' colonnes, ' . NB_COLS . ' attendues)']);
    exit;
}

// ── Préparation des requêtes ──────────────────────────────────────────
$stmtUpsert = $pdo->prepare("
    INSERT INTO FT_LOGS
        (ID_LOGS, DATE_LOGS, IP_CLIENT, CODE_GX_SITE, MODE, PING_LOGS, DOWNLOAD_LOGS, UPLOAD_LOGS)
    VALUES (?, STR_TO_DATE(?, '%d/%m/%Y %H:%i'), ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        DATE_LOGS     = VALUES(DATE_LOGS),
        IP_CLIENT     = VALUES(IP_CLIENT),
        CODE_GX_SITE  = VALUES(CODE_GX_SITE),
        MODE          = VALUES(MODE),
        PING_LOGS     = VALUES(PING_LOGS),
        DOWNLOAD_LOGS = VALUES(DOWNLOAD_LOGS),
        UPLOAD_LOGS   = VALUES(UPLOAD_LOGS)
");

$stmtExiste = $pdo->prepare("SELECT COUNT(*) FROM FT_LOGS WHERE ID_LOGS = ?");

// ── Traitement ligne par ligne ────────────────────────────────────────
$inseres  = 0;
$ecrases  = 0;
$ignores  = 0;
$erreurs  = [];
$numLigne = 1; // 1 = en-tête déjà lue

$pdo->beginTransaction();

try {
    while (($cols = fgetcsv($handle, 0, $sep)) !== false) {
        $numLigne++;

        // Ignorer lignes vides et lignes de stats (commencent par #)
        if (!$cols || trim($cols[0] ?? '') === '' || str_starts_with(trim($cols[0]), '#')) {
            $ignores++;
            continue;
        }

        if (count($cols) < NB_COLS) {
            $erreurs[] = "Ligne $numLigne : " . count($cols) . " colonnes (attendu " . NB_COLS . ")";
            $ignores++;
            continue;
        }

        $idLog  = intval(trim($cols[COL_ID]));
        $date   = trim($cols[COL_DATE]);
        $site   = trim($cols[COL_SITE]);
        $ip     = trim($cols[COL_IP]);
        $mode   = strtolower(trim($cols[COL_MODE]));
        $ping   = floatval(str_replace(',', '.', trim($cols[COL_PING])));
        $dl     = floatval(str_replace(',', '.', trim($cols[COL_DL])));
        $ul     = floatval(str_replace(',', '.', trim($cols[COL_UL])));

        // Validations
        if ($idLog <= 0) {
            $erreurs[] = "Ligne $numLigne : ID_LOGS invalide ($idLog)";
            $ignores++;
            continue;
        }
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $date)) {
            $erreurs[] = "Ligne $numLigne : date invalide ($date) — format attendu jj/mm/aaaa HH:ii";
            $ignores++;
            continue;
        }
        if (!in_array($mode, $modesValides, true)) {
            $erreurs[] = "Ligne $numLigne : mode invalide ($mode)";
            $ignores++;
            continue;
        }
        if ($ping < 0 || $dl < 0 || $ul < 0) {
            $erreurs[] = "Ligne $numLigne : valeurs négatives (ping=$ping, dl=$dl, ul=$ul)";
            $ignores++;
            continue;
        }

        // Déterminer insert ou écrase
        $stmtExiste->execute([$idLog]);
        $existe = (bool) $stmtExiste->fetchColumn();

        $stmtUpsert->execute([$idLog, $date, $ip, $site, $mode, $ping, $dl, $ul]);

        if ($existe) {
            $ecrases++;
        } else {
            $inseres++;
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    fclose($handle);
    error_log('[import_logs.php] ' . $e->getMessage());
    echo json_encode(['error' => 'Erreur BDD : ' . $e->getMessage()]);
    exit;
}

fclose($handle);

echo json_encode([
    'success'      => true,
    'inseres'      => $inseres,
    'ecrases'      => $ecrases,
    'ignores'      => $ignores,
    'total_lignes' => $numLigne - 1,
    'erreurs'      => array_slice($erreurs, 0, 20), // max 20 erreurs retournées
], JSON_UNESCAPED_UNICODE);