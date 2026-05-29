<?php
require_once __DIR__ . '/../../backend/includes/auth.php';
requireAdmin();


require_once __DIR__ . '/../../backend/admin/comptes.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Gestion des techniciens'; require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/statistique.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="../css/comptes.css?v=<?= APP_VERSION ?>">
</head>
<body>
    
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="page">

    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

    <?php if ($messageOk): ?>
        <div class="msg-success">✓ <?= htmlspecialchars($messageOk) ?></div>
    <?php endif; ?>
    <?php if ($messageErreur): ?>
        <div class="msg-error">✗ <?= htmlspecialchars($messageErreur) ?></div>
    <?php endif; ?>

    <!-- ── Créer un technicien ── -->
    <div class="chart-card" style="margin-bottom:1.5rem;">
        <div class="chart-header">
            <div>
                <div class="chart-title">Nouveau technicien</div>
                <div class="chart-subtitle">Créer un compte technicien</div>
            </div>
        </div>
        <form method="POST" class="compte-form">
            <input type="hidden" name="action" value="create">
            <?= csrfField() ?>
            <div class="form-row">
                <input type="text"     name="alias" placeholder="Identifiant" required>
                <input type="password" name="mdp"   placeholder="Mot de passe" required>
                <button type="submit" class="btn-filter">Créer</button>
            </div>
        </form>
    </div>

    <!-- ── Liste des techniciens ── -->
    <div class="chart-card">
        <div class="chart-header">
            <div>
                <div class="chart-title">Techniciens</div>
                <div class="chart-subtitle"><?= count($techniciens) ?> compte(s)</div>
            </div>
        </div>

        <?php if (!$techniciens): ?>
            <div class="empty">Aucun technicien enregistré.</div>
        <?php else: ?>
        <table class="comptes-table">
            <thead>
                <tr>
                    <th>Identifiant</th>
                    <th>Nouveau mot de passe</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($techniciens as $t): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['ALIAS_COMPTE']) ?></strong></td>
                    <td>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id"     value="<?= $t['ID_COMPTE'] ?>">
                            <?= csrfField() ?>
                            <div class="form-row">
                                <input type="password" name="mdp" placeholder="Nouveau mot de passe" required>
                                <button type="submit" class="btn-filter btn-sm">Modifier</button>
                            </div>
                        </form>
                    </td>
                    <td>
                        <form method="POST" class="inline-form"
                              onsubmit="return confirm('Supprimer « <?= htmlspecialchars($t['ALIAS_COMPTE']) ?> » ?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id"     value="<?= $t['ID_COMPTE'] ?>">
                            <?= csrfField() ?>
                            <button type="submit" class="btn-delete">Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>