<?php
/**
 * getIP.php
 *
 * Retourne l'IP du client et le nom du site France Travail associé.
 * Utilisé par index.js au chargement pour afficher les badges IP et Site.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/getIP_util.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/CacheService.php';
require_once __DIR__ . '/../services/SiteResolverService.php';

$ip      = getClientIp();
$nomSite = null;

try {
    $resolver = new SiteResolverService($pdo, new CacheService());
    $nomSite  = $resolver->trouverNom($ip);
} catch (Exception $e) {
    // Silencieux — la page reste fonctionnelle sans le nom du site
}

header('Content-Type: application/json');
echo json_encode([
    'ip'   => $ip,
    'site' => $nomSite,
]);