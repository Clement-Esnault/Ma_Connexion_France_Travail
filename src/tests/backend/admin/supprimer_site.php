<?php
/**
 * backend/admin/supprimer_site.php
 *
 * Supprime un site France Travail et ses logs associés (cascade).
 * Accessible aux admins uniquement.
 *
 * ── Corps JSON POST ───────────────────────────────────────────────────
 *   CODE_GX_SITE : string  obligatoire
 *
 * ── Réponse JSON ─────────────────────────────────────────────────────
 *   Succès : { success: true,  nb_logs: int }
 *   Échec  : { success: false, error: "..." }
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/CacheService.php';
require_once __DIR__ . '/../services/AuditService.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$corps = json_decode(file_get_contents('php://input'), true) ?? [];
$code  = strtoupper(trim($corps['CODE_GX_SITE'] ?? ''));

if ($code === '') {
    echo json_encode(['success' => false, 'error' => 'CODE_GX_SITE manquant']);
    exit;
}

// ── Vérifier existence + snapshot complet pour l'audit ────────────────
$requeteVerif = $pdo->prepare(
    'SELECT NOM_SITE, IP_RESEAU, MASQUE_SITE, ADRESSE, CODE_POSTAL,
            VILLE, LATITUDE, LONGITUDE, IP_SPECIALE, ID_DEPARTEMENT
     FROM FT_SITE WHERE CODE_GX_SITE = ?'
);
$requeteVerif->execute([$code]);
$site = $requeteVerif->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    echo json_encode(['success' => false, 'error' => "Site $code introuvable"]);
    exit;
}

// ── Compter les logs à supprimer ──────────────────────────────────────
$requeteCompte = $pdo->prepare('SELECT COUNT(*) FROM FT_LOGS WHERE CODE_GX_SITE = ?');
$requeteCompte->execute([$code]);
$nbLogs = (int) $requeteCompte->fetchColumn();

// ── Suppression en cascade dans une transaction ───────────────────────
try {
    $pdo->beginTransaction();

    // L'audit DOIT être écrit dans la transaction :
    // si la suppression échoue on rollback tout, y compris l'entrée d'audit.
    (new AuditService($pdo))->enregistrer(
        action:   'SUPPRESSION',
        idCompte: (int) $_SESSION['id_compte'],
        codeGx:   $code,
        nomSite:  $site['NOM_SITE'],
        ipAction: $_SERVER['REMOTE_ADDR'] ?? '',
        avant:    array_merge(['CODE_GX_SITE' => $code], $site)
    );

    $pdo->prepare('DELETE FROM FT_LOGS WHERE CODE_GX_SITE = ?')->execute([$code]);
    $pdo->prepare('DELETE FROM FT_SITE WHERE CODE_GX_SITE = ?')->execute([$code]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[Ma Connexion] Erreur suppression site ' . $code . ' : ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur base de données — suppression annulée']);
    exit;
}

// Invalider le cache CIDR
(new CacheService())->invalider('sites_cidr');

echo json_encode([
    'success' => true,
    'nb_logs' => $nbLogs,
    'message' => "Site $code ({$site['NOM_SITE']}) supprimé avec $nbLogs log(s).",
], JSON_UNESCAPED_UNICODE);