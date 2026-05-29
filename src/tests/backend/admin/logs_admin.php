<?php
/**
 * backend/admin/logs_admin.php
 *
 * Vue admin des logs — lecture paginée + suppressions.
 * Réservé à l'administrateur (requireAdmin).
 *
 * Filtres GET : site, region, interregion, ip, date_debut, date_fin, verdict, page
 * Actions POST (CSRF) : delete_one | delete_range | delete_older_than
 * Export : GET export=csv
 *
 * Variables exposées au template frontend/admin/logs.php :
 *   $logs, $total, $totalPages, $totalInsuffisant, $page
 *   $fSite, $fRegion, $fInterregion, $fIp, $fDateDebut, $fDateFin, $fVerdict
 *   $chaineUrl, $messageOk, $messageErreur
 *
 * ── Décisions d'architecture ─────────────────────────────────────────────────
 * Filtre verdict en deux passes :
 *   1. SQL HAVING  — filtre sur les valeurs brutes avant pagination pour que
 *                    le COUNT(*) et l'OFFSET soient corrects.
 *   2. PHP post-SQL — double-check sur les verdicts calculés par SeuilService,
 *                    car les formules de verdict (ping inversé vs DL/UL) sont
 *                    plus lisibles en PHP qu'en SQL pur.
 *
 * Les valeurs de seuils dans le HAVING viennent de FT_SEUILS (BDD), pas de
 * l'utilisateur. Le cast float + sprintf() garantit l'absence d'injection SQL
 * même si un seuil BDD était corrompu.
 *
 * L'approche JOINTURES_LOGS (constante partagée) évite la duplication de la
 * hiérarchie de jointures (FT_LOGS → FT_SITE → FT_DEPT → FT_REGION → FT_INTERREGION)
 * entre la requête COUNT, la requête de pagination et l'export CSV.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SeuilService.php';
requireAdmin();

// ── Jointures SQL communes ────────────────────────────────────────────
const JOINTURES_LOGS = '
    JOIN FT_SITE        s ON l.CODE_GX_SITE   = s.CODE_GX_SITE
    JOIN FT_DEPARTEMENT d ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
    JOIN FT_REGION      r ON d.ID_REGION      = r.ID_REGION
    JOIN FT_INTERREGION i ON r.ID_INTERREGION = i.ID_INTERREGION
';

/**
 * Construit la clause WHERE et les paramètres PDO depuis les filtres GET.
 *
 * @param  array $get  Tableau $_GET ou équivalent de test
 * @return array{whereSQL: string, params: array, site: string, region: string,
 *               interregion: string, ip: string, date_debut: string,
 *               date_fin: string, verdict: string}
 */
function construireFiltres(array $get): array
{
    $where  = [];
    $params = [];

    $site        = trim($get['site']        ?? '');
    $region      = trim($get['region']      ?? '');
    $interregion = trim($get['interregion'] ?? '');
    $ip          = trim($get['ip']          ?? '');
    $dateDebut   = trim($get['date_debut']  ?? '');
    $dateFin     = trim($get['date_fin']    ?? '');
    $verdict     = trim($get['verdict']     ?? '');

    if ($site)        { $where[] = '(s.CODE_GX_SITE LIKE ? OR s.NOM_SITE LIKE ?)'; $params[] = "%$site%"; $params[] = "%$site%"; }
    if ($region)      { $where[] = 'r.NOM_REGION LIKE ?';      $params[] = "%$region%"; }
    if ($interregion) { $where[] = 'i.NOM_INTERREGION LIKE ?'; $params[] = "%$interregion%"; }
    if ($ip)          { $where[] = 'l.IP_CLIENT LIKE ?';       $params[] = "%$ip%"; }
    if ($dateDebut)   { $where[] = 'l.DATE_LOGS >= ?';         $params[] = "$dateDebut 00:00:00"; }
    if ($dateFin)     { $where[] = 'l.DATE_LOGS <= ?';         $params[] = "$dateFin 23:59:59"; }

    return [
        'whereSQL'    => $where ? 'WHERE ' . implode(' AND ', $where) : '',
        'params'      => $params,
        'site'        => $site,
        'region'      => $region,
        'interregion' => $interregion,
        'ip'          => $ip,
        'date_debut'  => $dateDebut,
        'date_fin'    => $dateFin,
        'verdict'     => $verdict,
    ];
}

// ── Export CSV ────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    $f    = construireFiltres($_GET);
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(l.DATE_LOGS, '%d/%m/%Y %H:%i') AS DATE_LOGS,
               l.IP_CLIENT, s.CODE_GX_SITE, s.NOM_SITE,
               r.NOM_REGION, i.NOM_INTERREGION,
               l.PING_LOGS, l.DOWNLOAD_LOGS, l.UPLOAD_LOGS
        FROM FT_LOGS l " . JOINTURES_LOGS . " {$f['whereSQL']}
        ORDER BY l.DATE_LOGS DESC
    ");
    $stmt->execute($f['params']);
    exporterCsv(
        'logs_admin_' . date('Ymd_His'),
        ['Date', 'IP client', 'Code site', 'Nom site', 'Région', 'Interrégion',
         'Ping (ms)', 'Téléchargement (Mbit/s)', 'Envoi (Mbit/s)'],
        array_map('array_values', $stmt->fetchAll(PDO::FETCH_ASSOC))
    );
}

$messageOk     = '';
$messageErreur = '';

// ── Suppressions (POST) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_one') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM FT_LOGS WHERE ID_LOGS = ?')->execute([$id]);
            $messageOk = "Log #$id supprimé.";
        }

    } elseif ($action === 'delete_older_than') {
        $nb = intval($_POST['nb_jours'] ?? 0);
        if ($nb > 0) {
            $stmt = $pdo->prepare('DELETE FROM FT_LOGS WHERE DATE_LOGS < NOW() - INTERVAL ? DAY');
            $stmt->execute([$nb]);
            $messageOk = $stmt->rowCount() . " log(s) supprimé(s) (antérieurs à $nb jours).";
        } else {
            $messageErreur = 'Veuillez renseigner un nombre de jours valide.';
        }

    } elseif ($action === 'delete_range') {
        $debut = $_POST['date_debut'] ?? '';
        $fin   = $_POST['date_fin']   ?? '';
        if ($debut && $fin) {
            $stmt = $pdo->prepare('DELETE FROM FT_LOGS WHERE DATE_LOGS BETWEEN ? AND ?');
            $stmt->execute(["$debut 00:00:00", "$fin 23:59:59"]);
            $messageOk = $stmt->rowCount() . " log(s) supprimé(s) entre le $debut et le $fin.";
        } else {
            $messageErreur = 'Veuillez renseigner les deux dates.';
        }
    }
}

// ── Lecture paginée ───────────────────────────────────────────────────
$filtres     = construireFiltres($_GET);
$clauseSQL   = $filtres['whereSQL'];
$params      = $filtres['params'];
$page        = max(1, intval($_GET['page'] ?? 1));
$parPage     = 50;
$decalage    = ($page - 1) * $parPage;

$requeteTotal = $pdo->prepare("SELECT COUNT(*) FROM FT_LOGS l " . JOINTURES_LOGS . " $clauseSQL");
$requeteTotal->execute($params);
$total      = (int) $requeteTotal->fetchColumn();
$totalPages = max(1, (int) ceil($total / $parPage));

// ── SeuilService — chargé une fois, utilisé pour le HAVING et les verdicts ──
$seuilService  = new SeuilService($pdo);
$seuilsCharges = $seuilService->charger();

// ── Filtre verdict — passe 1/2 : SQL HAVING ────────────────────────────────
//
// Pourquoi en SQL plutôt qu'en PHP après récupération ?
//   Sans HAVING, il faudrait charger TOUTES les lignes pour trouver les N
//   premières qui correspondent au verdict — inutilisable avec 100 000+ logs.
//   Le HAVING permet à MySQL d'écarter les lignes non conformes avant LIMIT.
//
// Pourquoi des sprintf() et non des paramètres PDO ?
//   PDO ne supporte pas les paramètres dans une clause HAVING sur des colonnes
//   numériques sans agrégat dans certaines versions MySQL/MariaDB. Les valeurs
//   viennent de FT_SEUILS (BDD contrôlée), sont castées en float et formatées
//   avec 4 décimales — aucun risque d'injection.
$havingSQL = '';
$havingParams = [];
if (!empty($filtres['verdict']) && !empty($seuilsCharges)) {
    // Les seuils viennent de la BDD (pas de l'utilisateur), mais on les caste
    // en float et on les formate pour garantir qu'aucune injection n'est possible.
    $sBonPing  = (float) ($seuilsCharges['ping']['bon']         ?? 50);
    $sMauvPing = (float) ($seuilsCharges['ping']['mauvais']     ?? 100);
    $sBonDl    = (float) ($seuilsCharges['download']['bon']     ?? 5);
    $sMauvDl   = (float) ($seuilsCharges['download']['mauvais'] ?? 3);
    $sBonUl    = (float) ($seuilsCharges['upload']['bon']       ?? 5);
    $sMauvUl   = (float) ($seuilsCharges['upload']['mauvais']   ?? 3);

    // sprintf() garantit un format numérique strict (jamais de chaîne arbitraire)
    $cPingConfort = sprintf('l.PING_LOGS     <= %.4f', $sBonPing);
    $cPingInsuff  = sprintf('l.PING_LOGS     >= %.4f', $sMauvPing);
    $cDlConfort   = sprintf('l.DOWNLOAD_LOGS >= %.4f', $sBonDl);
    $cDlInsuff    = sprintf('l.DOWNLOAD_LOGS <= %.4f', $sMauvDl);
    $cUlConfort   = sprintf('l.UPLOAD_LOGS   >= %.4f', $sBonUl);
    $cUlInsuff    = sprintf('l.UPLOAD_LOGS   <= %.4f', $sMauvUl);

    if ($filtres['verdict'] === 'confort') {
        $havingSQL = "HAVING ($cPingConfort AND $cDlConfort AND $cUlConfort)";
    } elseif ($filtres['verdict'] === 'insuffisant') {
        $havingSQL = "HAVING ($cPingInsuff OR $cDlInsuff OR $cUlInsuff)";
    } elseif ($filtres['verdict'] === 'fonctionnel') {
        $havingSQL = "HAVING NOT ($cPingConfort AND $cDlConfort AND $cUlConfort)
                        AND NOT ($cPingInsuff OR $cDlInsuff OR $cUlInsuff)";
    }
}

// Recalculer le total avec le filtre verdict
if ($havingSQL) {
    $requeteTotal2 = $pdo->prepare("SELECT COUNT(*) FROM (
        SELECT l.ID_LOGS FROM FT_LOGS l " . JOINTURES_LOGS . " $clauseSQL $havingSQL
    ) AS sub");
    $requeteTotal2->execute($params);
    $total      = (int) $requeteTotal2->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $parPage));
    $decalage   = min($decalage, max(0, ($totalPages - 1) * $parPage));
}

$stmt = $pdo->prepare("
    SELECT l.ID_LOGS,
           DATE_FORMAT(l.DATE_LOGS, '%d/%m/%Y %H:%i') AS DATE_LOGS,
           l.IP_CLIENT, l.PING_LOGS, l.DOWNLOAD_LOGS, l.UPLOAD_LOGS,
           s.CODE_GX_SITE, s.NOM_SITE, r.NOM_REGION, i.NOM_INTERREGION
    FROM FT_LOGS l " . JOINTURES_LOGS . " $clauseSQL $havingSQL
    ORDER BY l.DATE_LOGS DESC
    LIMIT ? OFFSET ?
");
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(count($params) + 1, $parPage,   PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $decalage,  PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Verdicts par ligne ────────────────────────────────────────────────
// SeuilService déjà initialisé plus haut pour le filtre SQL.
$totalInsuffisant = 0;

foreach ($logs as &$l) {
    $vPing = $seuilService->verdict('ping',     (float) $l['PING_LOGS'],     $seuilsCharges);
    $vDl   = $seuilService->verdict('download', (float) $l['DOWNLOAD_LOGS'], $seuilsCharges);
    $vUl   = $seuilService->verdict('upload',   (float) $l['UPLOAD_LOGS'],   $seuilsCharges);

    $l['VERDICT_PING'] = $vPing === 'confort' ? 'cell-bon' : ($vPing === 'fonctionnel' ? 'cell-moyen' : 'cell-mauvais');
    $l['VERDICT_DL']   = $vDl   === 'confort' ? 'cell-bon' : ($vDl   === 'fonctionnel' ? 'cell-moyen' : 'cell-mauvais');
    $l['VERDICT_UL']   = $vUl   === 'confort' ? 'cell-bon' : ($vUl   === 'fonctionnel' ? 'cell-moyen' : 'cell-mauvais');

    // Filtre verdict — passe 2/2 : PHP post-SQL
    // Double-check sur SeuilService : le HAVING SQL utilise des comparaisons
    // simples (>= seuil) alors que SeuilService gère les cas limites (égalité
    // stricte sur les bornes). Les deux passes sont cohérentes mais la PHP
    // est la source de vérité pour l'affichage des badges colorés.
    if (!empty($filtres['verdict'])) {
        $pireVerdict = in_array('insuffisant', [$vPing, $vDl, $vUl]) ? 'insuffisant'
                     : (in_array('fonctionnel', [$vPing, $vDl, $vUl]) ? 'fonctionnel' : 'confort');
        if ($pireVerdict !== $filtres['verdict']) {
            $l['_masquer'] = true;
            continue;
        }
    }

    if ($vPing === 'insuffisant' || $vDl === 'insuffisant' || $vUl === 'insuffisant') {
        $totalInsuffisant++;
    }
}
unset($l);

// Supprimer les lignes masquées par le filtre verdict
$logs = array_values(array_filter($logs, fn($l) => empty($l['_masquer'])));

// ── Variables exposées au template ────────────────────────────────────
$fSite        = $filtres['site'];
$fRegion      = $filtres['region'];
$fInterregion = $filtres['interregion'];
$fIp          = $filtres['ip'];
$fDateDebut   = $filtres['date_debut'];
$fDateFin     = $filtres['date_fin'];
$fVerdict     = $filtres['verdict'];

$chaineUrl = '';
$urlParams = array_filter([
    'site'        => $fSite,
    'region'      => $fRegion,
    'interregion' => $fInterregion,
    'ip'          => $fIp,
    'date_debut'  => $fDateDebut,
    'date_fin'    => $fDateFin,
    'verdict'     => $fVerdict,
]);
if ($urlParams) {
    $chaineUrl = '&' . http_build_query($urlParams);
}