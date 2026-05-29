<?php
/**
 * backend/ip/site_info.php
 *
 * Recherche de sites France Travail par code GX, nom, IP réseau,
 * département, région ou interrégion.
 *
 * Retourne les résultats paginés en JSON avec le dernier verdict connu
 * (calculé via SeuilService sur le dernier log du site).
 *
 * GET :
 *   q     string  Terme de recherche (obligatoire, min. 1 caractère)
 *   page  int     Numéro de page (défaut : 1, 50 résultats par page)
 *
 * Réponse JSON :
 *   { total, page, pages, limit, results: [...] }
 *   Chaque résultat inclut : CODE_GX_SITE, NOM_SITE, CODE_POSTAL,
 *   IP_RESEAU, MASQUE_SITE, IP_SPECIALE, NUM_DEPARTEMENT, NOM_DEPARTEMENT,
 *   NOM_REGION, NOM_INTERREGION, nb_logs, dernier_verdict
 *
 * Accès : techniciens et admins connectés.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SeuilService.php';

requireLogin();

// ── Paramètres ────────────────────────────────────────────────────────
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limite   = 50;
$decalage = ($page - 1) * $limite;

if ($q === '') {
    echo json_encode(['total' => 0, 'page' => 1, 'pages' => 0, 'limit' => $limite, 'results' => []]);
    exit;
}

$like = '%' . $q . '%';

// ── Jointures communes ────────────────────────────────────────────────
$jointures = "
    FROM FT_SITE s
    LEFT JOIN FT_DEPARTEMENT  d  ON s.ID_DEPARTEMENT  = d.ID_DEPARTEMENT
    LEFT JOIN FT_REGION       r  ON d.ID_REGION       = r.ID_REGION
    LEFT JOIN FT_INTERREGION  ir ON r.ID_INTERREGION  = ir.ID_INTERREGION
";

// Recherche multi-champs : code, nom, IP, département, région, interrégion
$where = "
    WHERE s.CODE_GX_SITE    LIKE ?
       OR s.NOM_SITE         LIKE ?
       OR s.IP_RESEAU        LIKE ?
       OR d.NOM_DEPARTEMENT  LIKE ?
       OR r.NOM_REGION       LIKE ?
       OR ir.NOM_INTERREGION LIKE ?
";

$bindParams = [$like, $like, $like, $like, $like, $like];

// ── Comptage ──────────────────────────────────────────────────────────
$comptage = $pdo->prepare("SELECT COUNT(*) $jointures $where");
$comptage->execute($bindParams);
$total = (int) $comptage->fetchColumn();

// ── Résultats paginés ─────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        s.CODE_GX_SITE,
        s.NOM_SITE,
        s.CODE_POSTAL,
        s.IP_RESEAU,
        s.MASQUE_SITE,
        s.IP_SPECIALE,
        d.NUM_DEPARTEMENT,
        d.NOM_DEPARTEMENT,
        r.NOM_REGION,
        ir.NOM_INTERREGION,
        (SELECT COUNT(*) FROM FT_LOGS l WHERE l.CODE_GX_SITE = s.CODE_GX_SITE) AS nb_logs,
        (SELECT l2.PING_LOGS     FROM FT_LOGS l2 WHERE l2.CODE_GX_SITE = s.CODE_GX_SITE ORDER BY l2.DATE_LOGS DESC LIMIT 1) AS _ping,
        (SELECT l2.DOWNLOAD_LOGS FROM FT_LOGS l2 WHERE l2.CODE_GX_SITE = s.CODE_GX_SITE ORDER BY l2.DATE_LOGS DESC LIMIT 1) AS _dl,
        (SELECT l2.UPLOAD_LOGS   FROM FT_LOGS l2 WHERE l2.CODE_GX_SITE = s.CODE_GX_SITE ORDER BY l2.DATE_LOGS DESC LIMIT 1) AS _ul
    $jointures $where
    ORDER BY s.CODE_GX_SITE
    LIMIT ? OFFSET ?
");

foreach ($bindParams as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(count($bindParams) + 1, $limite,   PDO::PARAM_INT);
$stmt->bindValue(count($bindParams) + 2, $decalage, PDO::PARAM_INT);
$stmt->execute();

// ── Calcul du dernier verdict via SeuilService ────────────────────────
$seuilService = new SeuilService($pdo);
$seuils       = $seuilService->charger();
$results      = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as &$resultat) {
    if ($resultat['_dl'] !== null) {
        $verdictPing = $seuilService->verdict('ping',     (float) $resultat['_ping'], $seuils);
        $verdictDl   = $seuilService->verdict('download', (float) $resultat['_dl'],   $seuils);
        $verdictUl   = $seuilService->verdict('upload',   (float) $resultat['_ul'],   $seuils);
        $resultat['dernier_verdict'] = in_array('insuffisant', [$verdictPing, $verdictDl, $verdictUl]) ? 'insuffisant'
            : (in_array('fonctionnel', [$verdictPing, $verdictDl, $verdictUl]) ? 'fonctionnel' : 'confort');
            }
            unset($resultat['_ping'], $resultat['_dl'], $resultat['_ul']);
}
unset($resultat);

// ── Réponse JSON ──────────────────────────────────────────────────────
echo json_encode([
    'total'   => $total,
    'page'    => $page,
    'pages'   => (int) ceil($total / $limite),
    'limit'   => $limite,
'results' => $results,
], JSON_UNESCAPED_UNICODE);