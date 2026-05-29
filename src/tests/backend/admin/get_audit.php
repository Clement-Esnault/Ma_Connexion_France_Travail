<?php
/**
 * backend/admin/get_audit.php
 *
 * Retourne les entrées d'audit paginées avec filtres.
 * Accessible aux admins uniquement.
 *
 * ── Paramètres GET ────────────────────────────────────────────────────
 *   id_compte : int    filtre par compte (0 = tous)
 *   action    : string filtre par action ('' = toutes)
 *   jours     : int    période en jours (0 = tout)
 *   site      : string recherche sur CODE_GX_SITE ou NOM_SITE
 *   page      : int    page demandée (défaut 1)
 *   limite    : int    résultats par page (défaut 25, max 100)
 *
 * ── Réponse JSON ─────────────────────────────────────────────────────
 *   { total, page, pages, resultats: [...] }
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// ── Paramètres ────────────────────────────────────────────────────────
$idCompte = (int)    ($_GET['id_compte'] ?? 0);
$action   = trim(    $_GET['action']     ?? '');
$jours    = max(0,   (int) ($_GET['jours']  ?? 30));
$site     = trim(    $_GET['site']       ?? '');
$page     = max(1,   (int) ($_GET['page']   ?? 1));
$limite   = max(10, min(100, (int) ($_GET['limite'] ?? 25)));
$offset   = ($page - 1) * $limite;

// ── Construction de la clause WHERE ───────────────────────────────────
$conditions = [];
$params     = [];

if ($idCompte > 0) {
    $conditions[] = 'a.ID_COMPTE = ?';
    $params[]     = $idCompte;
}

$actionsValides = ['AJOUT', 'MODIFICATION', 'SUPPRESSION'];
if ($action !== '' && in_array($action, $actionsValides, true)) {
    $conditions[] = 'a.ACTION = ?';
    $params[]     = $action;
}

if ($jours > 0) {
    $conditions[] = 'a.DATE_ACTION >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $params[]     = $jours;
}

if ($site !== '') {
    $conditions[] = '(a.ID_SITE LIKE ? OR a.NOM_SITE LIKE ?)';
    $motif        = '%' . $site . '%';
    $params[]     = $motif;
    $params[]     = $motif;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ── Comptage total ────────────────────────────────────────────────────
$requeteTotal = $pdo->prepare(
    "SELECT COUNT(*) FROM FT_AUDIT_SITES a $where"
);
$requeteTotal->execute($params);
$total = (int) $requeteTotal->fetchColumn();
$pages = (int) ceil($total / $limite);

// ── Résultats paginés ─────────────────────────────────────────────────
$paramsPage   = array_merge($params, [$limite, $offset]);
$requeteData = $pdo->prepare("
    SELECT
        a.ID_AUDIT,
        a.DATE_ACTION,
        a.ACTION,
        a.ID_SITE         AS code_gx,
        a.NOM_SITE,
        a.IP_ACTION,
        a.DETAIL,
        c.ALIAS_COMPTE,
        c.IS_ADMIN
    FROM FT_AUDIT_SITES a
    LEFT JOIN FT_COMPTES c ON c.ID_COMPTE = a.ID_COMPTE
    $where
    ORDER BY a.DATE_ACTION DESC
    LIMIT ? OFFSET ?
");
foreach ($params as $i => $val) {
    $requeteData->bindValue($i + 1, $val);
}
$requeteData->bindValue(count($params) + 1, $limite, PDO::PARAM_INT);
$requeteData->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$requeteData->execute();
$lignes = $requeteData->fetchAll(PDO::FETCH_ASSOC);
// ── Formater le détail JSON ───────────────────────────────────────────
foreach ($lignes as &$ligne) {
    if ($ligne['DETAIL'] !== null) {
        $decoded = json_decode($ligne['DETAIL'], true);
        $ligne['detail_parse'] = $decoded ?? null;
    } else {
        $ligne['detail_parse'] = null;
    }
    unset($ligne['DETAIL']); // on envoie uniquement le parsé
}
unset($ligne);

echo json_encode([
    'total'      => $total,
    'page'       => $page,
    'pages'      => $pages,
    'resultats'  => $lignes,
], JSON_UNESCAPED_UNICODE);