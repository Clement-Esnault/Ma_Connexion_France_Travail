<?php
// Changement de mot de passe du compte connecté (technicien ou admin).
// Le mot de passe actuel est requis pour confirmer l'identité.
// Réponse JSON — appelé en POST depuis frontend/profil.php via profil.js.

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée.']); exit;
}

verifyCsrf();

$mdpActuel  = $_POST['mdp_actuel']  ?? '';
$mdpNouveau = $_POST['mdp_nouveau'] ?? '';
$mdpConfirm = $_POST['mdp_confirm'] ?? '';
$idCompte   = intval($_SESSION['id_compte'] ?? 0);

// ── Validation ────────────────────────────────────────────────────────
if ($mdpActuel === '' || $mdpNouveau === '' || $mdpConfirm === '') {
    echo json_encode(['error' => 'Tous les champs sont obligatoires.']); exit;
}
if ($mdpNouveau !== $mdpConfirm) {
    echo json_encode(['error' => 'Le nouveau mot de passe et sa confirmation ne correspondent pas.']); exit;
}
if (strlen($mdpNouveau) < 8) {
    echo json_encode(['error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.']); exit;
}

// ── Vérification du mot de passe actuel ───────────────────────────────
$stmt = $pdo->prepare('SELECT MDP_COMPTE FROM FT_COMPTES WHERE ID_COMPTE = ?');
$stmt->execute([$idCompte]);
$compte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compte || !password_verify($mdpActuel, $compte['MDP_COMPTE'])) {
    echo json_encode(['error' => 'Mot de passe actuel incorrect.']); exit;
}

// ── Mise à jour ───────────────────────────────────────────────────────
$pdo->prepare('UPDATE FT_COMPTES SET MDP_COMPTE = ? WHERE ID_COMPTE = ?')
    ->execute([password_hash($mdpNouveau, PASSWORD_BCRYPT), $idCompte]);

echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès.']);