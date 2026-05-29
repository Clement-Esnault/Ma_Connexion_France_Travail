/**
 * frontend/js/mon_historique.js
 *
 * Gestion de la page "Mon historique".
 * Réutilise le même endpoint get_logs.php avec mode=historique.
 * Affiche le site au lieu de l'IP client.
 */

'use strict';

// ── État ──────────────────────────────────────────────────────────────
let pageActuelle  = 1;
let filtreMode    = null;
let filtreVerdict = null;
let dateDebut     = '';
let dateFin       = '';
let chartInstance = null;

// ── Chargement principal ──────────────────────────────────────────────
async function charger(page = 1) {
    pageActuelle = page;

    const params = new URLSearchParams({ mode: 'historique', page });
    if (filtreMode)    params.set('mode',       filtreMode);   // écrase 'historique'
    if (filtreVerdict) params.set('verdict',    filtreVerdict);
    if (dateDebut)     params.set('date_debut', dateDebut);
    if (dateFin)       params.set('date_fin',   dateFin);

    // En mode historique on passe mode=historique ET éventuellement un filtre mode test
    // → on utilise un param dédié pour ne pas écraser
    const paramsFinaux = new URLSearchParams({ historique: '1', page });
    if (filtreMode)    paramsFinaux.set('mode',       filtreMode);
    else               paramsFinaux.set('mode',       'historique');
    if (filtreVerdict) paramsFinaux.set('verdict',    filtreVerdict);
    if (dateDebut)     paramsFinaux.set('date_debut', dateDebut);
    if (dateFin)       paramsFinaux.set('date_fin',   dateFin);

    const tbody      = document.getElementById('tbody');
    const table      = document.getElementById('table');
    const noResults  = document.getElementById('no-results');
    const totalEl    = document.getElementById('total-logs');
    const statsBox   = document.getElementById('stats-box');

    table.style.display = 'none';
    tbody.innerHTML = `<tr><td colspan="7" class="td-loading"><div class="spinner spinner--center"></div>Chargement…</td></tr>`;
    table.style.display = 'table';

    try {
        const res  = await fetch('/backend/ip/get_logs.php?' + paramsFinaux);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (data.error) throw new Error(data.error);

        afficher(data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="td-error">⚠ ${echapper(err.message)}</td></tr>`;
        console.error('[mon_historique.js]', err);
    }
}

// ── Affichage ─────────────────────────────────────────────────────────
function afficher(data) {
    const tbody     = document.getElementById('tbody');
    const table     = document.getElementById('table');
    const noResults = document.getElementById('no-results');
    const totalEl   = document.getElementById('total-logs');

    totalEl.textContent = data.total > 0
        ? `${data.total} test${data.total > 1 ? 's' : ''} trouvé${data.total > 1 ? 's' : ''}`
        : '';

    if (!data.results || data.results.length === 0) {
        table.style.display     = 'none';
        noResults.style.display = 'block';
        document.getElementById('stats-box').innerHTML = '';
        document.getElementById('chart-box').style.display = 'none';
        creerPagination('pagination',        0, 0, 0, 'tests', () => {});
        creerPagination('pagination-bottom', 0, 0, 0, 'tests', () => {});
        return;
    }

    noResults.style.display = 'none';
    table.style.display     = 'table';

    afficherStats(data.stats);
    afficherGraphique(data.results);

    tbody.innerHTML = data.results.map(log => {
        const verdict = calculerVerdict(log);
        return `<tr>
            <td>${echapper(log.DATE_LOGS_FR)}</td>
            <td><span style="font-family:monospace;font-size:12px;font-weight:600;color:var(--ft-blue)">${echapper(log.CODE_GX_SITE ?? '—')}</span></td>
            <td>${badgeMode(log.MODE)}</td>
            <td class="${classeVerdict(log, 'ping')}">${log.PING_LOGS ?? '—'}</td>
            <td class="${classeVerdict(log, 'dl')}">${log.DOWNLOAD_LOGS ?? '—'}</td>
            <td class="${classeVerdict(log, 'ul')}">${log.UPLOAD_LOGS ?? '—'}</td>
            <td>${badgeVerdict(verdict)}</td>
        </tr>`;
    }).join('');

    creerPagination('pagination',        data.page, data.pages, data.total, 'tests', p => charger(p));
    creerPagination('pagination-bottom', data.page, data.pages, data.total, 'tests', p => charger(p));
}

// ── Stats agrégées ────────────────────────────────────────────────────
function afficherStats(stats) {
    const box = document.getElementById('stats-box');
    if (!stats || stats.avg_download === null) { box.innerHTML = ''; return; }
    box.innerHTML = `
        <div class="stats-grid" style="margin-bottom:1rem">
            <div class="stat-card">
                <div class="stat-title">Ping moyen</div>
                <div class="stat-nb">${stats.avg_ping ?? '—'}<span style="font-size:14px;font-weight:400;color:var(--ft-muted)"> ms</span></div>
                <div class="stat-row"><span>Min</span><span>${stats.min_ping ?? '—'} ms</span></div>
                <div class="stat-row"><span>Max</span><span>${stats.max_ping ?? '—'} ms</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Téléchargement moyen</div>
                <div class="stat-nb">${stats.avg_download ?? '—'}<span style="font-size:14px;font-weight:400;color:var(--ft-muted)"> Mbit/s</span></div>
                <div class="stat-row"><span>Min</span><span>${stats.min_download ?? '—'}</span></div>
                <div class="stat-row"><span>Max</span><span>${stats.max_download ?? '—'}</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Envoi moyen</div>
                <div class="stat-nb">${stats.avg_upload ?? '—'}<span style="font-size:14px;font-weight:400;color:var(--ft-muted)"> Mbit/s</span></div>
                <div class="stat-row"><span>Min</span><span>${stats.min_upload ?? '—'}</span></div>
                <div class="stat-row"><span>Max</span><span>${stats.max_upload ?? '—'}</span></div>
            </div>
        </div>`;
}

// ── Graphique ─────────────────────────────────────────────────────────
function afficherGraphique(results) {
    const box = document.getElementById('chart-box');
    if (!results.length) { box.style.display = 'none'; return; }
    box.style.display = 'block';

    const labels = results.map(r => r.DATE_LOGS_FR).reverse();
    const dl     = results.map(r => parseFloat(r.DOWNLOAD_LOGS) || null).reverse();
    const ul     = results.map(r => parseFloat(r.UPLOAD_LOGS)   || null).reverse();
    const ping   = results.map(r => parseFloat(r.PING_LOGS)     || null).reverse();

    if (chartInstance) chartInstance.destroy();
    chartInstance = new Chart(document.getElementById('chart-logs'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Téléchargement (Mbit/s)', data: dl,   borderColor: '#283276', backgroundColor: 'rgba(40,50,118,.08)', tension: 0.3, yAxisID: 'y' },
                { label: 'Envoi (Mbit/s)',           data: ul,   borderColor: '#406BDE', backgroundColor: 'rgba(64,107,222,.08)', tension: 0.3, yAxisID: 'y' },
                { label: 'Ping (ms)',                data: ping, borderColor: '#E1000F', backgroundColor: 'rgba(225,0,15,.08)',   tension: 0.3, yAxisID: 'y2' },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                x:  { ticks: { maxTicksLimit: 10, font: { size: 11 } }, grid: { display: false } },
                y:  { beginAtZero: true, title: { display: true, text: 'Mbit/s' } },
                y2: { beginAtZero: true, position: 'right', title: { display: true, text: 'ms' }, grid: { drawOnChartArea: false } },
            }
        }
    });
}

// ── Helpers verdicts ──────────────────────────────────────────────────
// Note : les seuils sont chargés depuis utils.js (chargeSeuils)
function calculerVerdict(log) {
    // Utilise les seuils globaux chargés par utils.js si disponibles
    if (typeof SEUILS_GLOBAUX !== 'undefined' && SEUILS_GLOBAUX) {
        const vPing = log.PING_LOGS     <= SEUILS_GLOBAUX.ping_bon     ? 'confort' : log.PING_LOGS     <= SEUILS_GLOBAUX.ping_moyen     ? 'fonctionnel' : 'insuffisant';
        const vDl   = log.DOWNLOAD_LOGS >= SEUILS_GLOBAUX.download_bon ? 'confort' : log.DOWNLOAD_LOGS >= SEUILS_GLOBAUX.download_moyen ? 'fonctionnel' : 'insuffisant';
        const vUl   = log.UPLOAD_LOGS   >= SEUILS_GLOBAUX.upload_bon   ? 'confort' : log.UPLOAD_LOGS   >= SEUILS_GLOBAUX.upload_moyen   ? 'fonctionnel' : 'insuffisant';
        if ([vPing, vDl, vUl].includes('insuffisant')) return 'insuffisant';
        if ([vPing, vDl, vUl].includes('fonctionnel')) return 'fonctionnel';
        return 'confort';
    }
    return null;
}

function classeVerdict(log, metrique) {
    if (typeof SEUILS_GLOBAUX === 'undefined' || !SEUILS_GLOBAUX) return '';
    const val = metrique === 'ping' ? log.PING_LOGS : metrique === 'dl' ? log.DOWNLOAD_LOGS : log.UPLOAD_LOGS;
    if (val === null) return '';
    if (metrique === 'ping') {
        return val <= SEUILS_GLOBAUX.ping_bon ? 'cell-bon' : val <= SEUILS_GLOBAUX.ping_moyen ? 'cell-moyen' : 'cell-mauvais';
    }
    return val >= (metrique === 'dl' ? SEUILS_GLOBAUX.download_bon : SEUILS_GLOBAUX.upload_bon) ? 'cell-bon'
         : val >= (metrique === 'dl' ? SEUILS_GLOBAUX.download_moyen : SEUILS_GLOBAUX.upload_moyen) ? 'cell-moyen' : 'cell-mauvais';
}

function badgeMode(mode) {
    const modes = { precise: ['badge-mode badge-precis', '🎯 Précis'], fast: ['badge-mode badge-rapide', '⚡ Rapide'], balanced: ['badge-mode', '⚖ Équil.'] };
    const [cls, txt] = modes[mode] ?? ['badge-mode', mode ?? '—'];
    return `<span class="${cls}">${txt}</span>`;
}

function badgeVerdict(verdict) {
    if (!verdict) return '—';
    const map = { confort: ['badge-verdict badge-verdict--confort', '✅ Confort'], fonctionnel: ['badge-verdict badge-verdict--fonctionnel', '🟡 Fonctionnel'], insuffisant: ['badge-verdict badge-verdict--insuffisant', '❌ Insuffisant'] };
    const [cls, txt] = map[verdict] ?? ['', verdict];
    return `<span class="${cls}">${txt}</span>`;
}

// ── Filtres ───────────────────────────────────────────────────────────
function filtrerMode(mode, btn) {
    filtreMode = mode;
    document.querySelectorAll('.mode-filter-row:first-of-type .mode-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    charger(1);
}

function filtrerVerdict(verdict, btn) {
    filtreVerdict = verdict;
    document.querySelectorAll('.mode-filter-row:last-of-type .mode-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    charger(1);
}

function appliquerFiltre() {
    dateDebut = document.getElementById('filtre-debut').value;
    dateFin   = document.getElementById('filtre-fin').value;
    document.getElementById('btn-reset-filtre').style.display = (dateDebut || dateFin) ? 'inline-block' : 'none';
    charger(1);
}

function reinitialiserFiltre() {
    dateDebut = dateFin = '';
    document.getElementById('filtre-debut').value = '';
    document.getElementById('filtre-fin').value   = '';
    document.getElementById('btn-reset-filtre').style.display = 'none';
    charger(1);
}

function exporterCSV() {
    ouvrirModalExport({
        titre: 'Export CSV — Mon historique',
        type:  'csv',
        avecStats: true,
        colonnes: [
            { id: 'date',     label: 'Date',          checked: true  },
            { id: 'mode',     label: 'Mode test',     checked: true  },
            { id: 'ping',     label: 'Ping',          checked: true  },
            { id: 'download', label: 'T\u00e9l\u00e9ch.',      checked: true  },
            { id: 'upload',   label: 'Envoi',         checked: true  },
            { id: 'verdict',  label: 'Verdict',       checked: false },
        ],
        onConfirm(opts) {
            // Export c\u00f4t\u00e9 serveur avec param\u00e8tres additionnels
            const params = new URLSearchParams({ mode: 'historique', export: 'csv' });
            if (filtreMode)    params.set('mode',        filtreMode);
            if (filtreVerdict) params.set('verdict',     filtreVerdict);
            if (dateDebut)     params.set('date_debut',  dateDebut);
            if (dateFin)       params.set('date_fin',    dateFin);
            params.set('separateur', opts.separateur);
            params.set('bom',        opts.bom ? '1' : '0');
            params.set('stats',      opts.stats ? '1' : '0');
            params.set('colonnes',   opts.colonnes.join(','));
            window.location.href = '/backend/ip/get_logs.php?' + params;
        }
    });
}

// ── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => charger(1));
// ── Export PDF historique ─────────────────────────────────────────────
function ouvrirExportPDFHistorique() {
    const seuilsRecap = (typeof SEUILS_GLOBAUX !== 'undefined') ? (
        '<strong>Seuils actifs</strong><br>' +
        'Ping &mdash; confort &le; ' + SEUILS_GLOBAUX.ping_bon + ' ms<br>' +
        'T&eacute;l&eacute;chargement &mdash; confort &ge; ' + SEUILS_GLOBAUX.download_bon + ' Mbit/s<br>' +
        'Envoi &mdash; confort &ge; ' + SEUILS_GLOBAUX.upload_bon + ' Mbit/s'
    ) : '';

    ouvrirModalExport({
        titre: 'Export PDF — Mon historique',
        type: 'pdf',
        avecStats: true,
        avecCouleurs: true,
        seuilsRecap,
        onConfirm(opts) {
            const avecCouleurs = opts.couleurs;
            function couleur(valeur, metrique) {
                if (!avecCouleurs || typeof SEUILS_GLOBAUX === 'undefined') return '';
                let v;
                if (metrique === 'ping') {
                    v = valeur <= SEUILS_GLOBAUX.ping_bon ? 'bon' : valeur >= SEUILS_GLOBAUX.ping_mauvais ? 'mauvais' : 'moyen';
                } else {
                    const bon = metrique === 'dl' ? SEUILS_GLOBAUX.download_bon : SEUILS_GLOBAUX.upload_bon;
                    const mauv = metrique === 'dl' ? SEUILS_GLOBAUX.download_mauvais : SEUILS_GLOBAUX.upload_mauvais;
                    v = valeur >= bon ? 'bon' : valeur <= mauv ? 'mauvais' : 'moyen';
                }
                const c = { bon: '#d4edda', moyen: '#fff3cd', mauvais: '#f8d7da' };
                return ' style="background:' + c[v] + '"';
            }

            // Récupérer les logs affichés dans le tbody courant
            const rows = Array.from(document.querySelectorAll('#tbody tr'));
            const lignesHTML = rows.map(tr => {
                const tds = tr.querySelectorAll('td');
                if (!tds.length) return '';
                const date = tds[0]?.textContent ?? '';
                const mode = tds[1]?.textContent ?? '';
                const ping = parseFloat(tds[2]?.textContent ?? 0);
                const dl   = parseFloat(tds[3]?.textContent ?? 0);
                const ul   = parseFloat(tds[4]?.textContent ?? 0);
                return '<tr><td>' + date + '</td><td>' + mode + '</td>' +
                    '<td' + couleur(ping,'ping') + '>' + (tds[2]?.textContent ?? '') + '</td>' +
                    '<td' + couleur(dl,'dl') + '>' + (tds[3]?.textContent ?? '') + '</td>' +
                    '<td' + couleur(ul,'ul') + '>' + (tds[4]?.textContent ?? '') + '</td></tr>';
            }).join('');

            const statsEl = document.getElementById('stats-box');
            const blocStats = (opts.stats && statsEl) ? '<div class="pdf-stats-texte">' + statsEl.innerText + '</div>' : '';

            const entete = document.createElement('div');
            entete.id = 'pdf-header';
            entete.innerHTML =
                '<div class="pdf-meta"><div class="pdf-meta-source">France Travail &mdash; Ma Connexion</div>' +
                '<div class="pdf-meta-title">Mon historique</div>' +
                '<div class="pdf-meta-date">Export&eacute; le ' + new Date().toLocaleDateString('fr-FR') + '</div></div>' +
                blocStats +
                '<table class="pdf-table"><thead><tr>' +
                '<th>Date</th><th>Mode</th><th>Ping (ms)</th><th>T&eacute;l&eacute;ch. (Mbit/s)</th><th>Envoi (Mbit/s)</th>' +
                '</tr></thead><tbody>' + lignesHTML + '</tbody></table>';

            document.body.appendChild(entete);
            const style = document.createElement('style');
            style.id = 'style-print-tmp';
            style.textContent =
                '@media print{.bandeau-dsi,.header,.main-nav,.footer,.page,.export-modal-overlay,.pdf-modal-overlay{display:none!important}#pdf-header{display:block!important}#pdf-header .pdf-meta,#pdf-header .pdf-meta *{display:block!important}}';
            document.head.appendChild(style);
            const nettoyer = () => {
                document.getElementById('style-print-tmp')?.remove();
                document.getElementById('pdf-header')?.remove();
                window.removeEventListener('afterprint', nettoyer);
            };
            window.addEventListener('afterprint', nettoyer);
            window.print();
        }
    });
}