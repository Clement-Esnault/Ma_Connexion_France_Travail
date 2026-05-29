<?php
require_once __DIR__ . '/../../backend/includes/auth.php';
requireLogin();

$estAdmin = $_SESSION['is_admin'] ?? false;

require_once __DIR__ . '/../../backend/config.php';
$departements = $pdo->query(
    'SELECT d.ID_DEPARTEMENT, d.NOM_DEPARTEMENT, r.NOM_REGION
     FROM FT_DEPARTEMENT d
     JOIN FT_REGION r ON r.ID_REGION = d.ID_REGION
     ORDER BY r.NOM_REGION, d.NOM_DEPARTEMENT'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Gestion des sites'; require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/modifier_site.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="../css/gestion_sites.css?v=<?= APP_VERSION ?>">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="page">
    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>
    <h2 class="page-title">🏢 Gestion des sites</h2>

    <!-- ── Onglets ── -->
    <div class="onglets">
        <button class="onglet active" data-tab="ajouter" onclick="changerOnglet('ajouter', this)">
            ➕ Ajouter un site
        </button>
        <?php if ($estAdmin): ?>
        <button class="onglet" data-tab="supprimer" onclick="changerOnglet('supprimer', this)">
            🗑 Supprimer un site
        </button>
        <?php endif; ?>
        <?php if ($estAdmin): ?>
        <button class="onglet" data-tab="bulk" onclick="changerOnglet('bulk', this)">
            🗑 Suppression groupée
        </button>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════════════════
         Onglet Ajouter
    ════════════════════════════════════════════════ -->
    <div id="tab-ajouter" class="tab-content">
        <div class="chart-card form-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Nouveau site</div>
                    <div class="chart-subtitle">Les champs marqués * sont obligatoires</div>
                </div>
            </div>

            <div class="info-box">
                Format code GX : <strong>GXnnnnnn</strong> (6 chiffres, ex. GX006400).
                Sans IP/masque, le site sera marqué <strong>spécial</strong>.
            </div>

            <div id="msg-ajouter-ok"    class="msg-ok"    role="status"    aria-live="polite"></div>
            <div id="msg-ajouter-error" class="msg-error" role="alert"     aria-live="assertive"></div>
            <div id="msg-conflit-box"   class="conflit-box hidden" role="alert" aria-live="assertive"></div>

            <div class="field-group">
                <div class="row-2">
                    <label>
                        Code GX *
                        <input type="text" id="aj-code" placeholder="GX006400"
                               maxlength="8" autocomplete="off"
                               oninput="this.value=this.value.toUpperCase()">
                    </label>
                    <label>
                        Département *
                        <select id="aj-dept">
                            <option value="">— Choisir —</option>
                            <?php
                            $regionCourante = null;
                            foreach ($departements as $d):
                                if ($d['NOM_REGION'] !== $regionCourante):
                                    if ($regionCourante !== null) echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars($d['NOM_REGION']) . '">';
                                    $regionCourante = $d['NOM_REGION'];
                                endif;
                            ?>
                                <option value="<?= $d['ID_DEPARTEMENT'] ?>">
                                    <?= htmlspecialchars($d['NOM_DEPARTEMENT']) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($regionCourante !== null) echo '</optgroup>'; ?>
                        </select>
                    </label>
                </div>

                <label>
                    Nom du site *
                    <input type="text" id="aj-nom" placeholder="NANTES CENTRE"
                           maxlength="100" oninput="this.value=this.value.toUpperCase()">
                </label>

                <div class="row-2">
                    <label>
                        IP réseau
                        <input type="text" id="aj-ip" placeholder="10.59.24.0" autocomplete="off">
                    </label>
                    <label>
                        Masque CIDR
                        <input type="number" id="aj-masque" placeholder="24" min="8" max="32">
                    </label>
                </div>

                <label>
                    Adresse
                    <input type="text" id="aj-adresse" placeholder="8 avenue du Petit Clos" maxlength="150">
                </label>

                <div class="row-2">
                    <label>
                        Code postal
                        <input type="text" id="aj-cp" placeholder="44300" maxlength="5">
                    </label>
                    <label>
                        Ville
                        <input type="text" id="aj-ville" placeholder="NANTES"
                               maxlength="100" oninput="this.value=this.value.toUpperCase()">
                    </label>
                </div>

                <div class="row-2">
                    <label>
                        Latitude
                        <input type="number" id="aj-lat" placeholder="47.2341"
                               step="0.000001" min="-90" max="90">
                    </label>
                    <label>
                        Longitude
                        <input type="number" id="aj-lng" placeholder="-1.5786"
                               step="0.000001" min="-180" max="180">
                    </label>
                </div>
            </div>

            <button class="btn-save" id="btn-ajouter" onclick="GS.ajouterSite()">
                Créer le site
            </button>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════
         Onglet Supprimer (admin uniquement)
    ════════════════════════════════════════════════ -->
    <?php if ($estAdmin): ?>
    <div id="tab-supprimer" class="tab-content hidden">
        <div class="chart-card form-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Supprimer un site</div>
                    <div class="chart-subtitle">Recherchez le site — la suppression est irréversible</div>
                </div>
            </div>

            <div class="info-box danger-box">
                ⚠ <strong>Attention</strong> — supprime définitivement le site
                <strong>et l'intégralité de ses mesures</strong>.
            </div>

            <div id="msg-suppr-ok"    class="msg-ok"    role="status"  aria-live="polite"></div>
            <div id="msg-suppr-error" class="msg-error" role="alert"   aria-live="assertive"></div>

            <!-- Barre de recherche -->
            <div class="field-group">
                <label for="suppr-recherche">Rechercher un site</label>
                <div class="search-input-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="suppr-recherche"
                           placeholder="Nom, code GX, IP, département…"
                           oninput="GS.rechercherSite(this.value)"
                           autocomplete="off"
                           aria-label="Rechercher un site à supprimer">
                    <button class="search-clear hidden" id="suppr-clear"
                            onclick="GS.viderRecherche()" title="Effacer">✕</button>
                </div>
            </div>

            <!-- En-tête total -->
            <div id="suppr-resultats-header" class="suppr-resultats-header hidden">
                <span id="suppr-total-label"></span>
            </div>

            <!-- Résultats -->
            <div id="suppr-resultats" class="suppr-resultats hidden" role="list"></div>

            <!-- Pagination -->
            <div id="suppr-pagination" class="suppr-pagination hidden"
                 role="navigation" aria-label="Pagination des résultats"></div>

            <!-- Panneau confirmation -->
            <div id="suppr-confirm-box" class="suppr-confirm hidden"
                 role="dialog" aria-labelledby="suppr-confirm-titre">
                <div class="suppr-confirm-titre" id="suppr-confirm-titre">
                    ⚠ Confirmer la suppression
                </div>
                <div id="suppr-site-card" class="suppr-site-card"></div>
                <div class="suppr-confirm-actions">
                    <button class="btn-annuler"  onclick="GS.annulerSuppression()">← Annuler</button>
                    <button class="btn-supprimer" id="btn-confirmer-suppr"
                            onclick="GS.confirmerSuppression()">🗑 Supprimer définitivement</button>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>

    <!-- ════════════════════════════════════════════════
         Onglet Suppression groupée (admin uniquement)
    ════════════════════════════════════════════════ -->
    <?php if ($estAdmin): ?>
    <div id="tab-bulk" class="tab-content hidden">
        <div class="chart-card form-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Suppression groupée</div>
                    <div class="chart-subtitle">Recherchez et sélectionnez plusieurs sites — la suppression est irréversible</div>
                </div>
            </div>

            <div class="info-box danger-box">
                ⚠ <strong>Attention</strong> — supprime définitivement les sites sélectionnés
                <strong>et l'intégralité de leurs mesures</strong>.
            </div>

            <div id="msg-bulk-ok"    class="msg-ok"    role="status"  aria-live="polite"></div>
            <div id="msg-bulk-error" class="msg-error" role="alert"   aria-live="assertive"></div>

            <!-- Barre de recherche -->
            <div class="field-group">
                <label for="bulk-recherche">Rechercher des sites</label>
                <div class="search-input-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="bulk-recherche"
                           placeholder="Nom, code GX, IP, département…"
                           oninput="GS.bulkRechercher(this.value)"
                           autocomplete="off"
                           aria-label="Rechercher des sites à supprimer en masse">
                    <button class="search-clear hidden" id="bulk-clear"
                            onclick="GS.bulkViderRecherche()" title="Effacer">✕</button>
                </div>
            </div>

            <!-- En-tête résultats + tout cocher -->
            <div id="bulk-resultats-header" class="suppr-resultats-header hidden">
                <span id="bulk-total-label"></span>
                <button class="btn-tout-cocher" id="btn-bulk-tout-cocher"
                        onclick="GS.bulkToutCocher()">☑ Tout sélectionner</button>
            </div>

            <!-- Résultats avec checkboxes -->
            <div id="bulk-resultats" class="suppr-resultats bulk-resultats hidden" role="list"></div>

            <!-- Pagination -->
            <div id="bulk-pagination" class="suppr-pagination hidden"
                 role="navigation" aria-label="Pagination"></div>

            <!-- Récap sélection + confirmation -->
            <div id="bulk-recap" class="bulk-recap hidden">
                <div class="bulk-recap-titre">
                    <span id="bulk-nb-selectionnes">0</span> site(s) sélectionné(s) pour suppression
                </div>
                <div id="bulk-liste-selectionnes" class="bulk-liste-selectionnes"></div>
                <div class="suppr-confirm-actions">
                    <button class="btn-annuler" onclick="GS.bulkDeselectTout()">✕ Tout désélectionner</button>
                    <button class="btn-supprimer" id="btn-bulk-confirmer"
                            onclick="GS.bulkConfirmer()">🗑 Supprimer les sites sélectionnés</button>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>

<script src="../js/gestion_sites.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>