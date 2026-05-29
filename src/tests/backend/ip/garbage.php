<?php
// Endpoint de mesure du download — envoie des données aléatoires au client.
// speedtest.js mesure le débit en calculant la quantité reçue par unité de temps.
// Les données sont aléatoires pour éviter la compression HTTP (qui fausserait la mesure).

// Désactive la mise en cache navigateur
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/octet-stream');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Taille configurable via ?size=XX (en MB), entre 1 et 100, défaut 10
$size = isset($_GET['size']) ? intval($_GET['size']) : 10;
$size = max(1, min($size, 100));

// Envoi par chunks de 1MB — arrêt immédiat si le client a fermé la connexion
$bloc = 1024 * 1024;
for ($i = 0; $i < $size; $i++) {
    if (connection_aborted()) break;
    echo random_bytes($bloc);
    flush();
}