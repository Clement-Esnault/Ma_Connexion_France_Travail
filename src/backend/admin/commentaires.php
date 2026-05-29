<?php
// Liste paginée des commentaires utilisateurs avec filtres.
// Accessible aux techniciens et administrateurs connectés.
// Filtres GET : site, region, date_debut, date_fin, contenu.
// Pagination : 30 commentaires par page.

require_once __DIR__ . '/../../frontend/includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

require_once __DIR__ . '/../config.php';

$messageOk = '';
$messageErreur   = '';

// ── Construction des filtres dynamiques ──────────────────────────────
$fSite       = trim($_GET['site']       ?? '');
$fRegion     = trim($_GET['region']     ?? '');
$fDateDebut = trim($_GET['date_debut'] ?? '');
$fDateFin   = trim($_GET['date_fin']   ?? '');
$f_contenu    = trim($_GET['contenu']    ?? '');

$where  = [];
$params = [];

if ($fSite) {
    $where[]  = "(s.CODE_GX_SITE LIKE ? OR s.NOM_SITE LIKE ?)";
    $params[] = "%$fSite%";
    $params[] = "%$fSite%";
}
if ($fRegion) {
    $where[]  = "r.NOM_REGION LIKE ?";
    $params[] = "%$fRegion%";
}
if ($fDateDebut) {
    $where[]  = "c.DATE_COMMENTAIRE >= ?";
    $params[] = $fDateDebut . ' 00:00:00';
}
if ($fDateFin) {
    $where[]  = "c.DATE_COMMENTAIRE <= ?";
    $params[] = $fDateFin . ' 23:59:59';
}
if ($f_contenu) {
    $where[]  = "c.CONTENU_COMMENTAIRE LIKE ?";
    $params[] = "%$f_contenu%";
}

$clauseSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Comptage total pour la pagination ────────────────────────────────
$page       = max(1, intval($_GET['page'] ?? 1));
$parPage    = 30;
$decalage     = ($page - 1) * $parPage;

$requeteTotal = $pdo->prepare("
    SELECT COUNT(*)
    FROM FT_COMMENTAIRES c
    JOIN FT_LOGS        l ON c.ID_LOGS        = l.ID_LOGS
    JOIN FT_SITE        s ON l.CODE_GX_SITE   = s.CODE_GX_SITE
    JOIN FT_DEPARTEMENT d ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
    JOIN FT_REGION      r ON d.ID_REGION      = r.ID_REGION
    $clauseSQL
");
$requeteTotal->execute($params);
$total      = (int)$requeteTotal->fetchColumn();
$totalPages = max(1, ceil($total / $parPage));

// ── Commentaires paginés avec métriques du test associé ──────────────
$stmt = $pdo->prepare("
    SELECT
        c.ID_COMMENTAIRES,
        DATE_FORMAT(c.DATE_COMMENTAIRE, '%d/%m/%Y %H:%i') AS DATE_COMMENTAIRE,
        c.CONTENU_COMMENTAIRE,
        l.ID_LOGS,
        DATE_FORMAT(l.DATE_LOGS,        '%d/%m/%Y %H:%i') AS DATE_LOGS,
        l.PING_LOGS,
        l.DOWNLOAD_LOGS,
        l.UPLOAD_LOGS,
        l.IP_CLIENT,
        s.CODE_GX_SITE,
        s.NOM_SITE,
        r.NOM_REGION
    FROM FT_COMMENTAIRES c
    JOIN FT_LOGS        l ON c.ID_LOGS        = l.ID_LOGS
    JOIN FT_SITE        s ON l.CODE_GX_SITE   = s.CODE_GX_SITE
    JOIN FT_DEPARTEMENT d ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
    JOIN FT_REGION      r ON d.ID_REGION      = r.ID_REGION
    $clauseSQL
    ORDER BY c.DATE_COMMENTAIRE DESC
    LIMIT ? OFFSET ?
");
foreach ($params as $i => $val) { $stmt->bindValue($i + 1, $val); }
$stmt->bindValue(count($params) + 1, $parPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $decalage,  PDO::PARAM_INT);
$stmt->execute();
$commentaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Conservation des filtres actifs dans les liens de pagination ──────
$getParams = array_filter([
    'site'       => $fSite,
    'region'     => $fRegion,
    'date_debut' => $fDateDebut,
    'date_fin'   => $fDateFin,
    'contenu'    => $f_contenu,
]);
$chaineUrl = $getParams ? '&' . http_build_query($getParams) : '';