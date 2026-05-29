// recherche.js — recherche de sites. Requiert utils.js chargé avant.


let pageActuelle  = 1;
let rechercheActuelle = '';
let triActuel  = { col: null, asc: true };
let donneesActuelles  = [];

// ── Recherche principale ──────────────────────────────────────────────
// Appelle site_info.php avec le terme saisi, met à jour l'URL et affiche les résultats paginés
async function rechercher(page = 1) {
    const q = document.getElementById('q').value.trim();
    if (q === '') return;
    rechercheActuelle = q;
    pageActuelle  = page;

    // Synchronise l'URL pour restaurer la recherche au retour depuis logs.php
    const p = new URLSearchParams(window.location.search);
    p.set('q', q);
    history.replaceState(null, '', '?' + p.toString());

    const tbody      = document.getElementById('tbody');
    const table      = document.getElementById('table');
    const aucunResultat  = document.getElementById('no-results');
    const pagination = document.getElementById('pagination');

    table.style.display = 'table';
    tbody.innerHTML = `<tr><td colspan="8" class="td-loading"><div class="spinner spinner--center"></div>Recherche en cours…</td></tr>`;

    try {
        const res  = await fetch('/backend/ip/site_info.php?q=' + encodeURIComponent(q) + '&page=' + page);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        tbody.innerHTML      = '';
        pagination.innerHTML = '';

        if (!data.results.length) {
            table.style.display     = 'none';
            aucunResultat.style.display = 'block';
            const compteur = document.getElementById('compteur');
            if (compteur) compteur.textContent = '';
            return;
        }

        aucunResultat.style.display = 'none';
        table.style.display     = 'table';
        donneesActuelles = data.results;
        const btnExp = document.getElementById('btn-export-recherche');
        if (btnExp) btnExp.style.display = donneesActuelles.length ? 'inline-flex' : 'none';

        let compteur = document.getElementById('compteur');
        if (!compteur) {
            compteur    = document.createElement('div');
            compteur.id = 'compteur';
            pagination.before(compteur);
        }
        compteur.textContent  = `${data.total} résultat${data.total > 1 ? 's' : ''} trouvé${data.total > 1 ? 's' : ''}`;
        compteur.className = 'compteur-resultats';

        afficherResultats(donneesActuelles);

        // creerPagination() vient de utils.js — pagination en haut et en bas du tableau
        creerPagination('pagination',        data.page, data.pages, data.total, 'résultats', p => rechercher(p));
        creerPagination('pagination-bottom', data.page, data.pages, data.total, 'résultats', p => rechercher(p));

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="td-error">⚠ Impossible de contacter le serveur.</td></tr>`;
        console.error('[recherche.js]', err);
    }
}

// ── Affichage des résultats ───────────────────────────────────────────
// Construit les lignes du tableau — le lien "Historique" n'est visible que si connecté,
// le lien "Modifier" uniquement pour les admins (variables isAdmin/estConnecte injectées en PHP)
function afficherResultats(results) {
    const tbody = document.getElementById('tbody');
    tbody.innerHTML = '';
    results.forEach(s => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${surligner(s.CODE_GX_SITE, rechercheActuelle)}</td>
            <td>${surligner(s.NOM_SITE, rechercheActuelle)}</td>
            <td>${surligner(s.CODE_POSTAL, rechercheActuelle)}</td>
            <td>${surligner(s.NOM_REGION       ?? '—', rechercheActuelle)}</td>
            <td>${surligner(s.NOM_INTERREGION  ?? '—', rechercheActuelle)}</td>
            <td>${surligner(s.IP_RESEAU        ?? '—', rechercheActuelle)}</td>
            <td>${surligner(s.MASQUE_SITE      ?? '—', rechercheActuelle)}</td>
            ${estConnecte
                ? `<td style="display:flex;gap:.4rem;flex-wrap:wrap">
                    ${s.IP_SPECIALE == 1
                        ? '<span class="badge-special">Spéciale</span>'
                        : `<a href="logs.php?CODE_GX_SITE=${s.CODE_GX_SITE}&nom=${encodeURIComponent(s.NOM_SITE)}&q=${encodeURIComponent(rechercheActuelle)}&from_page=${pageActuelle}">
                               <span class="badge-ok">Historique (${s.nb_logs})</span>
                           </a>`
                    }
                  
                         <a href="admin/modifier_site.php?CODE_GX_SITE=${s.CODE_GX_SITE}">
                               <span class="badge-edit">✏ Modifier</span>
                           </a>
                        
                   </td>`
                : ''
            }`;
        tbody.appendChild(tr);
    });
}

// ── Tri par colonne ───────────────────────────────────────────────────
// Tri alphabétique ou numérique selon la colonne cliquée — bascule asc/desc
function trierColonne(col) {
    triActuel.asc = triActuel.col === col ? !triActuel.asc : true;
    triActuel.col = col;

    const trie = [...donneesActuelles].sort((a, b) => {
        const va = (a[col] ?? '').toString().toLowerCase();
        const vb = (b[col] ?? '').toString().toLowerCase();
        if (va < vb) return triActuel.asc ? -1 : 1;
        if (va > vb) return triActuel.asc ?  1 : -1;
        return 0;
    });

    document.querySelectorAll('.results-table th[data-col]').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (th.dataset.col === col) th.classList.add(triActuel.asc ? 'sort-asc' : 'sort-desc');
    });

    afficherResultats(trie);
}

// ── Mise en surbrillance du terme recherché ───────────────────────────
// echapper() vient de utils.js — protège contre les injections XSS dans innerHTML
function surligner(texte, requete) {
    if (!texte || texte === '—') return texte;
    const safe = echapper(String(texte));
    if (!requete) return safe;
    const escaped = requete.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return safe.replace(new RegExp(`(${escaped})`, 'gi'),
        '<mark class="surlignage">$1</mark>');
}

// ── Vider la recherche ────────────────────────────────────────────────
// Remet l'interface à l'état initial et nettoie l'URL
function viderRecherche() {
    const input = document.getElementById('q');
    input.value = '';
    input.focus();
    document.getElementById('btn-clear').style.display   = 'none';
    document.getElementById('table').style.display       = 'none';
    document.getElementById('no-results').style.display  = 'none';
    const compteur = document.getElementById('compteur');
    if (compteur) compteur.textContent = '';
    donneesActuelles  = [];
    rechercheActuelle = '';
    history.replaceState(null, '', '?');
}

// ── Restauration depuis l'URL ─────────────────────────────────────────
// Si l'URL contient ?q=…, relance la recherche au chargement (retour depuis logs.php)
const paramsUrl = new URLSearchParams(window.location.search);
const rechercheDepuisUrl  = paramsUrl.get('q');
if (rechercheDepuisUrl) {
    document.getElementById('q').value = rechercheDepuisUrl;
    rechercher(parseInt(paramsUrl.get('from_page')) || 1);
}

// Focus automatique au chargement
document.addEventListener('DOMContentLoaded', () => {
    const q = document.getElementById('q');
    if (q) q.focus();
});
// ── Export CSV recherche ──────────────────────────────────────────────
function exporterCSVRecherche() {
    if (!donneesActuelles || !donneesActuelles.length) return;

    ouvrirModalExport({
        titre: 'Export CSV — Résultats de recherche',
        type:  'csv',
        avecStats: false,
        colonnes: [
            { id: 'code',        label: 'Code site',    checked: true  },
            { id: 'nom',         label: 'Nom du site',  checked: true  },
            { id: 'cp',          label: 'Code postal',  checked: true  },
            { id: 'region',      label: 'Région',       checked: true  },
            { id: 'interregion', label: 'Interrégion',  checked: false },
            { id: 'ip',          label: 'IP réseau',    checked: true  },
            { id: 'masque',      label: 'Masque',       checked: false },
        ],
        onConfirm(opts) {
            const cols = opts.colonnes;
            const sep  = opts.separateur;
            const esc  = v => `"${String(v ?? '').replace(/"/g, '""')}"`;

            const entetes = [
                cols.includes('code')        && 'Code site',
                cols.includes('nom')         && 'Nom du site',
                cols.includes('cp')          && 'Code postal',
                cols.includes('region')      && 'Région',
                cols.includes('interregion') && 'Interrégion',
                cols.includes('ip')          && 'IP réseau',
                cols.includes('masque')      && 'Masque',
            ].filter(Boolean);

            const lignes = [entetes.map(esc).join(sep)];

            donneesActuelles.forEach(s => {
                const row = [
                    cols.includes('code')        && s.CODE_GX_SITE,
                    cols.includes('nom')         && s.NOM_SITE,
                    cols.includes('cp')          && s.CODE_POSTAL,
                    cols.includes('region')      && (s.NOM_REGION ?? ''),
                    cols.includes('interregion') && (s.NOM_INTERREGION ?? ''),
                    cols.includes('ip')          && s.IP_RESEAU,
                    cols.includes('masque')      && s.MASQUE_SITE,
                ].filter(v => v !== false);
                lignes.push(row.map(esc).join(sep));
            });

            telechargerCSV(lignes, `recherche_${new Date().toISOString().slice(0,10)}.csv`, sep, opts.bom);
        }
    });
}