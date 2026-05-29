<?php
/**
 * save_result.php
 *
 * Enregistre les résultats d'un test de débit dans FT_LOGS.
 * Identifie le site France Travail de l'IP client par comparaison CIDR.
 *
 * ── Corps JSON POST ───────────────────────────────────────────────────────────
 *   ping       : float   (ms)
 *   download   : float   (Mbit/s)
 *   upload     : float   (Mbit/s)
 *   mode       : string  'precise' | 'fast'
 *   session_id : string  UUID v4 — généré côté client par crypto.randomUUID()
 *                         Permet de lier les phases d'un même test (fast + precise)
 *                         dans les stats sans stocker d'identifiant utilisateur.
 *
 * ── Paramètre GET (admin localhost uniquement) ────────────────────────────────
 *   debug_ip : string  Surcharge l'IP client (localhost + session admin requis)
 *                      Utile pour tester la résolution CIDR depuis un poste DSI.
 *
 * ── Réponse JSON ─────────────────────────────────────────────────────────────
 *   Succès  : { success: true,  CODE_GX_SITE: "GXxxxxx", ip_client: "..." }
 *   Échec   : { success: false, error: "...",             ip_client: "..." }
 *
 * ── Décisions d'architecture ─────────────────────────────────────────────────
 *   - Pas d'authentification requise : la page index.php est accessible à tous
 *     les postes du réseau interne FT. L'IP client sert d'identifiant implicite.
 *   - La résolution du site (IP → CODE_GX_SITE) est déléguée à SiteResolverService
 *     qui utilise un cache JSON (CacheService) pour éviter une requête SQL CIDR
 *     coûteuse à chaque test.
 *   - La validation des valeurs (plages cohérentes) est dans ResultatService
 *     pour être réutilisable dans les tests PHPUnit sans dépendance HTTP.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/getIP_util.php';
require_once __DIR__ . '/../services/CacheService.php';
require_once __DIR__ . '/../services/SiteResolverService.php';
require_once __DIR__ . '/../services/ResultatService.php';

// ── 1. Décodage du corps JSON ─────────────────────────────────────────────────

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$ping      = floatval($data['ping']       ?? 0);
$dl        = floatval($data['download']   ?? 0);
$ul        = floatval($data['upload']     ?? 0);
$mode      = (string) ($data['mode']      ?? 'precise');
$sessionId = ResultatService::normaliserSessionId((string) ($data['session_id'] ?? ''));

// ── 2. Validation ─────────────────────────────────────────────────────────────

$service = new ResultatService($pdo);
$erreur  = $service->valider($ping, $dl, $ul, $mode, $sessionId);

if ($erreur !== null) {
    echo json_encode(['success' => false, 'error' => $erreur, 'ip_client' => null]);
    exit;
}

// ── 3. Détection de l'IP client ───────────────────────────────────────────────

$ipClient = getClientIp();

// Mode debug : localhost + session admin uniquement
if (isset($_GET['debug_ip'])) {
    $ipClient = filter_var($_GET['debug_ip'], FILTER_VALIDATE_IP) ?: $ipClient;
}

// ── 4. Résolution du site ─────────────────────────────────────────────────────
//
// SiteResolverService compare l'IP client à toutes les plages CIDR de FT_SITE.
// Exemple : IP 10.192.100.5 → plage 10.192.100.0/24 → CODE_GX_SITE = GX006400
//
// Le CacheService évite de re-calculer les masques CIDR à chaque requête.
// Si aucune plage ne correspond (IP hors réseau FT, télétravail VPN non configuré),
// l'enregistrement est refusé avec une erreur explicite.
$resolver    = new SiteResolverService($pdo, new CacheService());
$codeGxSite  = $resolver->trouverCode($ipClient);

if ($codeGxSite === null) {
    echo json_encode([
        'success'   => false,
        'error'     => 'Aucun site France Travail trouvé pour cette IP',
        'ip_client' => $ipClient,
    ]);
    exit;
}

// ── 5. Insertion en base ──────────────────────────────────────────────────────

$service->inserer($ping, $dl, $ul, $mode, $sessionId, $ipClient, $codeGxSite);

echo json_encode([
    'success'      => true,
    'CODE_GX_SITE' => $codeGxSite,
    'ip_client'    => $ipClient,
]);