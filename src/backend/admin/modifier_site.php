<?php
/**
 * backend/admin/modifier_site.php
 *
 * Modifie un site France Travail existant dans FT_SITE.
 * Accessible aux admins ET aux techniciens connectés.
 *
 * ── Corps JSON POST ───────────────────────────────────────────────────
 *   CODE_GX_SITE   : string  obligatoire (identifiant, non modifiable)
 *   NOM_SITE       : string  obligatoire
 *   ID_DEPARTEMENT : int     obligatoire
 *   IP_RESEAU      : string  optionnel (IPv4)
 *   MASQUE_SITE    : int     optionnel (8-32)
 *   ADRESSE        : string  optionnel
 *   CODE_POSTAL    : string  optionnel
 *   VILLE          : string  optionnel
 *   LATITUDE       : float   optionnel
 *   LONGITUDE      : float   optionnel
 *
 * ── Réponse JSON ─────────────────────────────────────────────────────
 *   Succès  : { success: true,  message: "..." }
 *   Conflit : { success: false, error: "...", conflit: { ... } }
 *   Échec   : { success: false, error: "..." }
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/CacheService.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../services/SeuilService.php';
require_once __DIR__ . '/../ip/getIP_util.php';
requireLogin(); // admin + tech

/*if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}*/

$corps = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Champs ────────────────────────────────────────────────────────────
$code    = strtoupper(trim($corps['CODE_GX_SITE']    ?? ''));
$nom     = trim($corps['NOM_SITE']                   ?? '');
$idDept  = ($corps['ID_DEPARTEMENT'] ?? '') !== '' ? (int) $corps['ID_DEPARTEMENT'] : null;
$ip      = trim($corps['IP_RESEAU']                  ?? '');
$masque  = ($corps['MASQUE_SITE'] ?? '') !== '' ? (int) $corps['MASQUE_SITE'] : null;
$adresse = trim($corps['ADRESSE']                    ?? '');
$cp      = trim($corps['CODE_POSTAL']                ?? '');
$ville   = trim($corps['VILLE']                      ?? '');
$lat     = ($corps['LATITUDE']  ?? '') !== '' ? (float) $corps['LATITUDE']  : null;
$lng     = ($corps['LONGITUDE'] ?? '') !== '' ? (float) $corps['LONGITUDE'] : null;

// ── Validation ────────────────────────────────────────────────────────
if ($code === '') {
    echo json_encode(['success' => false, 'error' => 'CODE_GX_SITE manquant']);
    exit;
}
if ($nom === '') {
    echo json_encode(['success' => false, 'error' => 'Le nom du site est obligatoire']);
    exit;
}
if ($idDept === null) {
    echo json_encode(['success' => false, 'error' => 'Le département est obligatoire']);
    exit;
}
if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    echo json_encode(['success' => false, 'error' => 'Adresse IP invalide']);
    exit;
}
if ($masque !== null && ($masque < 8 || $masque > 32)) {
    echo json_encode(['success' => false, 'error' => 'Masque CIDR invalide (8-32)']);
    exit;
}
if ($ip !== '' && $masque === null) {
    echo json_encode(['success' => false, 'error' => 'Masque CIDR requis quand une IP est fournie']);
    exit;
}
if ($ip === '' && $masque !== null) {
    echo json_encode(['success' => false, 'error' => 'IP requise quand un masque est fourni']);
    exit;
}

// ── Vérifier existence + snapshot avant ───────────────────────────────
$requeteAvant = $pdo->prepare(
    'SELECT CODE_GX_SITE, NOM_SITE, IP_RESEAU, MASQUE_SITE,
            ADRESSE, CODE_POSTAL, VILLE, LATITUDE, LONGITUDE,
            IP_SPECIALE, ID_DEPARTEMENT
     FROM FT_SITE WHERE CODE_GX_SITE = ?'
);
$requeteAvant->execute([$code]);
$avant = $requeteAvant->fetch(PDO::FETCH_ASSOC);

if (!$avant) {
    echo json_encode(['success' => false, 'error' => "Site $code introuvable"]);
    exit;
}

// ── Vérifier département ──────────────────────────────────────────────
$requeteDept = $pdo->prepare('SELECT COUNT(*) FROM FT_DEPARTEMENT WHERE ID_DEPARTEMENT = ?');
$requeteDept->execute([$idDept]);
if ((int) $requeteDept->fetchColumn() === 0) {
    echo json_encode(['success' => false, 'error' => 'Département introuvable']);
    exit;
}

// ── Seuils dérogatoires (optionnel — vide/null = seuil global) ────────
$derogations = [
    'download' => [
        'bonne'    => ($corps['DL_VALEUR_BONNE']    ?? '') !== '' ? (float) $corps['DL_VALEUR_BONNE']    : null,
        'mauvaise' => ($corps['DL_VALEUR_MAUVAISE'] ?? '') !== '' ? (float) $corps['DL_VALEUR_MAUVAISE'] : null,
    ],
    'upload' => [
        'bonne'    => ($corps['UL_VALEUR_BONNE']    ?? '') !== '' ? (float) $corps['UL_VALEUR_BONNE']    : null,
        'mauvaise' => ($corps['UL_VALEUR_MAUVAISE'] ?? '') !== '' ? (float) $corps['UL_VALEUR_MAUVAISE'] : null,
    ],
    'ping' => [
        'bonne'    => ($corps['PING_VALEUR_BONNE']    ?? '') !== '' ? (float) $corps['PING_VALEUR_BONNE']    : null,
        'mauvaise' => ($corps['PING_VALEUR_MAUVAISE'] ?? '') !== '' ? (float) $corps['PING_VALEUR_MAUVAISE'] : null,
    ],
];
$derogRaison = trim($corps['DEROGATION_RAISON'] ?? '');

// ── Vérifier chevauchement CIDR (en excluant le site lui-même) ────────
if ($ip !== '' && $masque !== null) {
    $sites = $pdo->query(
        "SELECT CODE_GX_SITE, NOM_SITE, IP_RESEAU, MASQUE_SITE
         FROM FT_SITE
         WHERE IP_RESEAU IS NOT NULL AND MASQUE_SITE IS NOT NULL
           AND CODE_GX_SITE != " . $pdo->quote($code)
    )->fetchAll(PDO::FETCH_ASSOC);

    $ipLong    = ip2long($ip);
    $bits      = ~((1 << (32 - $masque)) - 1);
    $newReseau = $ipLong & $bits;

    foreach ($sites as $site) {
        $existantLong   = ip2long($site['IP_RESEAU']);
        $existantMasque = (int) $site['MASQUE_SITE'];
        if ($existantLong === false) continue;

        $existantBits   = ~((1 << (32 - $existantMasque)) - 1);
        $existantReseau = $existantLong & $existantBits;

        $chevauchement =
            ($newReseau & $existantBits) === $existantReseau ||
            ($existantReseau & $bits)    === $newReseau;

        if ($chevauchement) {
            echo json_encode([
                'success' => false,
                'error'   => "La plage {$ip}/{$masque} chevauche le site {$site['CODE_GX_SITE']} ({$site['NOM_SITE']}) — {$site['IP_RESEAU']}/{$site['MASQUE_SITE']}",
                'conflit' => [
                    'CODE_GX_SITE' => $site['CODE_GX_SITE'],
                    'NOM_SITE'     => $site['NOM_SITE'],
                    'IP_RESEAU'    => $site['IP_RESEAU'],
                    'MASQUE_SITE'  => $site['MASQUE_SITE'],
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

$ipSpeciale = ($ip !== '' && $masque !== null) ? 0 : 1;

// ── Mise à jour ───────────────────────────────────────────────────────
$pdo->prepare("
    UPDATE FT_SITE SET
        NOM_SITE       = ?,
        IP_RESEAU      = NULLIF(?, ''),
        MASQUE_SITE    = ?,
        ADRESSE        = NULLIF(?, ''),
        CODE_POSTAL    = NULLIF(?, ''),
        VILLE          = NULLIF(?, ''),
        LATITUDE       = ?,
        LONGITUDE      = ?,
        IP_SPECIALE    = ?,
        ID_DEPARTEMENT = ?
    WHERE CODE_GX_SITE = ?
")->execute([
    $nom, $ip, $masque ?: null, $adresse,
    $cp, $ville, $lat, $lng, $ipSpeciale, $idDept,
    $code,
]);

// Invalider le cache CIDR
(new CacheService())->invalider('sites_cidr');

// ── Seuils dérogatoires ────────────────────────────────────────────────
(new SeuilService($pdo))->enregistrerDerogation(
    codeGxSite: $code,
    derogations: $derogations,
    raison:      $derogRaison ?: null,
    idCompte:    (int) $_SESSION['id_compte']
);

// ── Audit (diff automatique avant/après) ──────────────────────────────
$apres = [
    'NOM_SITE'       => $nom,
    'IP_RESEAU'      => $ip ?: null,
    'MASQUE_SITE'    => $masque,
    'ADRESSE'        => $adresse ?: null,
    'CODE_POSTAL'    => $cp ?: null,
    'VILLE'          => $ville ?: null,
    'LATITUDE'       => $lat,
    'LONGITUDE'      => $lng,
    'IP_SPECIALE'    => $ipSpeciale,
    'ID_DEPARTEMENT' => $idDept,
];

// Restreindre $avant aux mêmes clés pour le diff
$avantDiff = array_intersect_key($avant, $apres);

(new AuditService($pdo))->enregistrer(
    action:   'MODIFICATION',
    idCompte: (int) $_SESSION['id_compte'],
    codeGx:   $code,
    nomSite:  $nom,
    ipAction: $_SERVER['REMOTE_ADDR'] ?? '',
    avant:    $avantDiff,
    apres:    $apres
);

echo json_encode([
    'success' => true,
    'message' => "Site $code mis à jour avec succès.",
], JSON_UNESCAPED_UNICODE);