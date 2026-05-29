<?php
/**
 * backend/admin/rapport_hebdo.php — v1.9.8
 *
 * Génère les données du rapport de débit réseau (tests précis).
 * Accessible aux admins et techniciens connectés (requireLogin, pas requireAdmin).
 *
 * Paramètres GET :
 *   jours  int  Période : 7 (défaut) | 30 | 0 (depuis le début du mois en cours)
 *
 * Réponse JSON :
 *   - nationale       : moyennes globales + verdicts calculés côté serveur
 *   - par_region      : moyennes par région + verdicts
 *   - insuffisants    : sites sous seuil (download ou ping) avec verdicts
 *   - top_degrades    : top 5 sites les plus dégradés en download (>= 3 tests)
 *   - top_bons        : top 5 meilleurs sites en download (>= 3 tests)
 *   - nb_sites_actifs : nb de sites ayant effectué au moins 3 tests
 *   - seuils          : seuils de qualité courants
 *   - semaine         : numéro de semaine ISO + plage de dates
 *   - periode         : nombre de jours effectif + libellé
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/StatService.php';
require_once __DIR__ . '/../services/SeuilService.php';

// Ouvert aux techniciens et admins — pas seulement aux admins.
// Données en lecture seule, pas d'action critique.
requireLogin();

const MODE_RAPPORT = 'precise';

// ── Période configurable ──────────────────────────────────────────────
// 0 = depuis le début du mois en cours
$joursParam = isset($_GET['jours']) ? (int) $_GET['jours'] : 7;
$joursValides = [7, 30, 0];
if (!in_array($joursParam, $joursValides, true)) {
    $joursParam = 7;
}

// Pour StatService, null = tout l'historique — on traduit 0 en null
// mais on calcule le nb de jours réel pour l'affichage
if ($joursParam === 0) {
    // Depuis le 1er du mois en cours
    $debutMois   = (new DateTimeImmutable())->modify('first day of this month')->setTime(0, 0);
    $joursReels  = (int) $debutMois->diff(new DateTimeImmutable())->days + 1;
    $joursSQL    = $joursReels; // passer les jours exacts à StatService
    $libelleStr  = 'Depuis le 1er ' . (new DateTimeImmutable())->format('M Y');
} else {
    $joursSQL   = $joursParam;
    $joursReels = $joursParam;
    $libelleStr = $joursParam . ' derniers jours';
}

$stat   = new StatService($pdo);
$seuils = new SeuilService($pdo);

// ── Données agrégées ──────────────────────────────────────────────────
$nationale  = $stat->nationale($joursSQL, MODE_RAPPORT);
$parRegion  = $stat->parRegion($joursSQL, MODE_RAPPORT);
$parSite    = $stat->parSite($joursSQL,   MODE_RAPPORT);
$seuilsData = $seuils->charger();

// ── Verdicts nationaux — calculés côté serveur (SeuilService) ─────────
// Évite la divergence avec le JS si les seuils changent en cours de session.
$nationale['verdict_download'] = $seuils->verdict('download', (float) ($nationale['moy_download'] ?? 0), $seuilsData);
$nationale['verdict_upload']   = $seuils->verdict('upload',   (float) ($nationale['moy_upload']   ?? 0), $seuilsData);
$nationale['verdict_ping']     = $seuils->verdict('ping',     (float) ($nationale['moy_ping']      ?? 0), $seuilsData);

// ── Sites insuffisants ────────────────────────────────────────────────
$insuffisants = array_values(array_filter($parSite, function ($s) use ($seuils, $seuilsData) {
    $vDl   = $seuils->verdict('download', (float) $s['moy_download'], $seuilsData);
    $vPing = $seuils->verdict('ping',     (float) $s['moy_ping'],     $seuilsData);
    return $vDl === 'insuffisant' || $vPing === 'insuffisant';
}));

// ── Top 5 dégradés (download le plus bas, au moins 3 tests) ──────────
$sitesAvecTests = array_filter($parSite, fn($s) => (int) $s['nb_tests'] >= 3);
usort($sitesAvecTests, fn($a, $b) => $a['moy_download'] <=> $b['moy_download']);
$topDegrades = array_slice(array_values($sitesAvecTests), 0, 5);

// ── Top 5 meilleurs ───────────────────────────────────────────────────
$topBons = array_slice(array_reverse(array_values($sitesAvecTests)), 0, 5);

// ── Nb sites actifs ───────────────────────────────────────────────────
$nbSitesActifs = count($sitesAvecTests);

// ── Infos semaine ─────────────────────────────────────────────────────
$now     = new DateTimeImmutable();
$debutS  = $now->modify('monday this week');
$finS    = $now->modify('sunday this week');
$semaine = [
    'numero' => (int) $now->format('W'),
    'annee'  => (int) $now->format('o'),
    'debut'  => $debutS->format('d/m/Y'),
    'fin'    => $finS->format('d/m/Y'),
    'genere' => $now->format('d/m/Y à H:i'),
];

// ── Enrichir régions avec verdicts ────────────────────────────────────
foreach ($parRegion as &$region) {
    $region['verdict_download'] = $seuils->verdict('download', (float) $region['moy_download'], $seuilsData);
    $region['verdict_ping']     = $seuils->verdict('ping',     (float) $region['moy_ping'],     $seuilsData);
    $region['verdict_upload']   = $seuils->verdict('upload',   (float) $region['moy_upload'],   $seuilsData);
}
unset($region);

// ── Enrichir insuffisants avec verdicts ───────────────────────────────
foreach ($insuffisants as &$site) {
    $site['verdict_download'] = $seuils->verdict('download', (float) $site['moy_download'], $seuilsData);
    $site['verdict_ping']     = $seuils->verdict('ping',     (float) $site['moy_ping'],     $seuilsData);
    $site['verdict_upload']   = $seuils->verdict('upload',   (float) $site['moy_upload'],   $seuilsData);
}
unset($site);

echo json_encode([
    'nationale'       => $nationale,
    'par_region'      => $parRegion,
    'insuffisants'    => $insuffisants,
    'top_degrades'    => $topDegrades,
    'top_bons'        => $topBons,
    'nb_sites_actifs' => $nbSitesActifs,
    'seuils'          => $seuilsData,
    'semaine'         => $semaine,
    'periode'         => ['jours' => $joursReels, 'libelle' => $libelleStr, 'param' => $joursParam],
], JSON_UNESCAPED_UNICODE);