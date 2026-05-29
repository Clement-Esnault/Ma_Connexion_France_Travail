<?php
/**
 * backend/ip/get_alertes.php
 *
 * Retourne en JSON les sites ayant au moins une métrique insuffisante
 * selon les seuils FT_SEUILS (moyenne sur les N derniers jours, min. 3 tests).
 *
 * GET :
 *   periode  string  '7j' | '30j' | '90j' | 'tout'  (défaut: 30j)
 *   metrique string  'all' | 'ping' | 'download' | 'upload'  (défaut: all)
 *   export   string  'csv' → déclenche un téléchargement CSV
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
$periode  = trim($_GET['periode']  ?? '30j');
$metrique = trim($_GET['metrique'] ?? 'all');

$filtreDate = match ($periode) {
    '7j'  => 'AND l.DATE_LOGS >= DATE_SUB(NOW(), INTERVAL 7  DAY)',
    '30j' => 'AND l.DATE_LOGS >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    '90j' => 'AND l.DATE_LOGS >= DATE_SUB(NOW(), INTERVAL 90 DAY)',
    default => '',
};

// ── Seuils via SeuilService ───────────────────────────────────────────
$seuilService = new SeuilService($pdo);
$seuils       = $seuilService->charger();

// ── Moyennes par site (min. 3 tests) ──────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.CODE_GX_SITE, s.NOM_SITE, s.IP_RESEAU,
           d.NOM_DEPARTEMENT, r.NOM_REGION, ir.NOM_INTERREGION,
           COUNT(l.ID_LOGS)               AS nb_tests,
           ROUND(AVG(l.PING_LOGS),     1) AS moy_ping,
           ROUND(AVG(l.DOWNLOAD_LOGS), 2) AS moy_download,
           ROUND(AVG(l.UPLOAD_LOGS),   2) AS moy_upload
    FROM FT_SITE s
    JOIN FT_LOGS        l  ON  l.CODE_GX_SITE   = s.CODE_GX_SITE
    JOIN FT_DEPARTEMENT d  ON  d.ID_DEPARTEMENT = s.ID_DEPARTEMENT
    JOIN FT_REGION      r  ON  r.ID_REGION      = d.ID_REGION
    JOIN FT_INTERREGION ir ON ir.ID_INTERREGION  = r.ID_INTERREGION
    WHERE 1=1 $filtreDate
    GROUP BY s.CODE_GX_SITE, s.NOM_SITE, s.IP_RESEAU,
             d.NOM_DEPARTEMENT, r.NOM_REGION, ir.NOM_INTERREGION
    HAVING COUNT(l.ID_LOGS) >= 3
    ORDER BY moy_ping DESC
");
$stmt->execute();

// ── Détection de régression par site ─────────────────────────────────
// Régression = les 3 derniers tests sont tous insuffisants sur au moins une métrique
//              ET au moins un test antérieur était non-insuffisant sur cette même métrique.
// On récupère les 4 derniers tests de chaque site via une sous-requête par site.
//
// Pourquoi pas @rownum MySQL ?
//   Les variables de session MySQL (@rownum) provoquent une erreur de collation
//   (1267 Illegal mix) quand CODE_GX_SITE est en utf8mb4_unicode_ci et la variable
//   initialisée avec '' (utf8mb4_general_ci). On utilise à la place une requête
//   standard avec ORDER BY + LIMIT par site, exécutée en PHP sur la liste de sites.

$dernierTests = [];

// Récupérer la liste des codes GX impliqués (uniquement les sites déjà filtrés)
$stmtCodes = $pdo->prepare("
    SELECT DISTINCT l.CODE_GX_SITE
    FROM FT_LOGS l
    WHERE 1=1 $filtreDate
");
$stmtCodes->execute();
$tousLesCodes = $stmtCodes->fetchAll(PDO::FETCH_COLUMN);

// Pour chaque site, récupérer les 4 derniers tests (ORDER BY DATE_LOGS DESC LIMIT 4)
$stmtDerniers = $pdo->prepare("
    SELECT PING_LOGS, DOWNLOAD_LOGS, UPLOAD_LOGS
    FROM FT_LOGS
    WHERE CODE_GX_SITE = ?
    ORDER BY DATE_LOGS DESC
    LIMIT 4
");
foreach ($tousLesCodes as $code) {
    $stmtDerniers->execute([$code]);
    $rows = $stmtDerniers->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) >= 4) {
        $dernierTests[$code] = $rows;
    }
}

// ── Filtrage : insuffisants selon SeuilService (seuils par site si dérogation) ──
$sitesInsuffisants = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $site) {
    // Utilise les seuils dérogatoires du site s'ils existent, sinon les seuils globaux
    $seuilsSite = $seuilService->chargerPourSite($site['CODE_GX_SITE']);
    $site['verdict_ping']     = $seuilService->verdict('ping',     (float) $site['moy_ping'],     $seuilsSite);
    $site['verdict_download'] = $seuilService->verdict('download', (float) $site['moy_download'], $seuilsSite);
    $site['verdict_upload']   = $seuilService->verdict('upload',   (float) $site['moy_upload'],   $seuilsSite);
    $site['a_derogation']     = $seuilsSite !== $seuils; // info utile pour l'UI

    // Garder uniquement les sites insuffisants sur au moins une métrique
    if ($site['verdict_ping']     !== 'insuffisant'
        && $site['verdict_download'] !== 'insuffisant'
        && $site['verdict_upload']   !== 'insuffisant') continue;

    // Filtre métrique spécifique
    if ($metrique === 'ping'     && $site['verdict_ping']     !== 'insuffisant') continue;
    if ($metrique === 'download' && $site['verdict_download'] !== 'insuffisant') continue;
    if ($metrique === 'upload'   && $site['verdict_upload']   !== 'insuffisant') continue;

    // Exclure le site télétravail (mesures non représentatives du réseau WAN)
    if ($site['CODE_GX_SITE'] === 'GXTELETRAV') continue;

    // Détecter la régression pour ce site
    $site['en_regression'] = false;
    $tests = $dernierTests[$site['CODE_GX_SITE']] ?? [];
    if (count($tests) >= 4) {
        // Séquence attendue : tests[0..2] insuffisants, tests[3] non-insuffisant
        // (tests triés du plus récent au plus ancien)
        $seuilsSiteReg = $seuilService->chargerPourSite($site['CODE_GX_SITE']);
        $metriques = ['ping' => 'PING_LOGS', 'download' => 'DOWNLOAD_LOGS', 'upload' => 'UPLOAD_LOGS'];
        foreach ($metriques as $metNom => $col) {
            // Les 3 derniers tests sont-ils tous insuffisants ?
            $tousInsuffisants = true;
            for ($i = 0; $i < 3; $i++) {
                if ($seuilService->verdict($metNom, (float) $tests[$i][$col], $seuilsSiteReg) !== 'insuffisant') {
                    $tousInsuffisants = false;
                    break;
                }
            }
            // Le 4e (plus ancien) était-il non-insuffisant ?
            $avantOk = $seuilService->verdict($metNom, (float) $tests[3][$col], $seuilsSiteReg) !== 'insuffisant';
            if ($tousInsuffisants && $avantOk) {
                $site['en_regression'] = true;
                break;
            }
        }
    }

    $sitesInsuffisants[] = $site;
}

// ── Export CSV ────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    // Options modal export
    $sep       = in_array($_GET['separateur'] ?? '', [';', ','], true) ? $_GET['separateur'] : ';';
    $bom       = ($_GET['bom']   ?? '1') !== '0';
    $avecStats = ($_GET['stats'] ?? '0') === '1';
    $colsReq   = !empty($_GET['colonnes']) ? explode(',', $_GET['colonnes']) : null;

    // Toutes les colonnes disponibles (id => [label, callback])
    $toutesColonnes = [
        'code'        => ['Code site',               fn($s) => $s['CODE_GX_SITE']],
        'nom'         => ['Nom du site',              fn($s) => $s['NOM_SITE']],
        'dept'        => ['Département',              fn($s) => $s['NOM_DEPARTEMENT']],
        'region'      => ['Région',                   fn($s) => $s['NOM_REGION']],
        'interregion' => ['Interrégion',              fn($s) => $s['NOM_INTERREGION']],
        'ping'        => ['Ping moy (ms)',            fn($s) => $s['moy_ping']],
        'download'    => ['Téléch. moy (Mbit/s)',     fn($s) => $s['moy_download']],
        'upload'      => ['Envoi moy (Mbit/s)',       fn($s) => $s['moy_upload']],
        'verdict'     => ['Verdict ping',             fn($s) => $s['verdict_ping']],
        'verdict_dl'  => ['Verdict DL',               fn($s) => $s['verdict_download']],
        'verdict_ul'  => ['Verdict UL',               fn($s) => $s['verdict_upload']],
        'nb_tests'    => ['Nb tests',                 fn($s) => $s['nb_tests']],
        'derogation'  => ['Dérogation',               fn($s) => $s['a_derogation'] ? 'Oui' : 'Non'],
    ];

    // Filtrer selon colonnes demandées (ou toutes si non précisé)
    $colonnes = $colsReq
        ? array_intersect_key($toutesColonnes, array_flip($colsReq))
        : array_diff_key($toutesColonnes, ['interregion' => null, 'derogation' => null]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="alertes_' . $periode . '_' . date('Ymd') . '.csv"');
    header('Cache-Control: no-cache, no-store');

    $out = fopen('php://output', 'w');
    if ($bom) fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Stats globales si demandées
    if ($avecStats && count($sitesInsuffisants) > 0) {
        $pings = array_column($sitesInsuffisants, 'moy_ping');
        $dls   = array_column($sitesInsuffisants, 'moy_download');
        $uls   = array_column($sitesInsuffisants, 'moy_upload');
        fputcsv($out, ['# Statistiques — ' . count($sitesInsuffisants) . ' sites insuffisants'], $sep);
        fputcsv($out, ['Métrique', 'Moyenne', 'Min', 'Max'], $sep);
        fputcsv($out, ['Ping (ms)',                round(array_sum($pings)/count($pings),2), min($pings), max($pings)], $sep);
        fputcsv($out, ['Téléchargement (Mbit/s)',  round(array_sum($dls)/count($dls),2),   min($dls),   max($dls)],   $sep);
        fputcsv($out, ['Envoi (Mbit/s)',            round(array_sum($uls)/count($uls),2),   min($uls),   max($uls)],   $sep);
        fputcsv($out, [], $sep);
    }

    // En-têtes
    fputcsv($out, array_column(array_values($colonnes), 0), $sep);

    // Lignes
    foreach ($sitesInsuffisants as $site) {
        $ligne = [];
        foreach ($colonnes as [$label, $cb]) {
            $ligne[] = $cb($site);
        }
        fputcsv($out, $ligne, $sep);
    }

    fclose($out);
    exit;
}

// ── Réponse JSON ──────────────────────────────────────────────────────
echo json_encode([
    'success'  => true,
    'periode'  => $periode,
    'metrique' => $metrique,
    'seuils'   => $seuils,
    'total'    => count($sitesInsuffisants),
    'sites'    => $sitesInsuffisants,
], JSON_UNESCAPED_UNICODE);