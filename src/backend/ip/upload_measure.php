<?php
/**
 * backend/ip/upload_measure.php
 *
 * Endpoint de mesure du débit montant (upload).
 * Lit le body entier de la requête POST avant de répondre,
 * ce qui garantit que le timing côté client correspond au transfert réel.
 *
 * Réponse JSON : { bytes: int }
 *
 * Utilisé par speedtest.js v1.9+ (mesurerEnvoi).
 * Pas d'authentification requise — endpoint public comme garbage.php.
 */
// Intranet uniquement — CORS permissif nécessaire pour que le moteur speedtest.js
// puisse poster depuis index.php servi sur la même IP mais via URL différente.
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');

// Lire le body entier par chunks
$bytes  = 0;
$handle = fopen('php://input', 'rb');
if ($handle) {
    while ($chunk = fread($handle, 65536)) {
        $bytes += strlen($chunk);
    }
    fclose($handle);
}

echo json_encode(['bytes' => $bytes]);