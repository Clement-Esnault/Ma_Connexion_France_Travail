/**
 * rapport_hebdo.js — v1.9.7
 *
 * Charge et affiche le rapport de débit réseau.
 * Période configurable via les boutons 7j / 30j / mois en cours.
 * Les verdicts des KPI nationaux sont maintenant fournis par le serveur
 * (SeuilService PHP) — plus de recalcul côté client.
 *
 * Dépend de : /backend/admin/rapport_hebdo.php
 */

'use strict';

// ── État ──────────────────────────────────────────────────────────────

/** Période active : 7 | 30 | 0 (mois en cours) */
let periodeActive = 7;

// ── Constantes ────────────────────────────────────────────────────────

const VERDICTS_LABEL = {
    confort:      { label: 'Confort',      cls: 'cell-bon'     },
    fonctionnel:  { label: 'Fonctionnel',  cls: 'cell-moyen'   },
    insuffisant:  { label: 'Insuffisant',  cls: 'cell-mauvais' },
    inconnu:      { label: '—',            cls: ''             },
};

// ── Helpers DOM ───────────────────────────────────────────────────────

/**
 * Formate une valeur numérique ou retourne '—'.
 * @param {number|string|null} val
 * @param {number} dec
 * @returns {string}
 */
const fmt = (val, dec = 2) =>
    val !== null && val !== undefined && val !== ''
        ? parseFloat(val).toFixed(dec)
        : '—';

/**
 * Génère une cellule de verdict colorée.
 * @param {string} verdict
 * @returns {string}
 */
function cellVerdict(verdict) {
    const v = VERDICTS_LABEL[verdict] ?? VERDICTS_LABEL.inconnu;
    return `<td class="${v.cls}">${v.label}</td>`;
}

/**
 * Génère le HTML d'une cellule débit + verdict inline.
 * @param {number|string} val
 * @param {string} unite
 * @param {string} verdict
 * @returns {string}
 */
function cellDebit(val, unite, verdict) {
    const v = VERDICTS_LABEL[verdict] ?? VERDICTS_LABEL.inconnu;
    return `<td class="${v.cls}">${fmt(val)} <small>${unite}</small></td>`;
}

// ── KPI nationaux ─────────────────────────────────────────────────────

/**
 * Affiche les 4 KPI nationaux.
 * Les verdicts sont fournis par le serveur (nat.verdict_*) — pas de
 * recalcul côté client pour garantir la cohérence avec SeuilService.
 *
 * @param {object} nat      Objet nationale du JSON
 * @param {number} nbSites  Nombre de sites actifs
 */
function afficherNationale(nat, nbSites) {
    const container = document.getElementById('kpi-nationale');
    if (!container) return;

    const kpis = [
        {
            label:   'Téléchargement moy.',
            valeur:  fmt(nat.moy_download),
            unite:   'Mbit/s',
            verdict: nat.verdict_download ?? 'inconnu',
            prefixe: 'kpi-dl',
        },
        {
            label:   'Envoi moy.',
            valeur:  fmt(nat.moy_upload),
            unite:   'Mbit/s',
            verdict: nat.verdict_upload ?? 'inconnu',
            prefixe: 'kpi-ul',
        },
        {
            label:   'Ping moy.',
            valeur:  fmt(nat.moy_ping),
            unite:   'ms',
            verdict: nat.verdict_ping ?? 'inconnu',
            prefixe: 'kpi-ping',
        },
        {
            label:   'Tests effectués',
            valeur:  parseInt(nat.nb_tests).toLocaleString('fr-FR'),
            unite:   `sur ${nbSites} sites actifs`,
            verdict: null,
            prefixe: 'kpi-tests',
        },
    ];

    container.innerHTML = kpis.map(k => {
        const v = k.verdict ? (VERDICTS_LABEL[k.verdict] ?? VERDICTS_LABEL.inconnu) : { cls: '' };
        return `
        <div class="rapport-kpi ${k.prefixe} ${v.cls}">
            <div class="rapport-kpi-label">${k.label}</div>
            <div class="rapport-kpi-valeur">${k.valeur}</div>
            <div class="rapport-kpi-unite">${k.unite}</div>
            ${k.verdict ? `<div class="rapport-kpi-verdict ${k.verdict}">${VERDICTS_LABEL[k.verdict]?.label ?? ''}</div>` : ''}
        </div>`;
    }).join('');
}

// ── Tableau régions ───────────────────────────────────────────────────

/**
 * Affiche le tableau des régions.
 * @param {Array} regions
 */
function afficherRegions(regions) {
    const tbody = document.getElementById('tbody-regions');
    if (!tbody) return;

    if (!regions.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty">Aucune donnée sur cette période</td></tr>';
        return;
    }

    tbody.innerHTML = regions.map(r => `
        <tr>
            <td><strong>${htmlEsc(r.NOM_REGION ?? r.nom_region ?? '—')}</strong></td>
            ${cellDebit(r.moy_download, 'Mbit/s', r.verdict_download)}
            ${cellDebit(r.moy_upload,   'Mbit/s', r.verdict_upload)}
            ${cellDebit(r.moy_ping,     'ms',     r.verdict_ping)}
            <td>${parseInt(r.nb_tests).toLocaleString('fr-FR')}</td>
            ${cellVerdict(bilanRegion(r))}
        </tr>`
    ).join('');
}

/**
 * Calcule le bilan global d'une région (pire des 3 verdicts).
 * @param {object} r
 * @returns {string}
 */
function bilanRegion(r) {
    const ordre = ['insuffisant', 'fonctionnel', 'confort', 'inconnu'];
    return [r.verdict_download, r.verdict_ping, r.verdict_upload]
        .sort((a, b) => ordre.indexOf(a) - ordre.indexOf(b))[0] ?? 'inconnu';
}

// ── Tableau sites insuffisants ────────────────────────────────────────

/**
 * Affiche les sites sous seuil.
 * @param {Array} insuffisants
 */
function afficherInsuffisants(insuffisants) {
    const tbody = document.getElementById('tbody-insuffisants');
    const badge = document.getElementById('badge-insuffisants');
    if (!tbody) return;

    if (badge) badge.textContent = insuffisants.length;

    if (!insuffisants.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty rapport-ok">✓ Aucun site sous seuil sur cette période</td></tr>';
        return;
    }

    tbody.innerHTML = insuffisants.map(s => {
        const problemes = [];
        if (s.verdict_download === 'insuffisant') problemes.push('⬇ Download');
        if (s.verdict_ping     === 'insuffisant') problemes.push('📶 Ping');
        if (s.verdict_upload   === 'insuffisant') problemes.push('⬆ Upload');

        return `<tr>
            <td><strong>${htmlEsc(s.NOM_SITE)}</strong><br><small class="muted">${htmlEsc(s.CODE_GX_SITE)}</small></td>
            <td>${htmlEsc(s.NOM_REGION ?? '—')}</td>
            ${cellDebit(s.moy_download, 'Mbit/s', s.verdict_download)}
            ${cellDebit(s.moy_upload,   'Mbit/s', s.verdict_upload)}
            ${cellDebit(s.moy_ping,     'ms',     s.verdict_ping)}
            <td>${parseInt(s.nb_tests)}</td>
            <td class="cell-mauvais">${problemes.join(' · ')}</td>
        </tr>`;
    }).join('');
}

// ── Top dégradés / bons ───────────────────────────────────────────────

/**
 * Affiche un top 5.
 * @param {Array}   sites
 * @param {string}  tbodyId
 * @param {boolean} inverse  true = plus bas = mauvais
 */
function afficherTop(sites, tbodyId, inverse) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    if (!sites.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="empty">Aucune donnée</td></tr>';
        return;
    }

    tbody.innerHTML = sites.map((s, i) => {
        const rang = inverse
            ? `<span class="rapport-rang rapport-rang--bad">${i + 1}</span>`
            : `<span class="rapport-rang rapport-rang--good">${i + 1}</span>`;
        return `<tr>
            <td>${rang} <strong>${htmlEsc(s.NOM_SITE)}</strong><br><small class="muted">${htmlEsc(s.CODE_GX_SITE)}</small></td>
            <td>${htmlEsc(s.NOM_REGION ?? '—')}</td>
            <td class="${inverse ? 'cell-mauvais' : 'cell-bon'}">${fmt(s.moy_download)} <small>Mbit/s</small></td>
        </tr>`;
    }).join('');
}

// ── Utilitaire ────────────────────────────────────────────────────────

/**
 * Échappe le HTML.
 * @param {string} str
 * @returns {string}
 */
function htmlEsc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Affiche les skeletons de chargement sur les KPI.
 */
function afficherSkeletons() {
    const container = document.getElementById('kpi-nationale');
    if (container) {
        container.innerHTML = Array(4).fill('<div class="rapport-kpi-skeleton"></div>').join('');
    }
    ['tbody-regions', 'tbody-insuffisants', 'tbody-degrades', 'tbody-bons'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<tr><td colspan="7" class="td-loading">Chargement…</td></tr>';
    });
}

// ── Sélecteur de période ──────────────────────────────────────────────

/**
 * Initialise les boutons de sélection de période.
 */
function initPeriodeSelector() {
    document.querySelectorAll('.rapport-periode-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const jours = parseInt(btn.dataset.jours);
            if (jours === periodeActive) return;

            periodeActive = jours;

            // Mise à jour visuelle des boutons
            document.querySelectorAll('.rapport-periode-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Recharger le rapport avec la nouvelle période
            afficherSkeletons();
            chargerRapport();
        });
    });
}

// ── Chargement ────────────────────────────────────────────────────────

/**
 * Charge le rapport depuis l'API et peuple la page.
 */
async function chargerRapport() {
    try {
        const url  = `/backend/admin/rapport_hebdo.php?jours=${periodeActive}`;
        const resp = await fetch(url, { credentials: 'same-origin' });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();

        // En-tête
        const periodeEl = document.getElementById('rapport-periode');
        const metaEl    = document.getElementById('rapport-meta');
        const badgeEl   = document.getElementById('badge-periode');

        if (periodeEl && data.semaine) {
            if (periodeActive === 7) {
                periodeEl.textContent = `Semaine ${data.semaine.numero}/${data.semaine.annee} — ${data.semaine.debut} au ${data.semaine.fin}`;
            } else {
                periodeEl.textContent = data.periode?.libelle ?? `${data.periode?.jours} jours`;
            }
        }
        if (metaEl    && data.semaine) metaEl.textContent   = `Généré le ${data.semaine.genere}`;
        if (badgeEl   && data.periode) badgeEl.textContent  = `${data.periode.libelle} — tests précis`;

        // KPI — verdicts fournis par le serveur, plus de recalcul JS
        afficherNationale(data.nationale, data.nb_sites_actifs);

        // Régions
        afficherRegions(data.par_region ?? []);

        // Insuffisants
        afficherInsuffisants(data.insuffisants ?? []);

        // Top dégradés / bons
        afficherTop(data.top_degrades ?? [], 'tbody-degrades', true);
        afficherTop(data.top_bons     ?? [], 'tbody-bons',     false);

    } catch (err) {
        console.error('Erreur chargement rapport', err);
        document.querySelectorAll('.td-loading').forEach(el => {
            el.textContent = 'Erreur de chargement';
            el.classList.add('td-error');
        });
    }
}

// ── Init ──────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initPeriodeSelector();
    chargerRapport();
});

// ── Aides contextuelles ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const aides = [
        ['#kpi-nationale .rapport-kpi:nth-child(1) .rapport-kpi-label', 'Débit de téléchargement moyen sur tous les sites actifs. Mesure la capacité du réseau à rapatrier des données depuis le serveur de Montpellier.'],
        ['#kpi-nationale .rapport-kpi:nth-child(2) .rapport-kpi-label', 'Débit d\'envoi moyen sur tous les sites actifs. Mesure la capacité du réseau à envoyer des données vers le serveur.'],
        ['#kpi-nationale .rapport-kpi:nth-child(3) .rapport-kpi-label', 'Latence moyenne en millisecondes. En dessous de 50 ms = bon, au-dessus de 100 ms = dégradé.'],
        ['#kpi-nationale .rapport-kpi:nth-child(4) .rapport-kpi-label', 'Nombre total de tests effectués sur la période par l\'ensemble des sites.'],
    ];

    // Observer les KPIs car ils sont générés dynamiquement
    const observer = new MutationObserver(function() {
        aides.forEach(function([sel, texte]) {
            const el = document.querySelector(sel);
            if (el && !el.querySelector('.ft-aide')) {
                ajouterAide(el, texte);
            }
        });
    });
    const grid = document.getElementById('kpi-nationale');
    if (grid) observer.observe(grid, { childList: true, subtree: true });
});