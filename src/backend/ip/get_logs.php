<?php
/**
 * backend/ip/get_logs.php
 *
 * Retourne en JSON les logs paginés d'un site France Travail,
 * avec statistiques agrégées (avg/min/max/écart-type).
 *
 * Modes :
 *   - Normal    : CODE_GX_SITE obligatoire (technicien/admin sur un site)
 *   - Historique: mode=historique, filtre sur IP_CLIENT de la session (agent)
 *
 * GET :
 *   CODE_GX_SITE  string  Code du site (obligatoire sauf mode historique)
 *   page          int     Numéro de page (défaut : 1, 20 logs par page)
 *   date_debut    string  Filtre début YYYY-MM-DD (optionnel)
 *   date_fin      string  Filtre fin   YYYY-MM-DD (optionnel)
 *   mode          string  'precise' | 'fast' | 'balanced' | 'historique'
 *   verdict       string  'confort' | 'fonctionnel' | 'insuffisant'
 *   export        string  'csv' → téléchargement CSV
 *
 * Réponse JSON :
 *   { total, page, pages, stats: {...}, results: [...] }
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// ── Mode historique agent ─────────────────────────────────────────────
// L'agent ne fournit pas de CODE_GX_SITE — on filtre sur son IP client
$modeHistorique = (($_GET['mode'] ?? '') === 'historique');

if ($modeHistorique) {
    // Récupérer l'IP du poste agent via le même mécanisme que index.php
    require_once __DIR__ . '/../ip/getIP_util.php';
    $ipAgent = getClientIp();
    if (!$ipAgent) {
        echo json_encode(['error' => 'Impossible de déterminer votre IP']);
        exit;
    }
    $CODE_GX_SITE = null;
} else {
    $CODE_GX_SITE = trim($_GET['CODE_GX_SITE'] ?? '');
    if ($CODE_GX_SITE === '') {
        http_response_code(400);
        echo json_encode(['error' => 'CODE_GX_SITE manquant']);
        exit;
    }
}

// ── Paramètres communs ────────────────────────────────────────────────
$dateDebut = trim($_GET['date_debut'] ?? '');
$dateFin   = trim($_GET['date_fin']   ?? '');

$modesValides = ['precise', 'fast', 'balanced'];
$modeFiltre = (isset($_GET['mode']) && in_array($_GET['mode'], $modesValides, true))
    ? $_GET['mode'] : null;

// ── Construction de la clause WHERE ──────────────────────────────────
$where  = [];
$params = [];

if ($modeHistorique) {
    $where[]  = 'IP_CLIENT = ?';
    $params[] = $ipAgent;
} else {
    $where[]  = 'CODE_GX_SITE = ?';
    $params[] = $CODE_GX_SITE;
}

if ($dateDebut) { $where[] = 'DATE_LOGS >= ?'; $params[] = "$dateDebut 00:00:00"; }
if ($dateFin)   { $where[] = 'DATE_LOGS <= ?'; $params[] = "$dateFin 23:59:59"; }
if ($modeFiltre){ $where[] = 'MODE = ?';       $params[] = $modeFiltre; }

$filtreVerdict = in_array($_GET['verdict'] ?? '', ['confort', 'fonctionnel', 'insuffisant'], true)
    ? $_GET['verdict'] : null;

$clauseSQL = 'WHERE ' . implode(' AND ', $where);

// ── Export CSV ────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    // Options modal export
    $sep      = in_array($_GET['separateur'] ?? '', [';', ','], true) ? $_GET['separateur'] : ';';
    $bom      = ($_GET['bom'] ?? '1') !== '0';
    $colsReq  = !empty($_GET['colonnes']) ? explode(',', $_GET['colonnes']) : null;
    $avecStats = ($_GET['stats'] ?? '0') === '1';

    // Colonnes disponibles
    $toutesColonnes = $modeHistorique
        ? ['date', 'site', 'mode', 'ping', 'download', 'upload']
        : ['date', 'ip',   'mode', 'ping', 'download', 'upload'];
    $cols = $colsReq ? array_intersect($colsReq, $toutesColonnes) : $toutesColonnes;

    $requeteExport = $pdo->prepare("
        SELECT DATE_FORMAT(DATE_LOGS, '%d/%m/%Y %H:%i') AS DATE_LOGS,
               IP_CLIENT, CODE_GX_SITE, PING_LOGS, DOWNLOAD_LOGS, UPLOAD_LOGS, MODE
        FROM FT_LOGS $clauseSQL
        ORDER BY DATE_LOGS DESC
    ");
    $requeteExport->execute($params);
    $enregistrements = $requeteExport->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    $nomFichier = $modeHistorique
        ? 'historique_' . date('Ymd') . '.csv'
        : 'logs_' . $CODE_GX_SITE . '_' . date('Ymd') . '.csv';
    header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
    header('Cache-Control: no-cache, no-store');

    $out = fopen('php://output', 'w');
    if ($bom) fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Stats en en-tête si demandées
    if ($avecStats && count($enregistrements) > 0) {
        $pings = array_column($enregistrements, 'PING_LOGS');
        $dls   = array_column($enregistrements, 'DOWNLOAD_LOGS');
        $uls   = array_column($enregistrements, 'UPLOAD_LOGS');
        fputcsv($out, ['# Statistiques'], $sep);
        fputcsv($out, ['Métrique', 'Moyenne', 'Min', 'Max'], $sep);
        fputcsv($out, ['Ping (ms)',           round(array_sum($pings)/count($pings),2), min($pings), max($pings)], $sep);
        fputcsv($out, ['Téléchargement (Mbit/s)', round(array_sum($dls)/count($dls),2), min($dls),   max($dls)],   $sep);
        fputcsv($out, ['Envoi (Mbit/s)',       round(array_sum($uls)/count($uls),2),   min($uls),   max($uls)],   $sep);
        fputcsv($out, [], $sep);
    }

    // En-têtes colonnes
    $labels = [
        'date'     => 'Date',
        'ip'       => 'IP client',
        'site'     => 'Site',
        'mode'     => 'Mode',
        'ping'     => 'Ping (ms)',
        'download' => 'Téléchargement (Mbit/s)',
        'upload'   => 'Envoi (Mbit/s)',
    ];
    fputcsv($out, array_values(array_intersect_key($labels, array_flip($cols))), $sep);

    foreach ($enregistrements as $enreg) {
        $ligne = [];
        if (in_array('date',     $cols)) $ligne[] = $enreg['DATE_LOGS'];
        if (in_array('ip',       $cols)) $ligne[] = $enreg['IP_CLIENT'];
        if (in_array('site',     $cols)) $ligne[] = $enreg['CODE_GX_SITE'];
        if (in_array('mode',     $cols)) $ligne[] = $enreg['MODE'];
        if (in_array('ping',     $cols)) $ligne[] = $enreg['PING_LOGS'];
        if (in_array('download', $cols)) $ligne[] = $enreg['DOWNLOAD_LOGS'];
        if (in_array('upload',   $cols)) $ligne[] = $enreg['UPLOAD_LOGS'];
        fputcsv($out, $ligne, $sep);
    }
    fclose($out);
    exit;
}

// ── Export ALL (PDF côté JS) ──────────────────────────────────────────
if (($_GET['export'] ?? '') === 'all') {
    $selectExtra = $modeHistorique ? ', CODE_GX_SITE' : ', IP_CLIENT';

    // Stats sur l'ensemble filtré
    $statsAllStmt = $pdo->prepare("
        SELECT ROUND(AVG(PING_LOGS),        2) AS avg_ping,
               ROUND(MIN(PING_LOGS),        2) AS min_ping,
               ROUND(MAX(PING_LOGS),        2) AS max_ping,
               ROUND(STDDEV(PING_LOGS),     2) AS ecart_type_ping,
               ROUND(AVG(DOWNLOAD_LOGS),    2) AS avg_download,
               ROUND(MIN(DOWNLOAD_LOGS),    2) AS min_download,
               ROUND(MAX(DOWNLOAD_LOGS),    2) AS max_download,
               ROUND(STDDEV(DOWNLOAD_LOGS), 2) AS ecart_type_download,
               ROUND(AVG(UPLOAD_LOGS),      2) AS avg_upload,
               ROUND(MIN(UPLOAD_LOGS),      2) AS min_upload,
               ROUND(MAX(UPLOAD_LOGS),      2) AS max_upload,
               ROUND(STDDEV(UPLOAD_LOGS),   2) AS ecart_type_upload
        FROM FT_LOGS $clauseSQL
    ");
    $statsAllStmt->execute($params);
    $statsAll = $statsAllStmt->fetch(PDO::FETCH_ASSOC);

    $requeteAll = $pdo->prepare("
        SELECT ID_LOGS, PING_LOGS, DOWNLOAD_LOGS, UPLOAD_LOGS, MODE,
               DATE_FORMAT(DATE_LOGS, '%d/%m/%Y %H:%i') AS DATE_LOGS_FR
               $selectExtra
        FROM FT_LOGS $clauseSQL
        ORDER BY DATE_LOGS DESC
    ");
    $requeteAll->execute($params);
    $tousLogs = $requeteAll->fetchAll(PDO::FETCH_ASSOC);

    // Filtre verdict post-requête (même logique que le mode paginé)
    if ($filtreVerdict !== null) {
        require_once __DIR__ . '/../services/SeuilService.php';
        $seuilService = new SeuilService($pdo);
        $seuils       = $seuilService->charger();
        $tousLogs = array_values(array_filter($tousLogs, function ($log) use ($seuilService, $seuils, $filtreVerdict) {
            $verdictPing = $seuilService->verdict('ping',     (float) $log['PING_LOGS'],     $seuils);
            $verdictDl   = $seuilService->verdict('download', (float) $log['DOWNLOAD_LOGS'], $seuils);
            $verdictUl   = $seuilService->verdict('upload',   (float) $log['UPLOAD_LOGS'],   $seuils);
            $pire = in_array('insuffisant', [$verdictPing, $verdictDl, $verdictUl]) ? 'insuffisant'
                  : (in_array('fonctionnel', [$verdictPing, $verdictDl, $verdictUl]) ? 'fonctionnel' : 'confort');
            return $pire === $filtreVerdict;
        }));
    }

    echo json_encode([
        'total'   => count($tousLogs),
        'stats'   => $statsAll,
        'results' => $tousLogs,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Comptage ──────────────────────────────────────────────────────────
$comptage = $pdo->prepare("SELECT COUNT(*) FROM FT_LOGS $clauseSQL");
$comptage->execute($params);
$total = (int) $comptage->fetchColumn();

// ── Stats agrégées ────────────────────────────────────────────────────
$statsStmt = $pdo->prepare("
    SELECT ROUND(AVG(PING_LOGS),        2) AS avg_ping,
           ROUND(MIN(PING_LOGS),        2) AS min_ping,
           ROUND(MAX(PING_LOGS),        2) AS max_ping,
           ROUND(STDDEV(PING_LOGS),     2) AS ecart_type_ping,
           ROUND(AVG(DOWNLOAD_LOGS),    2) AS avg_download,
           ROUND(MIN(DOWNLOAD_LOGS),    2) AS min_download,
           ROUND(MAX(DOWNLOAD_LOGS),    2) AS max_download,
           ROUND(STDDEV(DOWNLOAD_LOGS), 2) AS ecart_type_download,
           ROUND(AVG(UPLOAD_LOGS),      2) AS avg_upload,
           ROUND(MIN(UPLOAD_LOGS),      2) AS min_upload,
           ROUND(MAX(UPLOAD_LOGS),      2) AS max_upload,
           ROUND(STDDEV(UPLOAD_LOGS),   2) AS ecart_type_upload
    FROM FT_LOGS $clauseSQL
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// ── Logs paginés ──────────────────────────────────────────────────────
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limite   = 20;
$decalage = ($page - 1) * $limite;

// En mode historique on ajoute CODE_GX_SITE dans le SELECT pour l'afficher
$selectExtra = $modeHistorique ? ', CODE_GX_SITE' : ', IP_CLIENT';

$requete = $pdo->prepare("
    SELECT ID_LOGS, PING_LOGS, DOWNLOAD_LOGS, UPLOAD_LOGS, MODE,
           DATE_FORMAT(DATE_LOGS, '%d/%m/%Y %H:%i') AS DATE_LOGS_FR
           $selectExtra
    FROM FT_LOGS $clauseSQL
    ORDER BY DATE_LOGS DESC
    LIMIT ? OFFSET ?
");
foreach ($params as $i => $val) {
    $requete->bindValue($i + 1, $val);
}
$requete->bindValue(count($params) + 1, $limite,   PDO::PARAM_INT);
$requete->bindValue(count($params) + 2, $decalage, PDO::PARAM_INT);
$requete->execute();

$resultats = $requete->fetchAll(PDO::FETCH_ASSOC);

// ── Filtre verdict post-requête ───────────────────────────────────────
if ($filtreVerdict !== null) {
    require_once __DIR__ . '/../services/SeuilService.php';
    $seuilService = new SeuilService($pdo);
    $seuils       = $seuilService->charger();
    $resultats = array_values(array_filter($resultats, function ($log) use ($seuilService, $seuils, $filtreVerdict) {
        $verdictPing = $seuilService->verdict('ping',     (float) $log['PING_LOGS'],     $seuils);
        $verdictDl   = $seuilService->verdict('download', (float) $log['DOWNLOAD_LOGS'], $seuils);
        $verdictUl   = $seuilService->verdict('upload',   (float) $log['UPLOAD_LOGS'],   $seuils);
        $pire = in_array('insuffisant', [$verdictPing, $verdictDl, $verdictUl]) ? 'insuffisant'
              : (in_array('fonctionnel', [$verdictPing, $verdictDl, $verdictUl]) ? 'fonctionnel' : 'confort');
        return $pire === $filtreVerdict;
    }));
}

echo json_encode([
    'total'          => $total,
    'page'           => $page,
    'pages'          => (int) ceil($total / $limite),
    'stats'          => $stats,
    'results'        => $resultats,
    'mode_historique' => $modeHistorique,
], JSON_UNESCAPED_UNICODE);