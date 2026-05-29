<?php
require_once __DIR__ . '/../backend/includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Mon profil'; require_once __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="css/profil.css?v=<?= APP_VERSION ?>">
</head>
<body>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page">
    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

    <!-- ── Infos compte ── -->
    <div class="profil-card">
        <h2>Changer mon mot de passe</h2>

        <div class="profil-info">
            Connecté en tant que <strong><?= htmlspecialchars($_SESSION['alias'] ?? '') ?></strong>
            <?= ($_SESSION['is_admin'] ?? false) ? '· <span style="color:var(--ft-blue2)">Administrateur</span>' : '· Technicien' ?>
        </div>

        <form id="profil-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="mdp_actuel">Mot de passe actuel</label>
                <input type="password" id="mdp_actuel" name="mdp_actuel" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="mdp_nouveau">Nouveau mot de passe <span style="color:#888;font-weight:400">(8 caractères min.)</span></label>
                <input type="password" id="mdp_nouveau" name="mdp_nouveau" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="mdp_confirm">Confirmer le nouveau mot de passe</label>
                <input type="password" id="mdp_confirm" name="mdp_confirm" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn-submit">Modifier le mot de passe</button>
        </form>

        <div id="profil-notice"></div>
    </div>

    <!-- ── Mon historique de tests ── -->
    <div class="profil-card" style="margin-top:1rem">
        <h2>Mon historique de tests</h2>
        <p style="font-size:13px; color:var(--ft-muted); margin-bottom:1rem;">
            Consultez les tests de débit effectués depuis votre poste.
        </p>
        <a href="mon_historique.php"
           class="btn-save"
           style="text-decoration:none; display:inline-block;">
            📈 Voir mon historique de tests
        </a>
    </div>

    <!-- ── Modifications de sites ── -->
    <div class="profil-card" style="margin-top:1rem">
        <h2>Mes modifications de sites</h2>
        <p style="font-size:13px; color:var(--ft-muted); margin-bottom:1rem;">
            Consultez l'historique de vos ajouts, modifications et suppressions de sites.
        </p>
        <a href="admin/audit.php?id_compte=<?= (int) $_SESSION['id_compte'] ?>"
           class="btn-save"
           style="text-decoration:none; display:inline-block;">
            📋 Voir mes modifications de sites
        </a>
    </div>
</div>

<script src="js/profil.js?v=<?= APP_VERSION ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>