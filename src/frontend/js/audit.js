/**
 * frontend/js/audit.js
 *
 * Gestion de la page d'audit des modifications de sites.
 */

'use strict';

const I = (id) => document.getElementById(id);

const LABELS_ACTION = {
    AJOUT:        { icone: '➕', classe: 'badge-ajout',        texte: 'Ajout'        },
    MODIFICATION: { icone: '✏️', classe: 'badge-modification', texte: 'Modification' },
    SUPPRESSION:  { icone: '🗑️', classe: 'badge-suppression',  texte: 'Suppression'  },
};

let pageActuelle = 1;

// ── Chargement ────────────────────────────────────────────────────────
async function charger(page = 1) {
    pageActuelle = page;

    const params = new URLSearchParams({
        id_compte: I('filtre-compte').value,
        action:    I('filtre-action').value,
        jours:     I('filtre-jours').value,
        site:      I('filtre-site').value.trim(),
        page,
        limite:    25,
    });

    const tbody = I('audit-tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="audit-vide audit-chargement">Chargement…</td></tr>';

    try {
        const rep  = await fetch('../../backend/admin/get_audit.php?' + params);
        const data = await rep.json();
        afficher(data);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="audit-vide audit-erreur">Erreur lors du chargement.</td></tr>';
    }
}

// ── Affichage ─────────────────────────────────────────────────────────
function afficher(data) {
    const tbody = I('audit-tbody');
    const compteur = I('audit-compteur');

    compteur.textContent = data.total === 0
        ? 'Aucun résultat.'
        : `${data.total} entrée${data.total > 1 ? 's' : ''} — page ${data.page} / ${data.pages}`;

    if (data.resultats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="audit-vide">Aucune entrée pour ces critères.</td></tr>';
        I('audit-pagination').innerHTML = '';
        return;
    }

    tbody.innerHTML = data.resultats.map(ligne => {
        const meta  = LABELS_ACTION[ligne.ACTION] ?? { icone: '?', classe: '', texte: ligne.ACTION };
        const alias = ligne.ALIAS_COMPTE
            ? `${ligne.ALIAS_COMPTE}${ligne.IS_ADMIN ? ' <span class="badge-admin">admin</span>' : ''}`
            : `<em>ID ${ligne.id_compte ?? '?'}</em>`;

        return `<tr>
            <td class="audit-date">${formaterDate(ligne.DATE_ACTION)}</td>
            <td>${alias}</td>
            <td><span class="badge-action ${meta.classe}">${meta.icone} ${meta.texte}</span></td>
            <td class="audit-site">
                <span class="audit-code">${echapper(ligne.code_gx)}</span><br>
                <span class="audit-nom">${echapper(ligne.NOM_SITE)}</span>
            </td>
            <td class="audit-ip">${echapper(ligne.IP_ACTION)}</td>
            <td>${rendreDiff(ligne.ACTION, ligne.detail_parse)}</td>
        </tr>`;
    }).join('');

    afficherPagination(data.page, data.pages);
}

// ── Rendu du diff ─────────────────────────────────────────────────────
function rendreDiff(action, detail) {
    if (!detail) return '<span class="audit-sans-detail">—</span>';

    if (action === 'MODIFICATION') {
        const lignes = Object.entries(detail).map(([champ, valeurs]) =>
            `<tr>
                <td class="diff-champ">${echapper(champ)}</td>
                <td class="diff-avant">${formaterValeur(valeurs.avant)}</td>
                <td class="diff-fleche">→</td>
                <td class="diff-apres">${formaterValeur(valeurs.apres)}</td>
            </tr>`
        ).join('');
        return lignes
            ? `<table class="diff-table"><thead><tr>
                <th>Champ</th><th>Avant</th><th></th><th>Après</th>
               </tr></thead><tbody>${lignes}</tbody></table>`
            : '<span class="audit-sans-detail">Aucune modification détectée</span>';
    }

    if (action === 'AJOUT' && detail.ajout) {
        const champs = Object.entries(detail.ajout)
            .filter(([, v]) => v !== null && v !== '')
            .map(([k, v]) => `<li><strong>${echapper(k)}</strong> : ${formaterValeur(v)}</li>`)
            .join('');
        return `<ul class="diff-liste">${champs}</ul>`;
    }

    if (action === 'SUPPRESSION' && detail.supprime) {
        const champs = Object.entries(detail.supprime)
            .filter(([, v]) => v !== null && v !== '')
            .map(([k, v]) => `<li><strong>${echapper(k)}</strong> : ${formaterValeur(v)}</li>`)
            .join('');
        return `<ul class="diff-liste diff-liste--suppression">${champs}</ul>`;
    }

    return `<pre class="audit-json">${echapper(JSON.stringify(detail, null, 2))}</pre>`;
}

// ── Pagination ────────────────────────────────────────────────────────
function afficherPagination(page, pages) {
    const conteneur = I('audit-pagination');
    if (pages <= 1) { conteneur.innerHTML = ''; return; }

    const btnPrev = page > 1
        ? `<button class="btn-page" onclick="charger(${page - 1})">← Précédent</button>`
        : `<button class="btn-page" disabled>← Précédent</button>`;

    const btnNext = page < pages
        ? `<button class="btn-page" onclick="charger(${page + 1})">Suivant →</button>`
        : `<button class="btn-page" disabled>Suivant →</button>`;

    conteneur.innerHTML = `${btnPrev}
        <span class="pagination-info">Page ${page} / ${pages}</span>
        ${btnNext}`;
}

// ── Helpers ───────────────────────────────────────────────────────────
function echapper(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function formaterDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleDateString('fr-FR') + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function formaterValeur(v) {
    if (v === null || v === undefined) return '<em class="val-null">null</em>';
    return `<span>${echapper(String(v))}</span>`;
}

// ── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    I('btn-charger').addEventListener('click', () => charger(1));
    I('filtre-site').addEventListener('keydown', e => { if (e.key === 'Enter') charger(1); });

    const urlParams = new URLSearchParams(window.location.search);
const idCompteUrl = urlParams.get('id_compte');
if (idCompteUrl) {
    I('filtre-compte').value = idCompteUrl;
}
charger(1);
 
});
// ── Export CSV audit ──────────────────────────────────────────────────
function exporterCSVAudit() {
    ouvrirModalExport({
        titre: 'Export CSV — Journal d\'audit',
        type:  'csv',
        avecStats: false,
        colonnes: [
            { id: 'date',    label: 'Date',         checked: true  },
            { id: 'compte',  label: 'Technicien',   checked: true  },
            { id: 'action',  label: 'Action',        checked: true  },
            { id: 'site',    label: 'Site',          checked: true  },
            { id: 'ip',      label: 'IP action',     checked: true  },
            { id: 'detail',  label: 'Détail diff',   checked: false },
        ],
        onConfirm(opts) {
            const cols = opts.colonnes;
            const sep  = opts.separateur;
            const esc  = v => `"${String(v ?? '').replace(/"/g, '""')}"`;

            // Récupérer les lignes visibles dans le tbody
            const rows = Array.from(document.querySelectorAll('#audit-tbody tr'));
            if (!rows.length) { alert('Aucune donnée à exporter.'); return; }

            const entetes = [
                cols.includes('date')   && 'Date',
                cols.includes('compte') && 'Technicien',
                cols.includes('action') && 'Action',
                cols.includes('site')   && 'Site',
                cols.includes('ip')     && 'IP action',
                cols.includes('detail') && 'Détail',
            ].filter(Boolean);

            const lignes = [entetes.map(esc).join(sep)];

            rows.forEach(tr => {
                const tds = tr.querySelectorAll('td');
                if (!tds.length) return;
                const row = [
                    cols.includes('date')   && (tds[0]?.textContent?.trim() ?? ''),
                    cols.includes('compte') && (tds[1]?.textContent?.trim() ?? ''),
                    cols.includes('action') && (tds[2]?.textContent?.trim() ?? ''),
                    cols.includes('site')   && (tds[3]?.textContent?.trim() ?? ''),
                    cols.includes('ip')     && (tds[4]?.textContent?.trim() ?? ''),
                    cols.includes('detail') && (tds[5]?.textContent?.trim().replace(/\s+/g, ' ') ?? ''),
                ].filter(v => v !== false);
                lignes.push(row.map(esc).join(sep));
            });

            telechargerCSV(lignes, `audit_${new Date().toISOString().slice(0,10)}.csv`, sep, opts.bom);
        }
    });
}