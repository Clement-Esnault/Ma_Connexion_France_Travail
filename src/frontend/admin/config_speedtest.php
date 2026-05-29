<?php
/**
 * config_speedtest.php — Page d'administration de la configuration du moteur de speedtest.
 *
 * Paramètres v1.9 (ReadableStream durée fixe) :
 *   - Ping     : prechauffage, echantillons, delay
 *   - Download : dureeMsDownload, parallel
 *   - Upload   : dureeMsUpload, parallel, tailleMoBlob
 *
 * Les anciens paramètres (size, series, pause, trim, tailleMo) ont été
 * supprimés en v1.9 — ne plus les afficher ici.
 */

require_once __DIR__ . '/../../backend/includes/auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php $pageTitle = 'Config. speedtest'; require_once __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="../css/seuils.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="../css/config_speedtest.css?v=<?= APP_VERSION ?>">
</head>
<body>
<script>
    // Seule variable PHP nécessaire côté JS — le reste est dans config_speedtest.js
    const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';
</script>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="page">
    <a href="<?= lien_retour() ?>" class="lien-retour">← Retour</a>

    <div class="chart-card">
        <div class="chart-header">
            <div>
                <div class="chart-title">Configuration du moteur de speedtest</div>
                <div class="chart-subtitle">
                    Paramètres appliqués à chaque test de débit (v1.9 — ReadableStream durée fixe)
                    — modifications effectives immédiatement
                </div>
            </div>
            <button class="btn-save" id="btn-save" onclick="sauvegarder()">Enregistrer</button>
        </div>

        <div id="msg-ok"  class="msg-success" style="display:none;"></div>
        <div id="msg-err" class="msg-error"   style="display:none;"></div>

        <div id="loading-config" class="loading-block">
            <div class="spinner"></div> Chargement de la configuration…
        </div>

        <div id="alertes-validation" class="mb-3"></div>

        <div id="config-form" style="display:none;">

            <!-- ── Mode Précis ────────────────────────────────────────── -->
            <div class="mode-section">
                <div class="mode-badge mode-precise">Mode Précis</div>
                <div class="mode-desc">~8 secondes — mesures fiables, ReadableStream durée fixe</div>

                <div class="cfg-group">
                    <div class="cfg-group-title">Ping</div>
                    <table class="seuils-table">
                        <thead><tr><th>Paramètre</th><th>Valeur</th><th>Unité</th><th>Explication</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Requêtes de préchauffage</td>
                                <td><input type="number" id="precise_ping_prechauffage" min="0" max="20" step="1"></td>
                                <td>—</td>
                                <td class="hint">Requêtes ignorées avant la mesure pour stabiliser TCP/DNS</td>
                            </tr>
                            <tr>
                                <td>Nombre de mesures</td>
                                <td><input type="number" id="precise_ping_echantillons" min="1" max="50" step="1"></td>
                                <td>—</td>
                                <td class="hint">Nombre de ping effectués — la médiane est retenue</td>
                            </tr>
                            <tr>
                                <td>Délai entre mesures</td>
                                <td><input type="number" id="precise_ping_delay" min="0" max="500" step="10"></td>
                                <td>ms</td>
                                <td class="hint">Pause entre chaque ping pour éviter la saturation</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cfg-group">
                    <div class="cfg-group-title">Download</div>
                    <table class="seuils-table">
                        <thead><tr><th>Paramètre</th><th>Valeur</th><th>Unité</th><th>Explication</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Durée de mesure</td>
                                <td><input type="number" id="precise_download_dureeMsDownload" min="500" max="30000" step="500"></td>
                                <td>ms</td>
                                <td class="hint">Durée pendant laquelle le stream est lu — le débit est calculé sur cette durée</td>
                            </tr>
                            <tr>
                                <td>Connexions parallèles</td>
                                <td><input type="number" id="precise_download_parallel" min="1" max="10" step="1"></td>
                                <td>—</td>
                                <td class="hint">Streams simultanés — sature mieux la bande passante sur les connexions rapides</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cfg-group">
                    <div class="cfg-group-title">Upload</div>
                    <table class="seuils-table">
                        <thead><tr><th>Paramètre</th><th>Valeur</th><th>Unité</th><th>Explication</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Durée de mesure</td>
                                <td><input type="number" id="precise_upload_dureeMsUpload" min="500" max="30000" step="500"></td>
                                <td>ms</td>
                                <td class="hint">Durée pendant laquelle des blobs sont envoyés en continu</td>
                            </tr>
                            <tr>
                                <td>Connexions parallèles</td>
                                <td><input type="number" id="precise_upload_parallel" min="1" max="10" step="1"></td>
                                <td>—</td>
                                <td class="hint">Envois simultanés</td>
                            </tr>
                            <tr>
                                <td>Taille du blob</td>
                                <td><input type="number" id="precise_upload_tailleMoBlob" min="1" max="50" step="1"></td>
                                <td>Mo</td>
                                <td class="hint">Taille des données aléatoires envoyées par requête POST — doit être suffisant pour durer toute la mesure</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- ── Mode Rapide ─────────────────────────────────────────── -->
            <div class="mode-section">
                <div class="mode-badge mode-fast">Mode Rapide</div>
                <div class="mode-desc">~4 secondes — mesure indicative, précision réduite (~±20%)</div>

                <div class="cfg-group">
                    <div class="cfg-group-title">Ping</div>
                    <table class="seuils-table">
                        <thead><tr><th>Paramètre</th><th>Valeur</th><th>Unité</th><th>Explication</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Requêtes de préchauffage</td>
                                <td><input type="number" id="fast_ping_prechauffage" min="0" max="20" step="1"></td>
                                <td>—</td>
                                <td class="hint">Requêtes ignorées avant la mesure</td>
                            </tr>
                            <tr>
                                <td>Nombre de mesures</td>
                                <td><input type="number" id="fast_ping_echantillons" min="1" max="50" step="1"></td>
                                <td>—</td>
                                <td class="hint">Nombre de ping effectués</td>
                            </tr>
                            <tr>
                                <td>Délai entre mesures</td>
                                <td><input type="number" id="fast_ping_delay" min="0" max="500" step="10"></td>
                                <td>ms</td>
                                <td class="hint">Pause entre chaque ping</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cfg-group">
                    <div class="cfg-group-title">Download</div>
                    <table class="seuils-table">
                        <thead><tr><th>Paramètre</th><th>Valeur</th><th>Unité</th><th>Explication</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Durée de mesure</td>
                                <td><input type="number" id="fast_download_dureeMsDownload" min="500" max="30000" step="500"></td>
                                <td>ms</td>
                                <td class="hint">Durée pendant laquelle le stream est lu</td>
                            </tr>
                            <tr>
                                <td>Connexions parallèles</td>
                                <td><input type="number" id="fast_download_parallel" min="1" max="10" step="1"></td>
                                <td>—</td>
                                <td class="hint">Streams simultanés</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cfg-group">
                    <div class="cfg-group-title">Upload</div>
                    <table class="seuils-table">
                        <thead><tr><th>Paramètre</th><th>Valeur</th><th>Unité</th><th>Explication</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Durée de mesure</td>
                                <td><input type="number" id="fast_upload_dureeMsUpload" min="500" max="30000" step="500"></td>
                                <td>ms</td>
                                <td class="hint">Durée pendant laquelle des blobs sont envoyés en continu</td>
                            </tr>
                            <tr>
                                <td>Connexions parallèles</td>
                                <td><input type="number" id="fast_upload_parallel" min="1" max="10" step="1"></td>
                                <td>—</td>
                                <td class="hint">Envois simultanés</td>
                            </tr>
                            <tr>
                                <td>Taille du blob</td>
                                <td><input type="number" id="fast_upload_tailleMoBlob" min="1" max="50" step="1"></td>
                                <td>Mo</td>
                                <td class="hint">Taille des données aléatoires envoyées par requête POST — doit être suffisant pour durer toute la mesure</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Plafond de sanité ────────────────────────────── -->
            <div class="config-section" style="margin-top: 24px;">
                <h2 class="config-section-title">Plafond de sanité</h2>
                <table class="config-table">
                    <thead>
                        <tr>
                            <th>Paramètre</th>
                            <th>Valeur</th>
                            <th>Unité</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Débit maximum</td>
                            <td><input type="number" id="debit_max_mbitps" min="0" max="10000" step="100" value="1000"></td>
                            <td>Mbit/s</td>
                            <td class="hint">
                                Résultat au-delà de cette valeur → mesure rejetée (loopback ou connexion locale détectée).
                                <strong>0 = désactivé</strong> — à utiliser pour les sites fibrés sur un datacenter (ex. Orléans).
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div><!-- /config-form -->
    </div><!-- /chart-card -->
</div><!-- /page -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="../js/config_speedtest.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>