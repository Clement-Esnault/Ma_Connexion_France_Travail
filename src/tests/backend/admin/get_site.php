<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/SeuilService.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$code = strtoupper(trim($_GET['CODE_GX_SITE'] ?? ''));
if ($code === '') {
    echo json_encode(['error' => 'CODE_GX_SITE manquant']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.CODE_GX_SITE, s.NOM_SITE, s.IP_RESEAU, s.MASQUE_SITE,
           s.ADRESSE, s.CODE_POSTAL, s.VILLE, s.LATITUDE, s.LONGITUDE,
           s.IP_SPECIALE, s.ID_DEPARTEMENT,
           d.NOM_DEPARTEMENT, d.NUM_DEPARTEMENT,
           r.NOM_REGION, ir.NOM_INTERREGION
    FROM FT_SITE s
    LEFT JOIN FT_DEPARTEMENT d  ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
    LEFT JOIN FT_REGION      r  ON d.ID_REGION      = r.ID_REGION
    LEFT JOIN FT_INTERREGION ir ON r.ID_INTERREGION = ir.ID_INTERREGION
    WHERE s.CODE_GX_SITE = ?
");
$stmt->execute([$code]);
$site = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site) {
    echo json_encode(['error' => "Site $code introuvable"]);
    exit;
}

// Enrichir avec la dérogation de seuils si elle existe
$seuilService = new SeuilService($pdo);
$site['derogation_seuils'] = $seuilService->chargerDerogation($site['CODE_GX_SITE']);

echo json_encode($site, JSON_UNESCAPED_UNICODE);