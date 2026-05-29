<?php
/**
 * difference.php — Comparaison modes rapide / précis
 *
 * Affiche les écarts entre le test rapide (~4s) et le test précis (~20s)
 * pour chaque session. Les seuils d'écart acceptable sont différenciés
 * par métrique (ping / download / upload) depuis v1.9.1.
 */
require_once __DIR__ . '/../backend/includes/auth.php';
requireLogin();

$estAdmin      = $_SESSION['is_admin'] ?? false;
$messageOk     = '';
$messageErreur = '';

// ── Mise à jour des seuils (admin uniquement) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $estAdmin) {
    verifyCsrf();
    try {
        $requeteMaj = $pdo->prepare(
            "UPDATE FT_SEUILS SET VALEUR_BONNE = ?, VALEUR_MAUVAISE = ? WHERE NOM_SEUIL = ?"
        );
        foreach (['difference_ping', 'difference_download', 'difference_upload'] as $cle) {
            $valeur = max(1.0, min(100.0, (float) ($_POST[$cle] ?? 20)));
            $requeteMaj->execute([$valeur, $valeur, $cle]);
        }
        $messageOk = 'Seuils mis à jour avec succès.';
    } catch (PDOException $e) {
        $messageErreur = 'Erreur : ' . $e->getMessage();
    }
}

// ── Chargement des seuils courants ────────────────────────────────────
try {
    $lignesSeuils = $pdo->query("
        SELECT NOM_SEUIL, VALEUR_BONNE FROM FT_SEUILS
        WHERE NOM_SEUIL IN ('difference_ping', 'difference_download', 'difference_upload', 'difference_seuil')
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $seuilGlobal   = isset($lignesSeuils['difference_seuil']) ? (float) $lignesSeuils['difference_seuil'] : 20.0;
    $seuilPing     = isset($lignesSeuils['difference_ping'])     ? (float) $lignesSeuils['difference_ping']     : $seuilGlobal;
    $seuilDownload = isset($lignesSeuils['difference_download']) ? (float) $lignesSeuils['difference_download'] : $seuilGlobal;
    $seuilUpload   = isset($lignesSeuils['difference_upload'])   ? (float) $lignesSeuils['difference_upload']   : $seuilGlobal;
} catch (PDOException $e) {
    $seuilPing = 15.0; $seuilDownload = 20.0; $seuilUpload = 30.0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Comparaison modes'; require_once __DIR__ . '/includes/head.php'; ?>
    <link rel="stylesheet" href="css/difference.css?v=<?= APP_VERSION ?>">
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="page">

    <h2 class="page-title">📊 Comparaison rapide / précis</h2>
    <p class="page-desc">
        Compare les résultats du test rapide (~4s) et du test précis (~20s) effectués lors de la même session.
        Un &eacute;cart sup&eacute;rieur au seuil signale que le test rapide n'est pas repr&eacute;sentatif de la connexion r&eacute;elle.<span class="ft-aide" data-aide="Le test rapide (~4s) mesure sur une dur&eacute;e courte et peut &ecirc;tre biais&eacute; par le slow-start TCP. Le test pr&eacute;cis (~20s) est la r&eacute;f&eacute;rence fiable. Un grand &eacute;cart entre les deux sugg&egrave;re une connexion instable ou un r&eacute;seau congestionn&eacute;.">?</span>
    </p>

    <!-- ── Seuils différenciés ── -->
    <?php if ($estAdmin): ?>
    <div class="seuil-admin-box">
        <div class="seuil-admin-titre">&#9881; Seuils d'&eacute;cart acceptables par m&eacute;trique<span class="ft-aide" data-aide="Si l'&eacute;cart entre le test rapide et le test pr&eacute;cis d&eacute;passe ce seuil, la session est marqu&eacute;e KO. Ex: seuil ping 15% = un &eacute;cart de plus de 15% entre ping rapide et ping pr&eacute;cis = KO.">?</span></div>
        <?php if ($messageOk): ?>
            <div class="msg-success">✓ <?= htmlspecialchars($messageOk) ?></div>
        <?php endif; ?>
        <?php if ($messageErreur): ?>
            <div class="msg-error">✗ <?= htmlspecialchars($messageErreur) ?></div>
        <?php endif; ?>
        <form method="POST" class="seuil-form seuil-form--trois-colonnes">
            <?= csrfField() ?>
            <label class="seuil-form-label">
                Ping
                <div class="seuil-form-input-group">
                    <input type="number" name="difference_ping"
                           value="<?= $seuilPing ?>" min="1" max="100" step="1" required>
                    <span class="seuil-form-unite">%</span>
                </div>
                <span class="seuil-form-hint">Ping plus stable — seuil recommandé : 15 %</span>
            </label>
            <label class="seuil-form-label">
                Téléchargement
                <div class="seuil-form-input-group">
                    <input type="number" name="difference_download"
                           value="<?= $seuilDownload ?>" min="1" max="100" step="1" required>
                    <span class="seuil-form-unite">%</span>
                </div>
                <span class="seuil-form-hint">Seuil recommandé : 20 %</span>
            </label>
            <label class="seuil-form-label">
                Envoi
                <div class="seuil-form-input-group">
                    <input type="number" name="difference_upload"
                           value="<?= $seuilUpload ?>" min="1" max="100" step="1" required>
                    <span class="seuil-form-unite">%</span>
                </div>
                <span class="seuil-form-hint">Upload variable sur WAN — seuil recommandé : 30 %</span>
            </label>
            <button type="submit" class="btn-seuil-save">Enregistrer</button>
        </form>
        <p class="seuil-form-hint" style="margin-top:.5rem">
            Valeurs stockées dans <code>FT_SEUILS</code> — s'appliquent immédiatement à tous les techniciens.
        </p>
    </div>
    <?php else: ?>
    <div class="seuil-info-box">
        Seuils d'écart acceptables :
        <strong>Ping <?= $seuilPing ?>&nbsp;%</strong> —
        <strong>Téléchargement <?= $seuilDownload ?>&nbsp;%</strong> —
        <strong>Envoi <?= $seuilUpload ?>&nbsp;%</strong>
        <span class="seuil-info-hint">(modifiables par un administrateur)</span>
    </div>
    <?php endif; ?>

    <!-- ── Contrôles ── -->
    <div class="controles-bar">
        <label class="controle-label">
            Période
            <select id="jours-input">
                <option value="0">Toute l'histoire</option>
                <option value="1">Dernier jour</option>
                <option value="7">7 derniers jours</option>
                <option value="30" selected>30 derniers jours</option>
                <option value="90">90 derniers jours</option>
                <option value="365">1 an</option>
            </select>
        </label>
        <label class="controle-label">
            Sessions (max)
            <input type="number" id="limite-input" value="100" min="10" max="2000" step="10">
        </label>
        <button id="btn-charger" type="button">Analyser</button>
    </div>

    <!-- ── Spinner / erreur ── -->
    <div id="spinner-box" class="spinner-box hidden"><div class="spinner"></div><span>Chargement…</span></div>
    <div id="erreur-box"  class="erreur-box hidden"></div>

    <!-- ── Synthèse ── -->
    <div id="synthese-box" class="hidden">
        <h3 class="section-titre-page">Synthèse</h3>
        <p class="synthese-sous-titre">
            Seuils appliqués :
            Ping <strong id="seuil-ping-affiche"></strong>&nbsp;% —
            Téléchargement <strong id="seuil-dl-affiche"></strong>&nbsp;% —
            Envoi <strong id="seuil-ul-affiche"></strong>&nbsp;%
        </p>
        <p class="synthese-sous-titre">
            Nombre de sessions où l'écart entre le test rapide et le test précis est
            inférieur&nbsp;(✅&nbsp;OK) ou supérieur&nbsp;(❌&nbsp;Pas OK) au seuil.
        </p>
        <div class="synthese-wrapper">
            <table class="tableau-synthese">
                <thead>
                    <tr><th></th><th>Ping</th><th>⬇ Téléchargement</th><th>⬆ Envoi</th></tr>
                </thead>
                <tbody>
                    <tr class="ligne-total"><td class="ligne-label">Total sessions</td><td id="s-ping-total"></td><td id="s-dl-total"></td><td id="s-ul-total"></td></tr>
                    <tr class="ligne-ok">   <td class="ligne-label">✅&nbsp;OK</td>      <td id="s-ping-ok"></td>   <td id="s-dl-ok"></td>   <td id="s-ul-ok"></td></tr>
                    <tr class="ligne-ko">   <td class="ligne-label">❌&nbsp;Pas OK</td>  <td id="s-ping-ko"></td>   <td id="s-dl-ko"></td>   <td id="s-ul-ko"></td></tr>
                    <tr class="ligne-pct">  <td class="ligne-label">% OK</td>            <td id="s-ping-pct"></td>  <td id="s-dl-pct"></td>  <td id="s-ul-pct"></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Détail ── -->
    <div id="detail-box" class="hidden">
        <div class="detail-header">
            <div>
                <h3 class="detail-titre">Détail par session</h3>
                <p class="detail-sous-titre">
                    Valeur rapide (orange) → précise (bleue) et écart.
                    ✓&nbsp;= sous le seuil de la métrique &nbsp;|&nbsp; ✗&nbsp;= au-dessus.
                </p>
            </div>
            <button id="btn-export-detail" class="btn-export-csv" type="button">⬇ Export CSV</button>
        </div>

        <!-- Filtres ── -->
        <div class="detail-filtres">
            <div class="detail-filtre-groupe">
                <label for="filtre-site">Site<span class="ft-aide" data-aide="Filtre par code GX ou nom de site. Laissez vide pour voir tous les sites.">?</span></label>
                <input type="text" id="filtre-site" placeholder="Filtrer par code site…" autocomplete="off">
            </div>
            <label class="detail-filtre-ko" data-aide="N'affiche que les sessions o&ugrave; au moins une m&eacute;trique pr&eacute;sente un &eacute;cart anormal entre le test rapide et le test pr&eacute;cis.">
                <input type="checkbox" id="filtre-ko">
                Afficher seulement les sessions avec au moins un KO
            </label>
            <span id="detail-compteur" class="detail-compteur"></span>
        </div>

        <div id="detail-pagination-top" class="detail-pagination"></div>

        <div class="detail-wrapper">
            <table class="detail-table">
                <colgroup>
                    <col style="width:13%">
                    <col style="width:15%">
                    <col style="width:24%">
                    <col style="width:24%">
                    <col style="width:24%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Date</th>
                        <th data-sort="ping_ecart" onclick="trierPar('ping_ecart')" class="sortable">
                            Ping (ms) <span class="seuil-col" id="col-seuil-ping"></span>
                        </th>
                        <th data-sort="dl_ecart" onclick="trierPar('dl_ecart')" class="sortable">
                            ⬇ Téléchargement <span class="seuil-col" id="col-seuil-dl"></span>
                        </th>
                        <th data-sort="ul_ecart" onclick="trierPar('ul_ecart')" class="sortable">
                            ⬆ Envoi <span class="seuil-col" id="col-seuil-ul"></span>
                        </th>
                    </tr>
                </thead>
                <tbody id="detail-tbody"></tbody>
            </table>
        </div>

        <div id="detail-pagination-bot" class="detail-pagination detail-pagination--bot"></div>
    </div>

</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="js/difference.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>