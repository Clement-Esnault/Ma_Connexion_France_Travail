<?php
// Point d'entrée HTTP pour les statistiques agrégées — retourne du JSON.
// Accessible aux techniciens et admins connectés uniquement.
// Paramètre GET : type = par_site | par_region | par_interregion | par_departement
//                        | nationale | evolution | alertes | seuils | comparaison_modes
// Paramètre GET : periode  = nb de jours (optionnel)
// Paramètre GET : mode = 'precise' | absent = tous
// Paramètre GET : flush=1  (admin uniquement) — vide le cache manuellement

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
ini_set('log_errors',     '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/StatService.php';
require_once __DIR__ . '/../services/CacheService.php';

requireLogin();

$service = new StatService($pdo);
$cache   = new CacheService();
$type    = $_GET['type'] ?? 'par_site';
$jours   = isset($_GET['periode']) ? max(1, intval($_GET['periode'])) : null;

// Filtre MODE : null = tous, 'precise' uniquement
$modesValides = ['precise'];
$mode = (isset($_GET['mode']) && in_array($_GET['mode'], $modesValides, true))
    ? $_GET['mode'] : null;

// La clé de cache inclut la période et le mode pour éviter les collisions
$suffixeMode = $mode !== null ? '_' . $mode : '';
$cleCache    = $type . ($jours ? "_${jours}j" : '_all') . $suffixeMode;

// Vidage manuel du cache (admin uniquement)
if (isset($_GET['flush']) && ($_SESSION['is_admin'] ?? false)) {
    $cache->vider();
    echo json_encode(['success' => true, 'message' => 'Cache vide.']);
    exit;
}

// TTL en secondes pour chaque type de stat
$ttlCache = [
    'par_site'          => 300,
    'par_region'        => 300,
    'par_interregion'   => 300,
    'par_departement'   => 300,
    'nationale'         => 300,
    'comparaison_modes' => 300,
];

try {
    switch ($type) {

        case 'par_site':
        case 'par_region':
        case 'par_interregion':
        case 'par_departement':
        case 'nationale':
        case 'comparaison_modes':
            $dureeCache = $ttlCache[$type];
            $data = $cache->obtenir($cleCache, $dureeCache);
            if ($data === null) {
                $data = match($type) {
                    'par_site'          => $service->parSite($jours, $mode),
                    'par_region'        => $service->parRegion($jours, $mode),
                    'par_interregion'   => $service->parInterregion($jours, $mode),
                    'par_departement'   => $service->parDepartement($jours, $mode),
                    'nationale'         => $service->nationale($jours, $mode),
                    'comparaison_modes' => $service->comparaisonModes(
                        isset($_GET['site_id']) ? trim($_GET['site_id']) : null,
                        $jours
                    ),
                };
                $cache->stocker($cleCache, $data);
            }
            echo json_encode($data);
            break;

        case 'evolution':
            $idSite = isset($_GET['site_id']) ? trim($_GET['site_id']) : null;
            echo json_encode($service->evolution($idSite, $mode));
            break;

        case 'alertes':
            $seuilDl   = isset($_GET['dl_seuil'])   ? (float)$_GET['dl_seuil']   : 10;
            $seuilPing = isset($_GET['ping_seuil']) ? (float)$_GET['ping_seuil'] : 100;
            echo json_encode($service->alertes($seuilDl, $seuilPing, $mode));
            break;

        case 'seuils':
            echo json_encode($service->seuils());
            break;

        case 'heatmap_horaire':
            $idSite  = isset($_GET['site'])    ? trim($_GET['site'])    : null;
            $metrique = isset($_GET['metrique']) ? trim($_GET['metrique']) : 'download';
            $hmPeriode = isset($_GET['hm_periode']) ? max(1, intval($_GET['hm_periode'])) : $jours;
            $hmMode  = $mode;
            echo json_encode($service->heatmapHoraire($idSite, $metrique, $hmPeriode, $hmMode));
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Type '$type' inconnu."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}