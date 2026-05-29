<?php
/**
 * backend/admin/get_departement.php
 *
 * Retourne la liste de tous les départements pour le select du formulaire.
 * Accessible aux admins et techniciens connectés.
 *
 * Réponse JSON :
 *   [ { ID_DEPARTEMENT, NOM_DEPARTEMENT, NUM_DEPARTEMENT, NOM_REGION }, … ]
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$depts = $pdo->query("
    SELECT d.ID_DEPARTEMENT, d.NOM_DEPARTEMENT, d.NUM_DEPARTEMENT,
           r.NOM_REGION
    FROM FT_DEPARTEMENT d
    LEFT JOIN FT_REGION r ON d.ID_REGION = r.ID_REGION
    ORDER BY d.NUM_DEPARTEMENT
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($depts, JSON_UNESCAPED_UNICODE);