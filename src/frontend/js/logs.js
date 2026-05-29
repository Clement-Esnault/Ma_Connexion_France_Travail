/**
 * logs.js — Historique des logs d'un site France Travail.
 *
 * Affiche la liste paginée des mesures de débit d'un site,
 * avec filtres date/mode/verdict, statistiques agrégées et graphique.
 *
 * Dépendances :
 *   utils.js → fetchJson(), SEUILS, chargerSeuils(),
 *              classCouleur(), tooltipVerdict(), creerPagination(), echapper()
 *   Chart.js → afficherGraphique()
 */

// ── Paramètres URL ────────────────────────────────────────────────────
const paramsUrl    = new URLSearchParams(window.location.search);
const CODE_GX_SITE = paramsUrl.get('CODE_GX_SITE');
const nomSite      = paramsUrl.get('nom');

// ── État des filtres ──────────────────────────────────────────────────
let pageActuelle  = 1;
let filtreDebut   = '';
let filtreFin     = '';
let filtreMode    = null;    // null = tous | 'precise' | 'balanced' | 'fast'
let filtreVerdict = null;    // null = tous | 'confort' | 'fonctionnel' | 'insuffisant'

/** Instance Chart.js courante — détruite avant chaque recréation. */
let instanceGraphique = null;

// Mettre à jour le titre de la page avec le nom du site
if (nomSite) {
    document.getElementById('titre').textContent = 'Logs — ' + nomSite;
}

// ══════════════════════════════════════════════════════════════════════
// STATISTIQUES AGRÉGÉES
// ══════════════════════════════════════════════════════════════════════

/**
 * Calcule et retourne le HTML d'une flèche de tendance
 * comparant la dernière mesure à la moyenne historique.
 *
 * Seuil de stabilité : ± 5 % — en dessous, la flèche est →
 *
 * @param {number}  derniere         Valeur du dernier log
 * @param {number}  moyenne          Moyenne historique
 * @param {boolean} [inverse=false]  true pour ping (plus bas = meilleur)
 * @returns {string} Fragment HTML de la flèche de tendance
 */
function flecheTendance(derniere, moyenne, inverse = false) {
    if (!derniere || !moyenne || moyenne === 0) return '';

    const ecartPct  = ((derniere - moyenne) / moyenne) * 100;
    const SEUIL_PCT = 5; // Variation minimale pour afficher une tendance

    if (Math.abs(ecartPct) < SEUIL_PCT) {
        return '<span class="tendance tendance--stable" title="Stable">→</span>';
    }

    const estMieux = inverse ? ecartPct < 0 : ecartPct > 0;
    const pct      = Math.abs(ecartPct).toFixed(0);

    return estMieux
        ? `<span class="tendance tendance--hausse" title="+${pct}% vs moyenne">↑</span>`
        : `<span class="tendance tendance--baisse" title="-${pct}% vs moyenne">↓</span>`;
}

/**
 * Affiche les statistiques agrégées (moyenne, min, max, écart-type)
 * dans le bloc #stats-box.
 *
 * @param {Object|null} stats         Données stats du backend, null = vider le bloc
 * @param {number}      totalLogs     Nombre total de logs
 * @param {Object[]}    [logs=[]]     Logs de la page courante (pour le dernier log)
 */
function afficherStats(stats, totalLogs, logs = []) {
    const conteneur = document.getElementById('stats-box');
    if (!stats) { conteneur.innerHTML = ''; return; }

    const dernierLog = logs[0] ?? null;

    conteneur.innerHTML = `
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Ping (millisecondes)</div>
                <div class="stat-row">
                    <span>Moyenne</span>
                    <span class="${classCouleur(+stats.avg_ping, SEUILS.ping, true)}">${(+stats.avg_ping).toFixed(1)}</span>
                    ${flecheTendance(dernierLog ? +dernierLog.PING_LOGS : 0, +stats.avg_ping, true)}
                </div>
                <div class="stat-row"><span>Écart-type</span><span class="cell-moyen">± ${(+stats.ecart_type_ping).toFixed(1)}</span></div>
                <div class="stat-row"><span>Min</span><span class="cell-bon">${(+stats.min_ping).toFixed(1)}</span></div>
                <div class="stat-row"><span>Max</span><span class="${classCouleur(+stats.max_ping, SEUILS.ping, true)}">${(+stats.max_ping).toFixed(1)}</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Téléchargement (Mbit/s)</div>
                <div class="stat-row">
                    <span>Moyenne</span>
                    <span class="${classCouleur(+stats.avg_download, SEUILS.download)}">${(+stats.avg_download).toFixed(2)}</span>
                    ${flecheTendance(dernierLog ? +dernierLog.DOWNLOAD_LOGS : 0, +stats.avg_download)}
                </div>
                <div class="stat-row"><span>Écart-type</span><span class="cell-moyen">± ${(+stats.ecart_type_download).toFixed(1)}</span></div>
                <div class="stat-row"><span>Min</span><span class="${classCouleur(+stats.min_download, SEUILS.download)}">${(+stats.min_download).toFixed(2)}</span></div>
                <div class="stat-row"><span>Max</span><span class="cell-bon">${(+stats.max_download).toFixed(2)}</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Envoi (Mbit/s)</div>
                <div class="stat-row">
                    <span>Moyenne</span>
                    <span class="${classCouleur(+stats.avg_upload, SEUILS.upload)}">${(+stats.avg_upload).toFixed(2)}</span>
                    ${flecheTendance(dernierLog ? +dernierLog.UPLOAD_LOGS : 0, +stats.avg_upload)}
                </div>
                <div class="stat-row"><span>Écart-type</span><span class="cell-moyen">± ${(+stats.ecart_type_upload).toFixed(1)}</span></div>
                <div class="stat-row"><span>Min</span><span class="${classCouleur(+stats.min_upload, SEUILS.upload)}">${(+stats.min_upload).toFixed(2)}</span></div>
                <div class="stat-row"><span>Max</span><span class="cell-bon">${(+stats.max_upload).toFixed(2)}</span></div>
            </div>
            <div class="stat-card stat-card-nb">
                <div class="stat-title">Tests</div>
                <div class="stat-nb">${totalLogs}</div>
                <div class="stat-nb-libelle">au total</div>
            </div>
        </div>`;
}

// ══════════════════════════════════════════════════════════════════════
// CHARGEMENT DES LOGS
// ══════════════════════════════════════════════════════════════════════

/**
 * Charge et affiche une page de logs pour le site CODE_GX_SITE courant.
 * Applique les filtres date, mode et verdict actifs.
 *
 * @param {number} [page=1]  Numéro de page à charger (1-based)
 */
async function chargerLogs(page = 1) {
    if (!CODE_GX_SITE) return;
    pageActuelle = page;

    const tbody = document.getElementById('tbody');
    const table = document.getElementById('table');
    table.style.display = 'table';
    tbody.innerHTML = `<tr><td colspan="6" class="td-loading"><div class="spinner spinner--center"></div>Chargement…</td></tr>`;

    try {
        // Construction de l'URL avec les filtres actifs
        const url = '/backend/ip/get_logs.php?CODE_GX_SITE=' + CODE_GX_SITE
            + '&page='     + page
            + (filtreDebut   ? '&date_debut=' + filtreDebut    : '')
            + (filtreFin     ? '&date_fin='   + filtreFin      : '')
            + (filtreMode    ? '&mode='        + filtreMode    : '')
            + (filtreVerdict ? '&verdict='     + filtreVerdict : '');

        const donnees      = await fetchJson(url);
        const aucunResultat = document.getElementById('no-results');
        tbody.innerHTML    = '';

        // Aucun résultat
        if (!donnees?.results?.length) {
            table.style.display          = 'none';
            aucunResultat.style.display  = 'block';
            document.getElementById('stats-box').innerHTML          = '';
            document.getElementById('pagination').innerHTML         = '';
            document.getElementById('pagination-bottom').innerHTML  = '';
            return;
        }

        aucunResultat.style.display = 'none';
        table.style.display         = 'table';

        afficherStats(donnees.stats, donnees.total, donnees.results);
        afficherGraphique(donnees.results, donnees.stats);
        document.getElementById('filtre-verdict-row').style.display = 'flex';

        // Libellé du filtre actif sous le compteur
        let descriptionFiltre = '';
        if (filtreDebut || filtreFin) {
            descriptionFiltre += ` entre le ${filtreDebut || '…'} et le ${filtreFin || '…'}`;
        }
        if (filtreMode === 'precise')  descriptionFiltre += ' — tests précis';
        if (filtreMode === 'balanced') descriptionFiltre += ' — tests équilibrés';
        if (filtreMode === 'fast')     descriptionFiltre += ' — tests rapides';
        document.getElementById('total-logs').textContent = `${donnees.total} log(s)${descriptionFiltre}`;

        // Construction des lignes du tableau
        donnees.results.forEach(log => {
            const valPing     = parseFloat(log.PING_LOGS);
            const valDownload = parseFloat(log.DOWNLOAD_LOGS);
            const valUpload   = parseFloat(log.UPLOAD_LOGS);
            const modeLog     = log.MODE ?? 'precise';

            // Badge visuel du mode de test
            const badgeMode = modeLog === 'fast'
                ? '<span class="badge-mode badge-rapide">⚡ Rapide</span>'
                : modeLog === 'balanced'
                    ? '<span class="badge-mode badge-equilibre">⚖ Équilibré</span>'
                    : '<span class="badge-mode badge-precis">🎯 Précis</span>';

            const ligne     = document.createElement('tr');
            ligne.id        = 'log-' + log.ID_LOGS;
            ligne.innerHTML = `
                <td>${log.DATE_LOGS_FR}</td>
                <td>${log.IP_CLIENT}</td>
                <td>${badgeMode}</td>
                <td class="${classCouleur(valPing,     SEUILS.ping,     true)}"
                    title="${tooltipVerdict(valPing,     SEUILS.ping,     'ping',     true)}">${log.PING_LOGS} ms</td>
                <td class="${classCouleur(valDownload, SEUILS.download, false)}"
                    title="${tooltipVerdict(valDownload, SEUILS.download, 'download', false)}">${log.DOWNLOAD_LOGS} Mbit/s</td>
                <td class="${classCouleur(valUpload,   SEUILS.upload,   false)}"
                    title="${tooltipVerdict(valUpload,   SEUILS.upload,   'upload',   false)}">${log.UPLOAD_LOGS} Mbit/s</td>`;
            tbody.appendChild(ligne);
        });

        // Pagination en haut et en bas du tableau
        creerPagination('pagination',        donnees.page, donnees.pages, donnees.total, 'logs', p => chargerLogs(p));
        creerPagination('pagination-bottom', donnees.page, donnees.pages, donnees.total, 'logs', p => chargerLogs(p));

        // Surligner un log spécifique si demandé dans l'URL (?surligner=ID_LOGS)
        const idSurligne = paramsUrl.get('surligner');
        if (idSurligne) {
            const lignesCiblee = document.getElementById('log-' + idSurligne);
            if (lignesCiblee) {
                lignesCiblee.scrollIntoView({ behavior: 'smooth', block: 'center' });
                lignesCiblee.classList.add('log-surligner');
                setTimeout(() => lignesCiblee.classList.remove('log-surligner'), 3000);
            }
        }

    } catch (erreur) {
        tbody.innerHTML = `<tr><td colspan="6" class="td-error">⚠ Impossible de charger les logs.</td></tr>`;
        console.error('[logs.js]', erreur);
    }
}

// ══════════════════════════════════════════════════════════════════════
// FILTRES
// ══════════════════════════════════════════════════════════════════════

/**
 * Active le filtre par mode de test et recharge la page 1.
 * @param {string|null} valeur  'precise' | 'balanced' | 'fast' | null (tous)
 * @param {HTMLElement} bouton  Bouton cliqué (reçoit la classe .active)
 */
function filtrerMode(valeur, bouton) {
    filtreMode = valeur;
    document.querySelectorAll('.mode-filter-btn').forEach(b => b.classList.remove('active'));
    bouton.classList.add('active');
    chargerLogs(1);
}

/**
 * Active le filtre par verdict qualitatif et recharge la page 1.
 * @param {string|null} valeur  'confort' | 'fonctionnel' | 'insuffisant' | null
 * @param {HTMLElement} bouton
 */
function filtrerVerdict(valeur, bouton) {
    filtreVerdict = valeur;
    document.querySelectorAll('#filtre-verdict-row .mode-filter-btn').forEach(b => b.classList.remove('active'));
    bouton.classList.add('active');
    chargerLogs(1);
}

/**
 * Applique les filtres de dates (début et fin) et recharge la page 1.
 * Affiche le bouton de réinitialisation si au moins un filtre est actif.
 */
function appliquerFiltre() {
    filtreDebut = document.getElementById('filtre-debut').value;
    filtreFin   = document.getElementById('filtre-fin').value;
    document.getElementById('btn-reset-filtre').style.display =
        (filtreDebut || filtreFin) ? 'inline-block' : 'none';
    chargerLogs(1);
}

/**
 * Réinitialise les filtres de dates et recharge la page 1.
 */
function reinitialiserFiltre() {
    filtreDebut = '';
    filtreFin   = '';
    document.getElementById('filtre-debut').value        = '';
    document.getElementById('filtre-fin').value          = '';
    document.getElementById('btn-reset-filtre').style.display = 'none';
    chargerLogs(1);
}

// ══════════════════════════════════════════════════════════════════════
// SUPPRESSION
// ══════════════════════════════════════════════════════════════════════

/**
 * Supprime un log après confirmation utilisateur.
 * Désactive le bouton pendant la requête pour éviter le double-clic.
 *
 * @param {number}      idLog   ID_LOGS du log à supprimer
 * @param {HTMLElement} bouton  Bouton déclencheur
 */
async function supprimerLog(idLog, bouton) {
    if (!confirm('Supprimer ce log ?')) return;

    bouton.disabled    = true;
    bouton.textContent = '…';

    try {
        const reponse = await fetchJson('/backend/ip/delete_log.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id_log: idLog }),
        });

        if (reponse.success) {
            chargerLogs(pageActuelle);
        } else {
            bouton.disabled    = false;
            bouton.textContent = '🗑';
            alert('Erreur : ' + (reponse.error ?? 'suppression échouée'));
        }
    } catch (erreur) {
        bouton.disabled    = false;
        bouton.textContent = '🗑';
        console.error('[logs.js]', erreur);
        alert('Impossible de contacter le serveur.');
    }
}

// ══════════════════════════════════════════════════════════════════════
// EXPORTS
// ══════════════════════════════════════════════════════════════════════

// ── Modal PDF ─────────────────────────────────────────────────────────
let _optionPDF = 'couleurs';

function ouvrirModalPDF() {
    const recap = document.getElementById('pdf-seuils-recap');
    if (recap) {
        recap.innerHTML =
            '<strong>Seuils actifs</strong><br>' +
            'Ping &mdash; confort &le; ' + SEUILS.ping.bon + ' ms &middot; insuffisant &ge; ' + SEUILS.ping.mauvais + ' ms<br>' +
            'T&eacute;l&eacute;chargement &mdash; confort &ge; ' + SEUILS.download.bon + ' Mbit/s &middot; insuffisant &le; ' + SEUILS.download.mauvais + ' Mbit/s<br>' +
            'Envoi &mdash; confort &ge; ' + SEUILS.upload.bon + ' Mbit/s &middot; insuffisant &le; ' + SEUILS.upload.mauvais + ' Mbit/s';
    }
    selOptionPDF('couleurs');
    document.getElementById('pdf-modal-overlay').classList.add('visible');
}

function fermerModalPDF() {
    document.getElementById('pdf-modal-overlay').classList.remove('visible');
}

function selOptionPDF(option) {
    _optionPDF = option;
    document.getElementById('opt-couleurs').classList.toggle('selected', option === 'couleurs');
    document.getElementById('opt-neutre').classList.toggle('selected', option === 'neutre');
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('pdf-modal-overlay')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) fermerModalPDF();
    });
});

async function lancerExportPDF() {
    const avecCouleurs = (_optionPDF === 'couleurs');
    const nom = nomSite ?? CODE_GX_SITE ?? 'logs';
    fermerModalPDF();
    const btnPdf = document.getElementById('btn-export-pdf');
    if (btnPdf) { btnPdf.disabled = true; btnPdf.textContent = 'Chargement...'; }

    try {
        const url = '/backend/ip/get_logs.php?CODE_GX_SITE=' + encodeURIComponent(CODE_GX_SITE)
            + '&export=all'
            + (filtreDebut   ? '&date_debut=' + filtreDebut   : '')
            + (filtreFin     ? '&date_fin='   + filtreFin     : '')
            + (filtreMode    ? '&mode='        + filtreMode    : '')
            + (filtreVerdict ? '&verdict='     + filtreVerdict : '');

        const donnees = await fetchJson(url);
        if (!donnees?.results?.length) { alert('Aucun log a exporter.'); return; }

        function couleurCellule(valeur, metrique) {
            if (!avecCouleurs) return '';
            const s = SEUILS[metrique];
            let v;
            if (metrique === 'ping') {
                v = valeur <= s.bon ? 'bon' : valeur >= s.mauvais ? 'mauvais' : 'moyen';
            } else {
                v = valeur >= s.bon ? 'bon' : valeur <= s.mauvais ? 'mauvais' : 'moyen';
            }
            const c = { bon: '#d4edda', moyen: '#fff3cd', mauvais: '#f8d7da' };
            return ' style="background:' + c[v] + '"';
        }

        const st = donnees.stats ?? {};
        const lignesSeuils = avecCouleurs
            ? '<tr><td>Seuil confort</td><td style="background:#d4edda">&le; ' + SEUILS.ping.bon + ' ms</td><td style="background:#d4edda">&ge; ' + SEUILS.download.bon + ' Mbit/s</td><td style="background:#d4edda">&ge; ' + SEUILS.upload.bon + ' Mbit/s</td></tr>' +
              '<tr><td>Seuil insuffisant</td><td style="background:#f8d7da">&ge; ' + SEUILS.ping.mauvais + ' ms</td><td style="background:#f8d7da">&le; ' + SEUILS.download.mauvais + ' Mbit/s</td><td style="background:#f8d7da">&le; ' + SEUILS.upload.mauvais + ' Mbit/s</td></tr>'
            : '<tr><td>Seuil confort</td><td>&le; ' + SEUILS.ping.bon + ' ms</td><td>&ge; ' + SEUILS.download.bon + ' Mbit/s</td><td>&ge; ' + SEUILS.upload.bon + ' Mbit/s</td></tr>' +
              '<tr><td>Seuil insuffisant</td><td>&ge; ' + SEUILS.ping.mauvais + ' ms</td><td>&le; ' + SEUILS.download.mauvais + ' Mbit/s</td><td>&le; ' + SEUILS.upload.mauvais + ' Mbit/s</td></tr>';

        const blocStats = st.avg_ping ? (
            '<table class="pdf-stats"><thead><tr>' +
            '<th></th><th>Ping (ms)</th><th>Telechargement (Mbit/s)</th><th>Envoi (Mbit/s)</th>' +
            '</tr></thead><tbody>' +
            '<tr><td>Moyenne</td><td' + couleurCellule(+st.avg_ping,'ping') + '>' + st.avg_ping + '</td><td' + couleurCellule(+st.avg_download,'download') + '>' + st.avg_download + '</td><td' + couleurCellule(+st.avg_upload,'upload') + '>' + st.avg_upload + '</td></tr>' +
            '<tr><td>Min</td><td' + couleurCellule(+st.min_ping,'ping') + '>' + st.min_ping + '</td><td' + couleurCellule(+st.min_download,'download') + '>' + st.min_download + '</td><td' + couleurCellule(+st.min_upload,'upload') + '>' + st.min_upload + '</td></tr>' +
            '<tr><td>Max</td><td' + couleurCellule(+st.max_ping,'ping') + '>' + st.max_ping + '</td><td' + couleurCellule(+st.max_download,'download') + '>' + st.max_download + '</td><td' + couleurCellule(+st.max_upload,'upload') + '>' + st.max_upload + '</td></tr>' +
            '<tr><td>Ecart-type</td><td>+/- ' + st.ecart_type_ping + '</td><td>+/- ' + st.ecart_type_download + '</td><td>+/- ' + st.ecart_type_upload + '</td></tr>' +
            lignesSeuils +
            '</tbody></table>'
        ) : '';

        const lignes = donnees.results.map(function(log) {
            const modeLog   = log.MODE ?? 'precise';
            const labelMode = modeLog === 'fast' ? 'Rapide' : modeLog === 'balanced' ? 'Equilibre' : 'Precis';
            return '<tr>' +
                '<td>' + echapper(log.DATE_LOGS_FR) + '</td>' +
                '<td>' + echapper(log.IP_CLIENT ?? log.CODE_GX_SITE ?? '') + '</td>' +
                '<td>' + labelMode + '</td>' +
                '<td' + couleurCellule(+log.PING_LOGS,     'ping')     + '>' + log.PING_LOGS     + ' ms</td>' +
                '<td' + couleurCellule(+log.DOWNLOAD_LOGS, 'download') + '>' + log.DOWNLOAD_LOGS + ' Mbit/s</td>' +
                '<td' + couleurCellule(+log.UPLOAD_LOGS,   'upload')   + '>' + log.UPLOAD_LOGS   + ' Mbit/s</td>' +
            '</tr>';
        }).join('');

        const entete = document.createElement('div');
        entete.id = 'pdf-header';
        entete.innerHTML =
            '<div class="pdf-meta">' +
                '<div class="pdf-meta-source">France Travail &mdash; Ma Connexion</div>' +
                '<div class="pdf-meta-title">Logs &mdash; ' + echapper(nom) + '</div>' +
                '<div class="pdf-meta-date">Exporte le ' + new Date().toLocaleDateString('fr-FR') +
                ' a ' + new Date().toLocaleTimeString('fr-FR') +
                ' &mdash; ' + donnees.total + ' log(s)' +
                (avecCouleurs ? ' &middot; Avec code couleur' : ' &middot; Sans code couleur') +
                '</div>' +
            '</div>' +
            blocStats +
            '<table class="pdf-table">' +
                '<thead><tr><th>Date</th><th>IP</th><th>Mode</th><th>Ping (ms)</th><th>Telechargement (Mbit/s)</th><th>Envoi (Mbit/s)</th></tr></thead>' +
                '<tbody>' + lignes + '</tbody>' +
            '</table>';

        document.body.appendChild(entete);

        const stylePrint = document.createElement('style');
        stylePrint.id = 'style-print-tmp';
        stylePrint.textContent =
            '@media print{.bandeau-dsi,.header,.main-nav,.footer,.page,.export-modal-overlay,.pdf-modal-overlay{display:none!important}#pdf-header{display:block!important}#pdf-header .pdf-meta,#pdf-header .pdf-meta *{display:block!important}}';
        document.head.appendChild(stylePrint);

        const nettoyer = () => {
            document.getElementById('style-print-tmp')?.remove();
            document.getElementById('pdf-header')?.remove();
            window.removeEventListener('afterprint', nettoyer);
        };
        window.addEventListener('afterprint', nettoyer);
        window.print();

    } catch (erreur) {
        console.error('[logs.js] lancerExportPDF', erreur);
        alert("Impossible de charger les logs pour l'export PDF.");
    } finally {
        if (btnPdf) { btnPdf.disabled = false; btnPdf.textContent = 'Imprimer / PDF'; }
    }
}

function exporterPDF() { ouvrirModalPDF(); }


/**
 * Déclenche le téléchargement CSV des logs filtrés via get_logs.php?export=csv.
 * Le nom du fichier inclut le code GX et la date du jour.
 */
function exporterCSV() {
    if (!CODE_GX_SITE) return;
    ouvrirModalExport({
        titre: 'Export CSV — Logs du site',
        type:  'csv',
        avecStats: true,
        colonnes: [
            { id: 'date',     label: 'Date',         checked: true  },
            { id: 'ip',       label: 'IP client',    checked: true  },
            { id: 'mode',     label: 'Mode test',    checked: true  },
            { id: 'ping',     label: 'Ping',         checked: true  },
            { id: 'download', label: 'T\u00e9l\u00e9ch.', checked: true  },
            { id: 'upload',   label: 'Envoi',        checked: true  },
        ],
        onConfirm(opts) {
            const params = new URLSearchParams({
                export:      'csv',
                CODE_GX_SITE: CODE_GX_SITE,
                separateur:  opts.separateur,
                bom:         opts.bom ? '1' : '0',
                stats:       opts.stats ? '1' : '0',
                colonnes:    opts.colonnes.join(','),
            });
            if (filtreDebut)           params.set('date_debut', filtreDebut);
            if (filtreFin)             params.set('date_fin',   filtreFin);
            if (filtreMode !== null)   params.set('mode',       filtreMode);
            if (filtreVerdict !== null) params.set('verdict',   filtreVerdict);
            window.location.href = '/backend/ip/get_logs.php?' + params;
        }
    });
}

// ══════════════════════════════════════════════════════════════════════
// GRAPHIQUE D'ÉVOLUTION
// ══════════════════════════════════════════════════════════════════════

/**
 * Affiche le graphique Chart.js des mesures ping / download / upload
 * de la page courante. Utilise deux axes Y (Mbit/s à gauche, ms à droite).
 *
 * Le graphique n'est affiché que s'il y a au moins 2 mesures.
 *
 * @param {Array}  logs   Logs de la page courante (triés du plus récent au plus ancien)
 * @param {Object} stats  Statistiques agrégées (pour le sous-titre)
 */
function afficherGraphique(logs, stats) {
    const conteneur = document.getElementById('chart-box');
    if (!logs || logs.length < 2) {
        conteneur.style.display = 'none';
        return;
    }

    conteneur.style.display = 'block';

    // Inverser l'ordre pour afficher du plus ancien au plus récent
    const etiquettes   = logs.map(log => log.DATE_LOGS_FR).reverse();
    const valeursPing  = logs.map(log => parseFloat(log.PING_LOGS)).reverse();
    const valeursDl    = logs.map(log => parseFloat(log.DOWNLOAD_LOGS)).reverse();
    const valeursUl    = logs.map(log => parseFloat(log.UPLOAD_LOGS)).reverse();

    document.getElementById('chart-subtitle').textContent =
        `${logs.length} mesures — avg DL : ${stats?.avg_download ?? '—'} Mbit/s · avg Ping : ${stats?.avg_ping ?? '—'} ms`;

    // Détruire l'instance précédente pour éviter les fuites mémoire
    if (instanceGraphique) instanceGraphique.destroy();

    const estModeSombre  = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const couleurGrille  = estModeSombre ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const couleurEtiq    = estModeSombre ? '#8892b8' : '#6b7a9e';

    instanceGraphique = new Chart(document.getElementById('chart-logs'), {
        type: 'line',
        data: {
            labels:   etiquettes,
            datasets: [
                {
                    label:           'Téléchargement (Mbit/s)',
                    data:            valeursDl,
                    borderColor:     '#406BDE',
                    backgroundColor: 'rgba(64,107,222,0.08)',
                    tension:         0.3,
                    pointRadius:     3,
                    yAxisID:         'yDl',
                },
                {
                    label:           'Envoi (Mbit/s)',
                    data:            valeursUl,
                    borderColor:     '#008ECF',
                    backgroundColor: 'rgba(0,142,207,0.06)',
                    tension:         0.3,
                    pointRadius:     3,
                    yAxisID:         'yDl',
                },
                {
                    label:           'Ping (ms)',
                    data:            valeursPing,
                    borderColor:     '#E1000F',
                    backgroundColor: 'rgba(225,0,15,0.06)',
                    tension:         0.3,
                    pointRadius:     3,
                    yAxisID:         'yPing',
                },
            ],
        },
        options: {
            responsive:  true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: { color: couleurEtiq, font: { size: 12 } },
                },
            },
            scales: {
                x: {
                    ticks: { color: couleurEtiq, font: { size: 10 }, maxTicksLimit: 8 },
                    grid:  { color: couleurGrille },
                },
                // Axe gauche : Mbit/s (download + upload)
                yDl: {
                    position: 'left',
                    title:    { display: true, text: 'Mbit/s', color: couleurEtiq },
                    ticks:    { color: couleurEtiq },
                    grid:     { color: couleurGrille },
                },
                // Axe droit : ms (ping) — grille désactivée pour ne pas surcharger
                yPing: {
                    position: 'right',
                    title:    { display: true, text: 'ms', color: couleurEtiq },
                    ticks:    { color: '#E1000F' },
                    grid:     { drawOnChartArea: false },
                },
            },
        },
    });
}

// ── Démarrage ─────────────────────────────────────────────────────────
// Charger les seuils depuis la BDD, puis charger la première page de logs
chargerSeuils().then(() => chargerLogs());