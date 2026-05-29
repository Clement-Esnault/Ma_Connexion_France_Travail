/**
 * difference.js — v1.9.1
 *
 * Gère la page de comparaison rapide/précis.
 * Depuis v1.9.1 : les seuils sont différenciés par métrique
 * (ping / download / upload) et retournés par le backend dans data.seuils.
 */
'use strict';

const I       = id => document.getElementById(id);
const setText = (id, v) => { const el = I(id); if (el) el.textContent = v; };
const masquer = (el, v) => el?.classList.toggle('hidden', v);

// ── État global ──────────────────────────────────────────────────────
let toutesLesSessions = [];

/**
 * Seuils d'écart par métrique — mis à jour à chaque réponse du backend.
 * @type {{ ping: number, download: number, upload: number }}
 */
let seuils = { ping: 15, download: 20, upload: 30 };

let triColonne  = null;
let triAsc      = true;
let pageActuelle = 1;
const PAR_PAGE   = 20;

// ── Affichage d'une erreur ───────────────────────────────────────────
function afficherErreur(msg) {
    const boite = I('erreur-box');
    if (boite) { boite.textContent = msg; masquer(boite, false); }
    masquer(I('spinner-box'), true);
    I('btn-charger').disabled = false;
}

// ── Chargement des données ───────────────────────────────────────────
async function charger() {
    const limite = parseInt(I('limite-input').value, 10) || 100;
    const jours  = parseInt(I('jours-input').value,  10) || 0;

    masquer(I('synthese-box'), true);
    masquer(I('detail-box'),   true);
    masquer(I('erreur-box'),   true);
    masquer(I('spinner-box'),  false);
    I('btn-charger').disabled = true;

    const ctrl  = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 30000);

    try {
        const reponse = await fetch(
            `../../backend/admin/difference.php?limite=${limite}&jours=${jours}`,
            { signal: ctrl.signal }
        );
        clearTimeout(timer);
        const texte = await reponse.text();
        let donnees;
        try {
            donnees = JSON.parse(texte);
        } catch (_) {
            const apercu = texte.substring(0, 200).replace(/<[^>]+>/g, '').trim();
            afficherErreur('⚠\u00a0Réponse inattendue : ' + (apercu || 'réponse vide'));
            return;
        }
        if (donnees.error) { afficherErreur('⚠\u00a0' + donnees.error); return; }
        afficher(donnees);
    } catch (erreur) {
        clearTimeout(timer);
        afficherErreur(erreur.name === 'AbortError'
            ? '⚠\u00a0Délai dépassé — le serveur ne répond pas.'
            : '⚠\u00a0Erreur réseau : ' + erreur.message);
    }
}

// ── Affichage de la synthèse ─────────────────────────────────────────
function afficher(donnees) {
    // Mettre à jour les seuils depuis la réponse backend
    if (donnees.seuils) {
        seuils = donnees.seuils;
    }

    toutesLesSessions = donnees.sessions || [];
    triColonne        = null;
    triAsc            = true;
    pageActuelle      = 1;

    // Afficher les seuils dans la synthèse et les en-têtes de colonnes
    setText('seuil-ping-affiche', seuils.ping);
    setText('seuil-dl-affiche',   seuils.download);
    setText('seuil-ul-affiche',   seuils.upload);

    // Seuil affiché dans chaque colonne du tableau détail
    const afficherSeuilCol = (id, valeur) => {
        const el = I(id);
        if (el) el.textContent = `≤ ${valeur} %`;
    };
    afficherSeuilCol('col-seuil-ping', seuils.ping);
    afficherSeuilCol('col-seuil-dl',   seuils.download);
    afficherSeuilCol('col-seuil-ul',   seuils.upload);

    // Compteurs synthèse
    setText('s-ping-total', donnees.totaux.ping);
    setText('s-dl-total',   donnees.totaux.download);
    setText('s-ul-total',   donnees.totaux.upload);
    setText('s-ping-ok',    donnees.ok.ping);
    setText('s-dl-ok',      donnees.ok.download);
    setText('s-ul-ok',      donnees.ok.upload);
    setText('s-ping-ko',    donnees.ko.ping);
    setText('s-dl-ko',      donnees.ko.download);
    setText('s-ul-ko',      donnees.ko.upload);

    const pct = (ok, total) => total > 0 ? (ok / total * 100).toFixed(1) + '\u00a0%' : '—';
    setText('s-ping-pct', pct(donnees.ok.ping,     donnees.totaux.ping));
    setText('s-dl-pct',   pct(donnees.ok.download, donnees.totaux.download));
    setText('s-ul-pct',   pct(donnees.ok.upload,   donnees.totaux.upload));

    colorerCellule('s-ping-ko', 's-ping-ok', donnees.ko.ping,     donnees.ok.ping);
    colorerCellule('s-dl-ko',   's-dl-ok',   donnees.ko.download, donnees.ok.download);
    colorerCellule('s-ul-ko',   's-ul-ok',   donnees.ko.upload,   donnees.ok.upload);

    masquer(I('spinner-box'),  true);
    masquer(I('synthese-box'), false);

    if (toutesLesSessions.length > 0) {
        majFiltres();
        masquer(I('detail-box'), false);
    }
}

function colorerCellule(idKo, idOk, nbKo, nbOk) {
    const elKo = I(idKo), elOk = I(idOk);
    if (elKo) elKo.className = nbKo > 0 ? 'cellule-ko' : '';
    if (elOk) elOk.className = nbOk > 0 ? 'cellule-ok' : '';
}

// ── Déterminer si une session est KO ─────────────────────────────────
/**
 * Une session est KO si au moins une métrique dépasse son seuil spécifique.
 * @param {{ ping_ecart: number|null, dl_ecart: number|null, ul_ecart: number|null }} session
 * @returns {boolean}
 */
function sessionEstKo(session) {
    return (session.ping_ecart !== null && session.ping_ecart > seuils.ping)
        || (session.dl_ecart   !== null && session.dl_ecart   > seuils.download)
        || (session.ul_ecart   !== null && session.ul_ecart   > seuils.upload);
}

// ── Filtrage + tri + pagination ───────────────────────────────────────
function majFiltres() {
    const filtre = (I('filtre-site')?.value || '').toLowerCase().trim();
    const koOnly = I('filtre-ko')?.checked   || false;

    let sessions = toutesLesSessions.filter(session => {
        if (filtre && !session.site.toLowerCase().includes(filtre)) return false;
        if (koOnly && !sessionEstKo(session)) return false;
        return true;
    });

    // Tri
    if (triColonne) {
        sessions = [...sessions].sort((a, b) => {
            const va = a[triColonne] ?? Infinity;
            const vb = b[triColonne] ?? Infinity;
            return triAsc ? va - vb : vb - va;
        });
    }

    // Compteur
    const nbKo = sessions.filter(sessionEstKo).length;
    setText('detail-compteur', `${sessions.length} session(s) — ${nbKo} KO`);

    // Pagination
    const totalPages = Math.max(1, Math.ceil(sessions.length / PAR_PAGE));
    pageActuelle = Math.min(pageActuelle, totalPages);
    const debut  = (pageActuelle - 1) * PAR_PAGE;
    const page   = sessions.slice(debut, debut + PAR_PAGE);

    afficherDetail(page);
    afficherPagination(totalPages, sessions);
    majEnTetes();
}

// ── Rendu du tableau détail ───────────────────────────────────────────
/**
 * Génère le HTML d'une cellule d'écart avec seuil spécifique à la métrique.
 * @param {number} fast    Valeur test rapide
 * @param {number} precise Valeur test précis
 * @param {number|null} ecart   Écart en %
 * @param {number} seuilMetrique Seuil applicable à cette métrique
 */
function ecartHtml(fast, precise, ecart, seuilMetrique) {
    if (ecart === null || ecart === undefined) {
        return '<span class="ecart-na">—</span>';
    }
    const estOk = ecart <= seuilMetrique;
    const classe = estOk ? 'ecart-ok' : 'ecart-ko';
    const signe  = estOk ? '✓' : '✗';
    return `<div class="val-ligne">`
         + `<span class="val-fast">${fast}</span>`
         + `<span class="val-sep">→</span>`
         + `<span class="val-precise">${precise}</span>`
         + `</div>`
         + `<div class="${classe} val-ecart">${signe}\u00a0${ecart}\u00a0%</div>`;
}

function afficherDetail(sessions) {
    const tbody = I('detail-tbody');
    if (!tbody) return;
    tbody.innerHTML = sessions.map(session => {
        const estKo = sessionEstKo(session);
        return `<tr class="${estKo ? 'row-ko' : ''}">
            <td class="detail-site">${session.site}</td>
            <td class="detail-date">${session.date}</td>
            <td>${ecartHtml(session.ping_fast, session.ping_precise, session.ping_ecart, seuils.ping)}</td>
            <td>${ecartHtml(session.dl_fast,   session.dl_precise,   session.dl_ecart,   seuils.download)}</td>
            <td>${ecartHtml(session.ul_fast,   session.ul_precise,   session.ul_ecart,   seuils.upload)}</td>
        </tr>`;
    }).join('');
}

// ── Pagination ────────────────────────────────────────────────────────
function afficherPagination(totalPages, sessions) {
    ['detail-pagination-top', 'detail-pagination-bot'].forEach(id => {
        const el = I(id);
        if (!el) return;
        if (totalPages <= 1) { el.innerHTML = ''; return; }
        el.innerHTML = `
            <button onclick="allerPage(${pageActuelle - 1})" ${pageActuelle === 1 ? 'disabled' : ''}>← Préc.</button>
            <span>Page ${pageActuelle} / ${totalPages}</span>
            <button onclick="allerPage(${pageActuelle + 1})" ${pageActuelle === totalPages ? 'disabled' : ''}>Suiv. →</button>`;
    });
}

function allerPage(p) {
    pageActuelle = p;
    majFiltres();
    I('detail-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── En-têtes triables ─────────────────────────────────────────────────
function majEnTetes() {
    document.querySelectorAll('.detail-table th[data-sort]').forEach(th => {
        const col = th.dataset.sort;
        th.classList.toggle('sort-asc',  triColonne === col &&  triAsc);
        th.classList.toggle('sort-desc', triColonne === col && !triAsc);
    });
}

function trierPar(col) {
    if (triColonne === col) triAsc = !triAsc;
    else { triColonne = col; triAsc = true; }
    pageActuelle = 1;
    majFiltres();
}

// ── Export CSV ────────────────────────────────────────────────────────
function exportCSV() {
    ouvrirModalExport({
        titre: 'Export CSV — Comparaison sessions',
        type:  'csv',
        avecStats: true,
        colonnes: [
            { id: 'site',      label: 'Site',         checked: true  },
            { id: 'date',      label: 'Date',         checked: true  },
            { id: 'ping',      label: 'Ping',         checked: true  },
            { id: 'download',  label: 'Téléch.',      checked: true  },
            { id: 'upload',    label: 'Envoi',        checked: true  },
            { id: 'ecarts',    label: 'Écarts %',     checked: true  },
            { id: 'verdict',   label: 'Verdict KO',   checked: false },
        ],
        onConfirm(opts) {
            const cols = opts.colonnes;
            const sep  = opts.separateur;
            const esc  = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
            const filtre = (I('filtre-site')?.value || '').toLowerCase().trim();
            const koOnly = I('filtre-ko')?.checked || false;
            let sessions = toutesLesSessions.filter(session => {
                if (filtre && !session.site.toLowerCase().includes(filtre)) return false;
                if (koOnly && !sessionEstKo(session)) return false;
                return true;
            });

            const entetes = [
                cols.includes('site')     && 'Site',
                cols.includes('date')     && 'Date',
                cols.includes('ping')     && 'Ping rapide',
                cols.includes('ping')     && 'Ping pr\u00e9cis',
                cols.includes('ecarts')   && `\u00c9cart ping % (seuil ${seuils.ping}%)`,
                cols.includes('download') && 'DL rapide',
                cols.includes('download') && 'DL pr\u00e9cis',
                cols.includes('ecarts')   && `\u00c9cart DL % (seuil ${seuils.download}%)`,
                cols.includes('upload')   && 'UL rapide',
                cols.includes('upload')   && 'UL pr\u00e9cis',
                cols.includes('ecarts')   && `\u00c9cart UL % (seuil ${seuils.upload}%)`,
                cols.includes('verdict')  && 'KO',
            ].filter(Boolean);

            const lignes = [entetes.map(esc).join(sep)];

            if (opts.stats && sessions.length) {
                const ko = sessions.filter(s => sessionEstKo(s)).length;
                lignes.push(['# Stats'].map(esc).join(sep));
                lignes.push(['Sessions totales', 'Sessions KO', '% KO'].map(esc).join(sep));
                lignes.push([sessions.length, ko, ((ko/sessions.length)*100).toFixed(1)+'%'].map(esc).join(sep));
                lignes.push([]);
            }

            sessions.forEach(session => {
                const row = [
                    cols.includes('site')     && session.site,
                    cols.includes('date')     && session.date,
                    cols.includes('ping')     && session.ping_fast,
                    cols.includes('ping')     && session.ping_precise,
                    cols.includes('ecarts')   && (session.ping_ecart ?? ''),
                    cols.includes('download') && session.dl_fast,
                    cols.includes('download') && session.dl_precise,
                    cols.includes('ecarts')   && (session.dl_ecart ?? ''),
                    cols.includes('upload')   && session.ul_fast,
                    cols.includes('upload')   && session.ul_precise,
                    cols.includes('ecarts')   && (session.ul_ecart ?? ''),
                    cols.includes('verdict')  && (sessionEstKo(session) ? 'KO' : 'OK'),
                ].filter(v => v !== false);
                lignes.push(row.map(esc).join(sep));
            });

            _telechargerCSV(lignes, `difference_${new Date().toISOString().slice(0,10)}.csv`, sep, opts.bom);
        }
    });
}

function _telechargerCSV(lignes, nomFichier, separateur, bom) {
    const contenu = lignes.join('\r\n');
    const prefixe = bom ? '\uFEFF' : '';
    const blob = new Blob([prefixe + contenu], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const lien = Object.assign(document.createElement('a'), { href: url, download: nomFichier });
    document.body.appendChild(lien);
    lien.click();
    setTimeout(() => { URL.revokeObjectURL(url); lien.remove(); }, 1000);
}

// ── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    I('btn-charger').addEventListener('click', () => { pageActuelle = 1; charger(); });
    I('filtre-site')?.addEventListener('input',  () => { pageActuelle = 1; majFiltres(); });
    I('filtre-ko')  ?.addEventListener('change', () => { pageActuelle = 1; majFiltres(); });
    I('btn-export-detail')?.addEventListener('click', exportCSV);
    charger();
});
// ── Export PDF difference ─────────────────────────────────────────────
function _ouvrirPDFDifference() {
    ouvrirModalExport({
        titre: 'Export PDF — Comparaison sessions',
        type: 'pdf',
        avecStats: true,
        avecCouleurs: true,
        onConfirm(opts) {
            const avecCouleurs = opts.couleurs;
            const esc = v => String(v ?? '');
            const filtre = (I('filtre-site')?.value || '').toLowerCase().trim();
            const koOnly = I('filtre-ko')?.checked || false;
            const sessions = toutesLesSessions.filter(s => {
                if (filtre && !s.site.toLowerCase().includes(filtre)) return false;
                if (koOnly && !sessionEstKo(s)) return false;
                return true;
            });

            function couleurEcart(ecart, seuil) {
                if (!avecCouleurs || ecart === null || ecart === undefined) return '';
                const ko = Math.abs(+ecart) > seuil;
                return ' style="background:' + (ko ? '#f8d7da' : '#d4edda') + '"';
            }

            const lignesStats = opts.stats ? (() => {
                const ko = sessions.filter(s => sessionEstKo(s)).length;
                return '<table class="pdf-stats"><thead><tr><th>Sessions totales</th><th>Sessions KO</th><th>% KO</th></tr></thead>' +
                    '<tbody><tr><td>' + sessions.length + '</td><td>' + ko + '</td><td>' + ((ko/Math.max(sessions.length,1))*100).toFixed(1) + '%</td></tr></tbody></table>';
            })() : '';

            const lignes = sessions.map(s =>
                '<tr><td>' + esc(s.site) + '</td><td>' + esc(s.date) + '</td>' +
                '<td>' + esc(s.ping_fast) + '</td><td>' + esc(s.ping_precise) + '</td>' +
                '<td' + couleurEcart(s.ping_ecart, seuils.ping) + '>' + (s.ping_ecart ?? '-') + '%</td>' +
                '<td>' + esc(s.dl_fast) + '</td><td>' + esc(s.dl_precise) + '</td>' +
                '<td' + couleurEcart(s.dl_ecart, seuils.download) + '>' + (s.dl_ecart ?? '-') + '%</td>' +
                '<td>' + esc(s.ul_fast) + '</td><td>' + esc(s.ul_precise) + '</td>' +
                '<td' + couleurEcart(s.ul_ecart, seuils.upload) + '>' + (s.ul_ecart ?? '-') + '%</td></tr>'
            ).join('');

            const entete = document.createElement('div');
            entete.id = 'pdf-header';
            entete.innerHTML =
                '<div class="pdf-meta"><div class="pdf-meta-source">France Travail &mdash; Ma Connexion</div>' +
                '<div class="pdf-meta-title">Comparaison sessions rapide/pr&eacute;cis</div>' +
                '<div class="pdf-meta-date">Export&eacute; le ' + new Date().toLocaleDateString('fr-FR') +
                ' &mdash; ' + sessions.length + ' session(s)</div></div>' +
                lignesStats +
                '<table class="pdf-table" style="font-size:9px"><thead><tr>' +
                '<th>Site</th><th>Date</th>' +
                '<th>Ping R</th><th>Ping P</th><th>&Eacute;cart %</th>' +
                '<th>DL R</th><th>DL P</th><th>&Eacute;cart %</th>' +
                '<th>UL R</th><th>UL P</th><th>&Eacute;cart %</th>' +
                '</tr></thead><tbody>' + lignes + '</tbody></table>';

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