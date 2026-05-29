<?php
// Traitement du formulaire de connexion (POST uniquement).
// Vérifie les identifiants, ouvre la session et journalise la tentative dans FT_LOGS_CONNEXION.
// Redirige vers la page d'origine si une session a expiré, sinon vers login.php.
// Note : session_start() est déjà appelé par session.php inclus dans login.php avant ce fichier.

require_once __DIR__ . '/../config.php';

// Rejeter toute requête non-POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../login.php');
    exit;
}

$utilisateur = trim($_POST['alias'] ?? '');
$pass        = trim($_POST['mdp']   ?? '');

// Champs obligatoires
if ($utilisateur === '' || $pass === '') {
    header('Location: ../../login.php?error=1');
    exit;
}

// ── Rate limiting — bloquer après 5 échecs en 10 minutes pour cette IP ──
$ip = $_SERVER['REMOTE_ADDR'];
$stmtCheck = $pdo->prepare("
    SELECT COUNT(*) FROM FT_LOGS_CONNEXION
    WHERE IP_CONNEXION = ?
      AND SUCCES = 0
      AND DATE_CO >= NOW() - INTERVAL 10 MINUTE
");
$stmtCheck->execute([$ip]);
if ((int)$stmtCheck->fetchColumn() >= 5) {
    header('Location: ../../login.php?error=locked');
    exit;
}

// Recherche du compte par alias
$stmt = $pdo->prepare("SELECT * FROM FT_COMPTES WHERE ALIAS_COMPTE = ? LIMIT 1");
$stmt->execute([$utilisateur]);
$compte = $stmt->fetch();

if (!$compte || !password_verify($pass, $compte['MDP_COMPTE'])) {
    // Log échec — uniquement si l'alias existe (FK obligatoire sur ID_COMPTE)
    if ($compte) {
        $stmt = $pdo->prepare("
            INSERT INTO FT_LOGS_CONNEXION (ID_COMPTE, TYPE_ACCES, IP_CONNEXION, SUCCES)
            VALUES (?, 'inconnu', ?, 0)
        ");
        $stmt->execute([$compte['ID_COMPTE'], $_SERVER['REMOTE_ADDR']]);
    }
    header('Location: ../../login.php?error=1');
    exit;
}

// Regenerer l'ID de session apres authentification (prevention fixation de session)
session_regenerate_id(true);

// Ouverture de session
$_SESSION['logged_in']     = true;
$_SESSION['id_compte']     = $compte['ID_COMPTE'];
$_SESSION['alias']         = $compte['ALIAS_COMPTE'];
$_SESSION['is_admin']      = (bool)$compte['IS_ADMIN'];
$_SESSION['last_activity'] = time();

// Log succès avec le type d'accès (admin ou tech)
$stmt = $pdo->prepare("
    INSERT INTO FT_LOGS_CONNEXION (ID_COMPTE, TYPE_ACCES, IP_CONNEXION, SUCCES)
    VALUES (?, ?, ?, 1)
");
$stmt->execute([
    $compte['ID_COMPTE'],
    $_SESSION['is_admin'] ? 'admin' : 'tech',
    $_SERVER['REMOTE_ADDR']
]);

// Rediriger vers la page d'origine si l'utilisateur a ete deconnecte automatiquement
$redirect = $_SESSION['redirect_after_login'] ?? null;
unset($_SESSION['redirect_after_login']);

if ($redirect && str_starts_with($redirect, '/')) {
    header('Location: ' . $redirect);
} else {
    header('Location: ../../login.php');
}
exit;