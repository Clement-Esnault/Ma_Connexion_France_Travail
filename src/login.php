<?php
require_once __DIR__ . '/frontend/includes/session.php';
require_once __DIR__ . '/backend/config.php';

if (isset($_GET['logout'])) {
    require_once __DIR__ . '/backend/admin/logout.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/backend/admin/login_unified.php';
}

$errorParam = $_GET['error'] ?? '';
$messageErreur = match($errorParam) {
    '1'              => 'Identifiants incorrects.',
    'locked'         => 'Trop de tentatives échouées. Réessayez dans 10 minutes.',
    'session_expired'=> 'Votre session a expiré. Veuillez vous reconnecter.',
    default          => ''
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Connexion'; require_once __DIR__ . '/frontend/includes/head.php'; ?>
    <link rel="stylesheet" href="frontend/css/login.css">
</head>
<body>

<?php require_once __DIR__ . '/frontend/includes/header.php'; ?>
<div class="page">

<?php if (isset($_GET['disconnected'])): ?>
    <div style="background:#d4edda; color:#1a7a3c; padding:10px 20px;
                border-radius:8px; font-size:14px; margin-bottom:1rem; display:inline-block;">
        ✓ Vous avez été déconnecté avec succès.
    </div>
<?php endif; ?>

<?php if ($estConnecte): ?>

<div style="text-align:center; padding:2rem 1rem 1rem; color:var(--ft-muted); font-size:14px;">
    Bienvenue, <strong><?= htmlspecialchars($_SESSION['alias']) ?></strong>.
    Utilisez la navigation ci-dessus pour accéder aux outils.
</div>



<?php else: ?>

    <div class="login-card">
        <div class="card-label">Connexion</div>
        <form method="POST" action="login.php">
            <input type="text"     name="alias" placeholder="Identifiant"  required>
            <input type="password" name="mdp"   placeholder="Mot de passe" required>
            <button type="submit" class="btn">Connexion</button>
        </form>
        <?php if ($messageErreur): ?>
            <div class="error-msg"><?= htmlspecialchars($messageErreur) ?></div>
        <?php endif; ?>
    </div>

<?php endif; ?>


</div><!-- /page -->
</body>
<?php require_once __DIR__ . '/frontend/includes/footer.php'; ?>
</html>