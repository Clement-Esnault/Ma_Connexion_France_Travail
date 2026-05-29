<?php
/**
 * get_seuils.php
 *
 * Retourne les seuils de qualité en JSON, indexés par métrique.
 * Utilisé par index.js (verdicts) et logs.js (couleurs des cellules).
 *
 * Format : { "ping": { "bon": 50, "mauvais": 100 }, "download": {...}, "upload": {...} }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/SeuilService.php';

header('Content-Type: application/json');

$service = new SeuilService($pdo);
echo json_encode($service->charger());