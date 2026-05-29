/**
 * alertes.js — Sites avec performances insuffisantes.
 *
 * Dépend de utils.js (creerPagination, echapper).
 * Données chargées depuis backend/ip/get_alertes.php.
 */

// alertes.js — sites avec performances insuffisantes. Requiert utils.js chargé avant.

const PAR_PAGE = 25;
let tousSites = [], seuils = {}, colonnesTri = 'moy_ping', directionTri = 'desc', page = 1;

// Icônes SVG inline pour les badges de verdict (ok / insuffisant / fonctionnel)
const ICONES = {
    ok:   `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>`,
    warn: `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    mid:  `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="5" y1="12" x2="19" y2="12"/></svg>`,
};

// ── Chargement ────────────────────────────────────────────────────────
// Appelle get_alertes.php avec la période sélectionnée et stocke les données brutes
async function chargerDonnees() {
    const periodeSelecteur  = document.getElementById('sel-periode').value;
    const chargement  = document.getElementById('loading');
    const table    = document.getElementById('sites-table');
    const emptyMsg = document.getElementById('empty-msg');

    chargement.style.display = 'block';
    table.style.display   = 'none';
    emptyMsg.style.display = 'none';

    try {
        const res = await fetch(`/backend/ip/get_alertes.php?periode=${periodeSelecteur}`, {
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(`Erreur serveur ${res.status}`);
        const data = await res.json();

        if (!data.success) {
            chargement.innerHTML = `<div class="icon">⚠️</div><div>Erreur : ${data.error}</div>`;
            return;
        }

        seuils   = data.seuils || {};
        tousSites = data.sites  || [];

        // Avertissement si certains seuils ne sont pas configurés en BDD
        const manquants = ['ping', 'download', 'upload'].filter(m => !seuils[m]);
        document.getElementById('alert-seuils').style.display = manquants.length ? 'block' : 'none';

        chargement.style.display = 'none';
        page = 1;
        filtrerEtAfficher();
    } catch (err) {
        chargement.innerHTML = `<div class="icon">❌</div><div>${err.message}</div>`;
        console.error('[alertes.js]', err);
    }
}

// ── Filtrage + tri local ──────────────────────────────────────────────
// Filtre tousSites selon la métrique et la recherche texte, puis trie et affiche
function filtrerEtAfficher() {
    const metrique = document.getElementById('sel-metrique').value;
    const champRecherche   = document.getElementById('inp-search').value.trim().toLowerCase();

    const seulRegresssion = document.getElementById('chk-regression')?.checked ?? false;

    let filtered = tousSites.filter(s => {
        // Filtre régression
        if (seulRegresssion && !s.en_regression) return false;
        // Filtre par métrique : n'affiche que les sites insuffisants sur la métrique choisie
        if (metrique === 'ping'     && s.verdict_ping     !== 'insuffisant') return false;
        if (metrique === 'download' && s.verdict_download !== 'insuffisant') return false;
        if (metrique === 'upload'   && s.verdict_upload   !== 'insuffisant') return false;
        // Filtre texte : cherche dans code, nom, département, région, interrégion
        if (champRecherche) {
            const texteRecherche = [s.CODE_GX_SITE, s.NOM_SITE, s.NOM_DEPARTEMENT, s.NOM_REGION, s.NOM_INTERREGION]
                .join(' ').toLowerCase();
            if (!texteRecherche.includes(champRecherche)) return false;
        }
        return true;
    });

    // Tri numérique ou alphabétique selon la colonne active
    filtered.sort((a, b) => {
        let va = a[colonnesTri] ?? '', vb = b[colonnesTri] ?? '';
        if (!isNaN(va) && !isNaN(vb)) { va = +va; vb = +vb; }
        else { va = String(va).toLowerCase(); vb = String(vb).toLowerCase(); }
        if (va < vb) return directionTri === 'asc' ? -1 :  1;
        if (va > vb) return directionTri === 'asc' ?  1 : -1;
        return 0;
    });

    majIndicateurs(filtered);
    afficherTableau(filtered);
}

// ── KPIs ──────────────────────────────────────────────────────────────
// Met à jour les 4 compteurs : total, nb insuffisants par métrique
function majIndicateurs(data) {
    document.getElementById('kpi-total').textContent      = data.length;
    document.getElementById('kpi-ping').textContent       = data.filter(s => s.verdict_ping     === 'insuffisant').length;
    document.getElementById('kpi-dl').textContent         = data.filter(s => s.verdict_download === 'insuffisant').length;
    document.getElementById('kpi-ul').textContent         = data.filter(s => s.verdict_upload   === 'insuffisant').length;
    const nbReg = data.filter(s => s.en_regression).length;
    const kpiReg = document.getElementById('kpi-regression');
    if (kpiReg) kpiReg.textContent = nbReg;
    const kpiRegBox = document.getElementById('kpi-regression-box');
    if (kpiRegBox) kpiRegBox.classList.toggle('kpi--alerte', nbReg > 0);
    const badge = document.getElementById('badge-count');
    badge.textContent   = data.length + ' site' + (data.length > 1 ? 's' : '');
    badge.style.display = 'inline-block';
}

// ── Tableau ───────────────────────────────────────────────────────────
// Affiche la page courante du tableau avec pagination via creerPagination() de utils.js
function afficherTableau(data) {
    const table    = document.getElementById('sites-table');
    const tbody    = document.getElementById('sites-tbody');
    const emptyMsg = document.getElementById('empty-msg');

    if (!data.length) {
        table.style.display = 'none';
table.classList.add('hidden');
        emptyMsg.style.display = 'block';
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    emptyMsg.style.display = 'none';
table.classList.remove('hidden');
table.style.display = 'table';

    const totalPages = Math.ceil(data.length / PAR_PAGE);
    if (page > totalPages) page = 1;
    tbody.innerHTML = data.slice((page - 1) * PAR_PAGE, page * PAR_PAGE).map(construireLigne).join('');

    creerPagination('pagination', page, totalPages, data.length, 'site(s)', p => { page = p; afficherTableau(data); });
}

// Génère le HTML d'une ligne de tableau pour un site insuffisant
function construireLigne(s) {
    const insuffisants = [
        s.verdict_ping     === 'insuffisant' ? 'PING' : null,
        s.verdict_download === 'insuffisant' ? 'DL'   : null,
        s.verdict_upload   === 'insuffisant' ? 'UL'   : null,
    ].filter(Boolean);

    const code = encodeURIComponent(s.CODE_GX_SITE);
    const nom  = encodeURIComponent(s.NOM_SITE);

    const badgeReg = s.en_regression
        ? `<span class="badge-regression" title="Ce site a enregistré au moins 3 tests insuffisants consécutifs après une période correcte">📉 Régression</span>`
        : '';

    return `<tr class="${s.en_regression ? 'ligne-regression' : ''}">
        <td><a class="site-link" href="logs.php?CODE_GX_SITE=${code}&nom=${nom}">${echapper(s.CODE_GX_SITE)}</a></td>
        <td><a class="site-link" href="logs.php?CODE_GX_SITE=${code}&nom=${nom}">${echapper(s.NOM_SITE)} ${badgeReg}</a></td>
        <td>${echapper(s.NOM_DEPARTEMENT)}</td>
        <td>${echapper(s.NOM_REGION)}</td>
        <td>${badgeVerdict(s.verdict_ping,     s.moy_ping     + '&nbsp;ms')}</td>
        <td>${badgeVerdict(s.verdict_download, s.moy_download + '&nbsp;Mbit/s')}</td>
        <td>${badgeVerdict(s.verdict_upload,   s.moy_upload   + '&nbsp;Mbit/s')}</td>
        <td class="col-regression">${badgeReg}</td>
        <td><div class="tags">${insuffisants.map(t => `<span class="tag-insuffisant">${t}</span>`).join('')}</div></td>
        <td class="muted">${s.nb_tests}</td>
        <td><a class="btn-voir-logs" href="logs.php?CODE_GX_SITE=${code}&nom=${nom}">📋 Logs</a></td>
    </tr>`;
}

// Construit le badge coloré d'une métrique avec icône selon le verdict
function badgeVerdict(verdict, libelle) {
    const icon = verdict === 'confort' ? ICONES.ok : verdict === 'insuffisant' ? ICONES.warn : ICONES.mid;
    return `<span class="metrique ${verdict}">${icon} ${libelle}</span>`;
}

// ── Export CSV (côté serveur) ─────────────────────────────────────────
// Redirige vers get_alertes.php?export=csv avec les filtres actifs
document.getElementById('btn-export').addEventListener('click', () => {
    const params = new URLSearchParams({
        export:   'csv',
        periode:  document.getElementById('sel-periode').value,
        metrique: document.getElementById('sel-metrique').value,
    });
    window.location.href = `/backend/ip/get_alertes.php?${params}`;
});

// ── Tri par clic sur en-tête ──────────────────────────────────────────
// Bascule asc/desc sur la même colonne, repart à la page 1
document.querySelectorAll('thead th[data-col]').forEach(th => {
    th.addEventListener('click', () => {
        directionTri = colonnesTri === th.dataset.col ? (directionTri === 'asc' ? 'desc' : 'asc') : 'desc';
        colonnesTri = th.dataset.col;
        document.querySelectorAll('thead th').forEach(t => t.classList.remove('trie-asc', 'trie-desc'));
        th.classList.add(directionTri === 'asc' ? 'trie-asc' : 'trie-desc');
        page = 1;
        filtrerEtAfficher();
    });
});

// ── Filtres ───────────────────────────────────────────────────────────
document.getElementById('sel-periode').addEventListener('change',  () => { page = 1; chargerDonnees(); });
document.getElementById('sel-metrique').addEventListener('change', () => { page = 1; filtrerEtAfficher(); });

// Debounce 250ms sur la recherche texte pour éviter de filtrer à chaque frappe
let minuteurRecherche;
document.getElementById('inp-search').addEventListener('input', () => {
    clearTimeout(minuteurRecherche);
    minuteurRecherche = setTimeout(() => { page = 1; filtrerEtAfficher(); }, 250);
});

// Filtre régression
document.getElementById('chk-regression')?.addEventListener('change', () => { page = 1; filtrerEtAfficher(); });

// Chargement initial
chargerDonnees();
// ── Aides contextuelles ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    ajouterAide(document.querySelector('#kpi-bar'), null); // pas global
    const aidesPeriode = [
        ['label[for="sel-periode"]', 'Période d\'analyse : seuls les tests effectués dans cet intervalle sont pris en compte pour calculer les moyennes.'],
        ['label[for="sel-metrique"]', 'Filtrer les sites insuffisants sur une métrique précise, ou afficher tous les sites insuffisants sur au moins une métrique.'],
        ['label[for="inp-search"]',   'Recherche en temps réel sur le code GX, le nom du site, le département, la région ou l\'interrégion.'],
    ];
    aidesPeriode.forEach(function([sel, texte]) {
        const el = document.querySelector(sel);
        if (el) ajouterAide(el, texte);
    });
});