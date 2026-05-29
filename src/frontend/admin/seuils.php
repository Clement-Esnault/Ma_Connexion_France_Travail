<?php
require_once __DIR__ . '/../../backend/includes/auth.php';
requireAdmin();


require_once __DIR__ . '/../../backend/admin/seuils.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Gestion des seuils'; require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/statistique.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="../css/seuils.css?v=<?= APP_VERSION ?>">
</head>
<body>
    
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="page">
    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

    <div class="chart-card">
        <div class="chart-header">
            <div>
                <div class="chart-title">Gestion des seuils</div>
                <div class="chart-subtitle">Définir les valeurs bonne / mauvaise pour chaque métrique</div>
            </div>
        </div>

        <?php if ($messageOk): ?>
            <div class="msg-success">✓ <?= htmlspecialchars($messageOk) ?></div>
        <?php endif; ?>
        <?php if ($messageErreur): ?>
            <div class="msg-error">✗ <?= htmlspecialchars($messageErreur) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>
            <table class="seuils-table">
                <thead>
                    <tr>
                        <th>Métrique</th>
                        <th>Valeur bonne <span class="badge-bonne">&#9679; vert</span><span class="ft-aide" data-aide="Seuil de confort : au-del&agrave; (DL/UL) ou en-dessous (Ping) de cette valeur, la m&eacute;trique est consid&eacute;r&eacute;e comme bonne et affich&eacute;e en vert.">?</span></th>
                        <th>Valeur mauvaise <span class="badge-mauvaise">&#9679; rouge</span><span class="ft-aide" data-aide="Seuil d'insuffisance : en-dessous (DL/UL) ou au-del&agrave; (Ping) de cette valeur, la m&eacute;trique est insuffisante et affich&eacute;e en rouge. Entre les deux seuils = orange (fonctionnel).">?</span></th>
                        <th>Unité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $config = [
                        'ping'     => ['label' => 'Ping',     'unite' => 'ms',     'hint_bonne' => 'en dessous = bon',  'hint_mauvaise' => 'au dessus = mauvais'],
                        'download' => ['label' => 'Téléchargement', 'unite' => 'Mbit/s', 'hint_bonne' => 'au dessus = bon',  'hint_mauvaise' => 'en dessous = mauvais'],
                        'upload'   => ['label' => 'Envoi',   'unite' => 'Mbit/s', 'hint_bonne' => 'au dessus = bon',  'hint_mauvaise' => 'en dessous = mauvais'],
                    ];
                    foreach ($config as $key => $cfg):
                        $bonne    = $seuils[$key]['VALEUR_BONNE']    ?? 0;
                        $mauvaise = $seuils[$key]['VALEUR_MAUVAISE'] ?? 0;
                    ?>
                    <tr>
                        <td><strong><?= $cfg['label'] ?></strong> <?php
                            $aides_seuils = [
                                'ping'     => 'Latence en ms. Inverse des autres : plus bas = meilleur. Entre bon et mauvais = fonctionnel (orange).',
                                'download' => 'Débit descendant en Mbit/s. Plus haut = meilleur. Impacte le chargement des apps métier.',
                                'upload'   => 'Débit montant en Mbit/s. Impacte l\'envoi de fichiers et pièces jointes.',
                            ];
                            if (isset($aides_seuils[$key])): ?>
                            <span class="ft-aide" data-aide="<?= htmlspecialchars($aides_seuils[$key]) ?>" tabindex="0" aria-label="Aide">?</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="number" name="bonne_<?= $key ?>"
                                   value="<?= $bonne ?>" min="0" step="0.1">
                            <div class="hint"><?= $cfg['hint_bonne'] ?></div>
                        </td>
                        <td>
                            <input type="number" name="mauvaise_<?= $key ?>"
                                   value="<?= $mauvaise ?>" min="0" step="0.1">
                            <div class="hint"><?= $cfg['hint_mauvaise'] ?></div>
                        </td>
                        <td><?= $cfg['unite'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:1.5rem; display:flex; gap:1rem; align-items:center;">
                <button type="submit" class="btn-filter">Enregistrer</button>
                <span style="font-size:12px; color:var(--ft-muted);">
                    Ces valeurs s'appliquent aux alertes et aux indicateurs de couleur.
                </span>
            </div>
        </form>
    </div>

</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>