<?php
require_once __DIR__ . '/../../backend/includes/auth.php';
requireLogin();
$assetsRoot = '../';
$rootPath   = '../';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Modifier un site'; require_once __DIR__ . '/../includes/head.php'; ?>

    <link rel="stylesheet" href="../css/modifier_site.css?v=<?= APP_VERSION ?>">
</head>
<body>
<script>
    const CODE_GX_SITE = <?= json_encode(trim($_GET['CODE_GX_SITE'] ?? '')) ?>;
</script>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page">
    <a href="../recherche.php" class="lien-retour">← Retour à la recherche</a>

    <div class="chart-card form-card">
        <div class="chart-header">
            <div>
                <div class="chart-title" id="titre-page">Modifier un site</div>
                <div class="chart-subtitle" id="sous-titre">Chargement…</div>
            </div>
            <span id="badge-statut"></span>
        </div>

        <div id="loading" style="padding:2rem; text-align:center; color:#888;">
            <div class="spinner" style="margin:0 auto 8px;"></div>Chargement du site…
        </div>

        <form id="form-site" style="display:none">
            <div class="info-box">
                Le champ <strong>Code GX</strong> est en lecture seule.
                En renseignant une <strong>IP réseau + masque</strong>, le site passe automatiquement
                en statut <strong>Normal</strong> — les tests lui seront rattachés.
            </div>

            <div class="field-group">
                <div class="row-2">
                    <label>Code GX <input type="text" id="f-code" readonly></label>
                    <label>Région  <input type="text" id="f-region" readonly></label>
                </div>
                <label>Nom du site <input type="text" id="f-nom" name="NOM_SITE" placeholder="Nom du site"></label>
            </div>

            <div class="field-group">
                <label>Adresse <input type="text" id="f-adresse" name="ADRESSE" placeholder="Ex : 26 Rue Bailey"></label>
                <div class="row-3">
                    <label>Ville
                        <input type="text" id="f-ville" name="VILLE" placeholder="Ex : Caen">
                    </label>
                    <label>Code postal
                        <input type="text" id="f-cp" name="CODE_POSTAL" placeholder="Ex : 14000" maxlength="5">
                    </label>
                    <label>Département
                        <select id="f-dept" name="ID_DEPARTEMENT">
                            <option value="">Chargement…</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="field-group">
                <div class="row-2">
                    <label>IP réseau
                        <input type="text" id="f-ip" name="IP_RESEAU"
                               placeholder="Ex : 10.30.5.0"
                               pattern="^(\d{1,3}\.){3}\d{1,3}$">
                    </label>
                    <label>Masque CIDR
                        <input type="number" id="f-masque" name="MASQUE_SITE"
                               placeholder="Ex : 24" min="8" max="32">
                    </label>
                </div>
                <div class="row-2">
                    <label>Latitude  <input type="number" id="f-lat" name="LATITUDE"  step="any" placeholder="Ex : 49.2001"></label>
                    <label>Longitude <input type="number" id="f-lng" name="LONGITUDE" step="any" placeholder="Ex : -0.3921"></label>
                </div>
            </div>


            <div class="field-group field-group--seuils">
                <div class="field-group-titre">
                    Seuils dérogatoires
                    <span class="field-group-badge" id="badge-derogation" style="display:none">
                        ⚠ Actif
                    </span>
                </div>
                <p class="field-group-hint">
                    Laisser vide = utiliser les seuils globaux du réseau.
                    À utiliser pour les agences structurellement limitées
                    (connexion rurale, site distant) afin d'éviter les fausses alertes.
                </p>
                <div class="row-3">
                    <div class="seuil-col">
                        <div class="seuil-col-titre">Téléchargement (Mbit/s)</div>
                        <label>Bon au-dessus de
                            <input type="number" id="f-dl-bon" name="DL_VALEUR_BONNE"
                                   step="0.1" min="0" max="1000" placeholder="ex : 6">
                        </label>
                        <label>Mauvais en-dessous de
                            <input type="number" id="f-dl-mauvais" name="DL_VALEUR_MAUVAISE"
                                   step="0.1" min="0" max="1000" placeholder="ex : 2">
                        </label>
                    </div>
                    <div class="seuil-col">
                        <div class="seuil-col-titre">Envoi (Mbit/s)</div>
                        <label>Bon au-dessus de
                            <input type="number" id="f-ul-bon" name="UL_VALEUR_BONNE"
                                   step="0.1" min="0" max="1000" placeholder="ex : 1.5">
                        </label>
                        <label>Mauvais en-dessous de
                            <input type="number" id="f-ul-mauvais" name="UL_VALEUR_MAUVAISE"
                                   step="0.1" min="0" max="1000" placeholder="ex : 0.3">
                        </label>
                    </div>
                    <div class="seuil-col">
                        <div class="seuil-col-titre">Ping (ms)</div>
                        <label>Bon en-dessous de
                            <input type="number" id="f-ping-bon" name="PING_VALEUR_BONNE"
                                   step="1" min="0" max="9999" placeholder="ex : 80">
                        </label>
                        <label>Mauvais au-dessus de
                            <input type="number" id="f-ping-mauvais" name="PING_VALEUR_MAUVAISE"
                                   step="1" min="0" max="9999" placeholder="ex : 200">
                        </label>
                    </div>
                </div>
                <label class="label-raison">
                    Motif de la dérogation
                    <input type="text" id="f-derog-raison" name="DEROGATION_RAISON"
                           maxlength="255" placeholder="ex : Liaison ADSL rurale — max 4 Mbit/s">
                </label>
                <button type="button" class="btn-reset-derogation" id="btn-reset-derog">
                    ✕ Supprimer la dérogation (revenir aux seuils globaux)
                </button>
            </div>

            <button type="submit" class="btn-save" id="btn-save">Enregistrer</button>
            <div class="msg-ok"    id="msg-ok"></div>
            <div class="msg-error" id="msg-error"></div>
        </form>
    </div>
</div>


<script src="../js/modifier_site.js?v=<?= APP_VERSION ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>