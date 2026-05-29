<?php
// Supprime un log individuel de FT_LOGS.
// Réservé aux administrateurs uniquement (requireAdmin).
// Paramètre POST JSON : id_log (int).

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

// requireAdmin() vérifie is_admin=true — requireLogin() seul ne suffit pas
requireAdmin();

$data   = json_decode(file_get_contents('php://input'), true);
$id_log = intval($corps['id_log'] ?? 0);

if ($id_log <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

$requete = $pdo->prepare('DELETE FROM FT_LOGS WHERE ID_LOGS = ?');
$requete->execute([$id_log]);

// rowCount() = 0 si le log n'existait pas en BDD
echo json_encode(['success' => $requete->rowCount() > 0]);