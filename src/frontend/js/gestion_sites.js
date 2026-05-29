/**
 * gestion_sites.js
 *
 * Gestion des sites France Travail : ajout et suppression.
 * Exposé dans l'objet global GS pour éviter la pollution du scope global.
 *
 * Dépendances :
 *   /backend/admin/ajouter_site.php
 *   /backend/admin/supprimer_site.php
 *   /backend/ip/site_info.php
 *   /backend/ip/get_logs.php
 */

'use strict';

const GS = (() => {

    // ── Constantes ────────────────────────────────────────────────────
    const API_AJOUTER   = '/backend/admin/ajouter_site.php';
    const API_SUPPRIMER = '/backend/admin/supprimer_site.php';
    const API_RECHERCHE = '/backend/ip/site_info.php';
    const API_LOGS      = '/backend/ip/get_logs.php';

    const VERDICTS = {
        confort:      { label: 'Confort',      cls: 'verdict-confort'      },
        fonctionnel:  { label: 'Fonctionnel',  cls: 'verdict-fonctionnel'  },
        insuffisant:  { label: 'Insuffisant',  cls: 'verdict-insuffisant'  },
    };

    // ── État recherche ────────────────────────────────────────────────
    let _siteASupprimer = null;
    let _rechercheTimer = null;
    let _rechercheQ     = '';
    let _recherchePage  = 1;

    // ── Onglets ───────────────────────────────────────────────────────
    function changerOnglet(tab, btn) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.onglet').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tab)?.classList.remove('hidden');
        btn.classList.add('active');
    }

    // ══════════════════════════════════════════════════════════════════
    // AJOUT
    // ══════════════════════════════════════════════════════════════════

    async function ajouterSite() {
        const btn = document.getElementById('btn-ajouter');
        afficherMsg('ajouter', null);
        masquerConflit();

        const code    = val('aj-code').toUpperCase();
        const dept    = val('aj-dept');
        const nom     = val('aj-nom');
        const ip      = val('aj-ip');
        const masque  = val('aj-masque');
        const adresse = val('aj-adresse');
        const cp      = val('aj-cp');
        const ville   = val('aj-ville');
        const lat     = val('aj-lat');
        const lng     = val('aj-lng');

        if (!code || !nom || !dept) {
            afficherMsg('ajouter', 'error', '⚠ Code GX, nom et département sont obligatoires.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Création…';

        try {
            const rep = await post(API_AJOUTER, {
                CODE_GX_SITE:   code,
                NOM_SITE:       nom,
                ID_DEPARTEMENT: dept   ? parseInt(dept)     : null,
                IP_RESEAU:      ip     || null,
                MASQUE_SITE:    masque ? parseInt(masque)   : null,
                ADRESSE:        adresse || null,
                CODE_POSTAL:    cp      || null,
                VILLE:          ville   || null,
                LATITUDE:       lat    ? parseFloat(lat)    : null,
                LONGITUDE:      lng    ? parseFloat(lng)    : null,
            });

            if (rep.success) {
                afficherMsg('ajouter', 'ok', `✓ ${rep.message}`);
                reinitialiserFormAjout();
            } else if (rep.conflit) {
                afficherConflit(rep.conflit, ip, masque);
            } else {
                afficherMsg('ajouter', 'error', '⚠ ' + rep.error);
            }
        } catch (_) {
            afficherMsg('ajouter', 'error', '⚠ Erreur réseau — réessayez.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Créer le site';
        }
    }

    function afficherConflit(conflit, ipNouv, masqueNouv) {
        const box = document.getElementById('msg-conflit-box');
        box.innerHTML = `
            <strong>⚠ Chevauchement de plage IP détecté</strong><br>
            La plage <code>${esc(ipNouv)}/${esc(masqueNouv)}</code> chevauche le site existant :<br>
            <span class="conflit-site">
                <strong>${esc(conflit.CODE_GX_SITE)}</strong> — ${esc(conflit.NOM_SITE)}
                &nbsp;·&nbsp; <code>${esc(conflit.IP_RESEAU)}/${esc(conflit.MASQUE_SITE)}</code>
            </span>
            <br><small>Modifiez l'IP/masque ou corrigez d'abord le site existant.</small>
        `;
        box.classList.remove('hidden');
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function masquerConflit() {
        const box = document.getElementById('msg-conflit-box');
        box.classList.add('hidden');
        box.innerHTML = '';
    }

    function reinitialiserFormAjout() {
        ['aj-code','aj-nom','aj-ip','aj-masque','aj-adresse','aj-cp','aj-ville','aj-lat','aj-lng']
            .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        const dept = document.getElementById('aj-dept');
        if (dept) dept.value = '';
        masquerConflit();
    }

    // ══════════════════════════════════════════════════════════════════
    // RECHERCHE + PAGINATION
    // ══════════════════════════════════════════════════════════════════

    function rechercherSite(q) {
        clearTimeout(_rechercheTimer);
        _rechercheQ    = q.trim();
        _recherchePage = 1;

        const clear = document.getElementById('suppr-clear');
        if (clear) clear.classList.toggle('hidden', !_rechercheQ);

        if (_rechercheQ.length < 2) {
            viderResultats();
            return;
        }
        _rechercheTimer = setTimeout(() => chargerPage(1), 300);
    }

    function viderRecherche() {
        const input = document.getElementById('suppr-recherche');
        if (input) input.value = '';
        _rechercheQ = '';
        document.getElementById('suppr-clear')?.classList.add('hidden');
        viderResultats();
        annulerSuppression();
    }

    function viderResultats() {
        document.getElementById('suppr-resultats')?.classList.add('hidden');
        document.getElementById('suppr-resultats-header')?.classList.add('hidden');
        document.getElementById('suppr-pagination')?.classList.add('hidden');
        const box = document.getElementById('suppr-resultats');
        if (box) box.innerHTML = '';
    }

    async function chargerPage(page) {
        _recherchePage = page;
        const box    = document.getElementById('suppr-resultats');
        const header = document.getElementById('suppr-resultats-header');
        const pagBox = document.getElementById('suppr-pagination');

        // Indicateur de chargement
        box.innerHTML = '<div class="suppr-loading"><span class="spinner-search"></span> Recherche…</div>';
        box.classList.remove('hidden');
        header.classList.add('hidden');
        pagBox.classList.add('hidden');

        try {
            const data = await fetch(
                `${API_RECHERCHE}?q=${encodeURIComponent(_rechercheQ)}&page=${page}`
            ).then(r => r.json());

            const sites = data.results ?? [];
            const total = data.total   ?? 0;
            const pages = data.pages   ?? 1;

            if (!sites.length) {
                box.innerHTML = `
                    <div class="suppr-no-result">
                        <span class="no-result-icon">🔍</span>
                        Aucun site trouvé pour « ${esc(_rechercheQ)} »
                    </div>`;
                return;
            }

            // En-tête
            document.getElementById('suppr-total-label').textContent =
                `${total} site${total > 1 ? 's' : ''} trouvé${total > 1 ? 's' : ''}`;
            header.classList.remove('hidden');

            // Résultats enrichis
            box.innerHTML = sites.map(s => renderSiteRow(s)).join('');

            // Pagination
            if (pages > 1) {
                pagBox.innerHTML = renderPagination(page, pages, total);
                pagBox.classList.remove('hidden');
            }

        } catch (_) {
            box.innerHTML = '<div class="suppr-no-result">⚠ Erreur réseau — réessayez.</div>';
        }
    }

    /**
     * Génère le HTML d'une ligne de résultat enrichie.
     * Affiche : code GX, nom, ville, département, région, IP/masque, nb_logs, verdict.
     */
    function renderSiteRow(s) {
        const verdict = s.dernier_verdict ? VERDICTS[s.dernier_verdict] : null;
        const badgeVerdict = verdict
            ? `<span class="verdict-badge ${verdict.cls}">${verdict.label}</span>`
            : `<span class="verdict-badge verdict-aucun">Aucun test</span>`;

        const ipInfo = (s.IP_RESEAU && s.MASQUE_SITE)
            ? `<span class="site-row-ip">🌐 ${esc(s.IP_RESEAU)}/${esc(s.MASQUE_SITE)}</span>`
            : `<span class="site-row-ip site-row-ip--speciale">⭐ Site spécial</span>`;

        const nbLogs = parseInt(s.nb_logs ?? 0);
        const logsInfo = nbLogs > 0
            ? `<span class="site-row-logs site-row-logs--warn">📋 ${nbLogs.toLocaleString('fr-FR')} log${nbLogs > 1 ? 's' : ''}</span>`
            : `<span class="site-row-logs site-row-logs--ok">📋 Aucun log</span>`;

        const localisation = [s.VILLE, s.NOM_DEPARTEMENT, s.NOM_REGION]
            .filter(Boolean).join(' · ');

        const codeEsc = escAttr(s.CODE_GX_SITE);
        const nomEsc  = escAttr(s.NOM_SITE);
        const villeEsc= escAttr(s.VILLE ?? '');

        return `
            <div class="suppr-site-row" role="listitem"
                 onclick="GS.selectionnerSite('${codeEsc}', '${nomEsc}', '${villeEsc}')"
                 tabindex="0"
                 onkeydown="if(event.key==='Enter'||event.key===' ') GS.selectionnerSite('${codeEsc}','${nomEsc}','${villeEsc}')">
                <div class="site-row-main">
                    <span class="suppr-code">${esc(s.CODE_GX_SITE)}</span>
                    <span class="suppr-nom">${esc(s.NOM_SITE)}</span>
                    ${badgeVerdict}
                </div>
                <div class="site-row-meta">
                    <span class="site-row-localisation">📍 ${esc(localisation)}</span>
                    ${ipInfo}
                    ${logsInfo}
                </div>
            </div>`;
    }

    function renderPagination(page, totalPages, total) {
        const delta = 2;
        const debut = Math.max(1, page - delta);
        const fin   = Math.min(totalPages, page + delta);
        let html = `<span class="pag-info">${total} résultat${total > 1 ? 's' : ''}</span>`;

        html += `<button class="pag-btn" ${page === 1 ? 'disabled' : ''}
                         onclick="GS._chargerPage(${page - 1})" aria-label="Page précédente">‹</button>`;

        if (debut > 1) {
            html += `<button class="pag-btn" onclick="GS._chargerPage(1)">1</button>`;
            if (debut > 2) html += `<span class="pag-ellipsis">…</span>`;
        }

        for (let i = debut; i <= fin; i++) {
            html += `<button class="pag-btn ${i === page ? 'pag-active' : ''}"
                             onclick="GS._chargerPage(${i})" aria-label="Page ${i}"
                             ${i === page ? 'aria-current="page"' : ''}>${i}</button>`;
        }

        if (fin < totalPages) {
            if (fin < totalPages - 1) html += `<span class="pag-ellipsis">…</span>`;
            html += `<button class="pag-btn" onclick="GS._chargerPage(${totalPages})">${totalPages}</button>`;
        }

        html += `<button class="pag-btn" ${page === totalPages ? 'disabled' : ''}
                         onclick="GS._chargerPage(${page + 1})" aria-label="Page suivante">›</button>`;

        return html;
    }

    // ══════════════════════════════════════════════════════════════════
    // SUPPRESSION
    // ══════════════════════════════════════════════════════════════════

    function selectionnerSite(code, nom, ville) {
        _siteASupprimer = { code, nom };

        // Masquer les résultats, remplir la barre
        document.getElementById('suppr-resultats')?.classList.add('hidden');
        document.getElementById('suppr-resultats-header')?.classList.add('hidden');
        document.getElementById('suppr-pagination')?.classList.add('hidden');

        const input = document.getElementById('suppr-recherche');
        if (input) { input.value = `${code} — ${nom}`; input.blur(); }

        afficherMsg('suppr', null);

        // Carte provisoire pendant le chargement des logs
        const card = document.getElementById('suppr-site-card');
        if (card) {
            card.innerHTML = `
                <div class="site-card-loading">
                    <span class="spinner-search"></span> Chargement des informations…
                </div>`;
        }

        document.getElementById('suppr-confirm-box')?.classList.remove('hidden');
        document.getElementById('suppr-confirm-box')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Charger les détails complets via site_info + nb logs
        Promise.all([
            fetch(`${API_RECHERCHE}?q=${encodeURIComponent(code)}&page=1`).then(r => r.json()),
            fetch(`${API_LOGS}?CODE_GX_SITE=${encodeURIComponent(code)}&limit=1`).then(r => r.json()),
        ]).then(([infoData, logsData]) => {
            const site  = infoData.results?.[0] ?? null;
            const nbLogs = parseInt(logsData.total ?? 0);
            if (card) card.innerHTML = renderSiteCard(site, nom, ville, nbLogs);
        }).catch(() => {
            if (card) card.innerHTML = renderSiteCard(null, nom, ville, null);
        });
    }

    function renderSiteCard(site, nomFallback, villeFallback, nbLogs) {
        const nom  = site?.NOM_SITE  ?? nomFallback;
        const code = site?.CODE_GX_SITE ?? _siteASupprimer?.code ?? '—';

        const ipLine = (site?.IP_RESEAU && site?.MASQUE_SITE)
            ? `${site.IP_RESEAU}/${site.MASQUE_SITE}`
            : 'Site spécial (sans IP)';

        const localisation = site
            ? [site.VILLE, site.NOM_DEPARTEMENT, site.NOM_REGION].filter(Boolean).join(' · ')
            : villeFallback;

        const logsHtml = nbLogs === null
            ? '<span class="card-logs-loading">Chargement…</span>'
            : nbLogs > 0
                ? `<span class="card-logs-warn">⚠ <strong>${nbLogs.toLocaleString('fr-FR')} mesure${nbLogs > 1 ? 's' : ''}</strong> seront supprimées</span>`
                : `<span class="card-logs-ok">✓ Aucune mesure associée</span>`;

        const verdict = site?.dernier_verdict ? VERDICTS[site.dernier_verdict] : null;
        const badgeVerdict = verdict
            ? `<span class="verdict-badge ${verdict.cls}">${verdict.label}</span>`
            : '';

        return `
            <div class="site-card">
                <div class="site-card-header">
                    <span class="site-card-code">${esc(code)}</span>
                    <span class="site-card-nom">${esc(nom)}</span>
                    ${badgeVerdict}
                </div>
                <div class="site-card-details">
                    <div class="site-card-row">📍 ${esc(localisation || '—')}</div>
                    <div class="site-card-row">🌐 ${esc(ipLine)}</div>
                    <div class="site-card-row">${logsHtml}</div>
                </div>
            </div>`;
    }

    function annulerSuppression() {
        _siteASupprimer = null;
        document.getElementById('suppr-confirm-box')?.classList.add('hidden');
        const input = document.getElementById('suppr-recherche');
        if (input) input.value = _rechercheQ;
        // Réafficher les résultats si une recherche était active
        if (_rechercheQ.length >= 2) {
            chargerPage(_recherchePage);
        }
        afficherMsg('suppr', null);
    }

    async function confirmerSuppression() {
        if (!_siteASupprimer) return;
        const btn = document.getElementById('btn-confirmer-suppr');
        btn.disabled = true;
        btn.textContent = 'Suppression…';

        try {
            const rep = await post(API_SUPPRIMER, { CODE_GX_SITE: _siteASupprimer.code });

            if (rep.success) {
                document.getElementById('suppr-confirm-box')?.classList.add('hidden');
                document.getElementById('suppr-recherche').value = '';
                _rechercheQ = '';
                document.getElementById('suppr-clear')?.classList.add('hidden');
                viderResultats();
                afficherMsg('suppr', 'ok', `✓ ${rep.message}`);
                _siteASupprimer = null;
            } else {
                afficherMsg('suppr', 'error', '⚠ ' + rep.error);
            }
        } catch (_) {
            afficherMsg('suppr', 'error', '⚠ Erreur réseau — réessayez.');
        } finally {
            btn.disabled = false;
            btn.textContent = '🗑 Supprimer définitivement';
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // UTILITAIRES
    // ══════════════════════════════════════════════════════════════════

    /** Valeur trimée d'un input par id */
    function val(id) { return (document.getElementById(id)?.value ?? '').trim(); }

    /** POST JSON → JSON */
    async function post(url, data) {
        const garde = activerGardePage();
        try {
            const r = await fetch(url, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(data),
            });
            return r.json();
        } finally {
            garde.desactiver();
        }
    }

    /** Échappe HTML pour affichage dans innerHTML */
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /** Échappe pour attribut HTML inline onclick='...' */
    function escAttr(str) {
        return String(str ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    /** Affiche ou masque un message de feedback */
    function afficherMsg(zone, type, texte) {
        const ok  = document.getElementById(`msg-${zone}-ok`);
        const err = document.getElementById(`msg-${zone}-error`);
        if (!ok || !err) return;
        ok.style.display  = 'none';
        err.style.display = 'none';
        if (!type) return;
        const el = type === 'ok' ? ok : err;
        el.textContent = texte ?? '';
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── API publique ──────────────────────────────────────────────────
    return {
        ajouterSite,
        rechercherSite,
        viderRecherche,
        selectionnerSite,
        annulerSuppression,
        confirmerSuppression,
        _chargerPage: chargerPage,  // appelé depuis le HTML de pagination
    };

})();

// changerOnglet est appelé directement depuis le HTML
function changerOnglet(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.onglet').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab)?.classList.remove('hidden');
    btn.classList.add('active');
}
// ══════════════════════════════════════════════════════════════════════
// SUPPRESSION GROUPÉE (BULK)
// ══════════════════════════════════════════════════════════════════════

(() => {
    // ── État ──────────────────────────────────────────────────────────
    let _bulkQ       = '';
    let _bulkPage    = 1;
    let _bulkTimer   = null;
    let _bulkSites   = new Map(); // code → { code, nom }

    // ── Recherche ─────────────────────────────────────────────────────
    function bulkRechercher(q) {
        clearTimeout(_bulkTimer);
        _bulkQ    = q.trim();
        _bulkPage = 1;

        const clear = document.getElementById('bulk-clear');
        if (clear) clear.classList.toggle('hidden', !_bulkQ);

        if (_bulkQ.length < 2) {
            _bulkViderResultats();
            return;
        }
        _bulkTimer = setTimeout(() => _bulkChargerPage(1), 300);
    }

    function bulkViderRecherche() {
        const input = document.getElementById('bulk-recherche');
        if (input) input.value = '';
        _bulkQ = '';
        document.getElementById('bulk-clear')?.classList.add('hidden');
        _bulkViderResultats();
    }

    function _bulkViderResultats() {
        document.getElementById('bulk-resultats')?.classList.add('hidden');
        document.getElementById('bulk-resultats-header')?.classList.add('hidden');
        document.getElementById('bulk-pagination')?.classList.add('hidden');
        const box = document.getElementById('bulk-resultats');
        if (box) box.innerHTML = '';
    }

    async function _bulkChargerPage(page) {
        _bulkPage = page;
        const box    = document.getElementById('bulk-resultats');
        const header = document.getElementById('bulk-resultats-header');
        const pagBox = document.getElementById('bulk-pagination');

        box.innerHTML = '<div class="suppr-loading"><span class="spinner-search"></span> Recherche…</div>';
        box.classList.remove('hidden');
        header.classList.add('hidden');
        pagBox.classList.add('hidden');

        try {
            const data = await fetch(
                `/backend/ip/site_info.php?q=${encodeURIComponent(_bulkQ)}&page=${page}`
            ).then(r => r.json());

            const sites  = data.results ?? [];
            const total  = data.total   ?? 0;
            const pages  = data.pages   ?? 1;

            if (!sites.length) {
                box.innerHTML = '<div class="suppr-no-result"><span class="no-result-icon">🔍</span> Aucun site trouvé</div>';
                return;
            }

            document.getElementById('bulk-total-label').textContent =
                `${total} site${total > 1 ? 's' : ''} trouvé${total > 1 ? 's' : ''}`;
            header.classList.remove('hidden');

            box.innerHTML = sites.map(s => _bulkRenderLigne(s)).join('');

            if (pages > 1) {
                // Réutilise renderPagination via l'API publique
                pagBox.innerHTML = GS._renderPaginationBulk(page, pages, total);
                pagBox.classList.remove('hidden');
            }

        } catch (_) {
            box.innerHTML = '<div class="suppr-no-result">⚠ Erreur réseau — réessayez.</div>';
        }
    }

    function _bulkRenderLigne(s) {
        const coche  = _bulkSites.has(s.CODE_GX_SITE);
        const codeE  = esc(s.CODE_GX_SITE);
        const nomE   = esc(s.NOM_SITE);
        const loc    = [s.VILLE, s.NOM_DEPARTEMENT, s.NOM_REGION].filter(Boolean).join(' · ');
        const ip     = (s.IP_RESEAU && s.MASQUE_SITE) ? `${s.IP_RESEAU}/${s.MASQUE_SITE}` : 'Site spécial';
        const nbLogs = parseInt(s.nb_logs ?? 0);
        const logsHtml = nbLogs > 0
            ? `<span class="site-row-logs site-row-logs--warn">📋 ${nbLogs.toLocaleString('fr-FR')} log${nbLogs > 1 ? 's' : ''}</span>`
            : `<span class="site-row-logs site-row-logs--ok">📋 Aucun log</span>`;

        const verdict = s.dernier_verdict
            ? `<span class="verdict-badge verdict-${s.dernier_verdict}">${s.dernier_verdict.charAt(0).toUpperCase() + s.dernier_verdict.slice(1)}</span>`
            : `<span class="verdict-badge verdict-aucun">Aucun test</span>`;

        return `
            <div class="suppr-site-row bulk-site-row ${coche ? 'bulk-selected' : ''}" role="listitem"
                 onclick="GS.bulkToggle('${escAttr(s.CODE_GX_SITE)}', '${escAttr(s.NOM_SITE)}')"
                 tabindex="0"
                 onkeydown="if(event.key==='Enter'||event.key===' ') GS.bulkToggle('${escAttr(s.CODE_GX_SITE)}','${escAttr(s.NOM_SITE)}')">
                <input type="checkbox" class="bulk-checkbox" ${coche ? 'checked' : ''}
                       aria-label="Sélectionner ${nomE}" onclick="event.stopPropagation();"
                       onchange="GS.bulkToggle('${escAttr(s.CODE_GX_SITE)}', '${escAttr(s.NOM_SITE)}')">
                <div class="bulk-site-info">
                    <div class="site-row-main">
                        <span class="suppr-code">${codeE}</span>
                        <span class="suppr-nom">${nomE}</span>
                        ${verdict}
                    </div>
                    <div class="site-row-meta">
                        <span class="site-row-localisation">📍 ${esc(loc)}</span>
                        <span class="site-row-ip">🌐 ${esc(ip)}</span>
                        ${logsHtml}
                    </div>
                </div>
            </div>`;
    }

    // ── Sélection ─────────────────────────────────────────────────────
    function bulkToggle(code, nom) {
        if (_bulkSites.has(code)) {
            _bulkSites.delete(code);
        } else {
            _bulkSites.set(code, { code, nom });
        }
        // Mettre à jour la checkbox et la classe dans les résultats affichés
        const rows = document.querySelectorAll('#bulk-resultats .bulk-site-row');
        rows.forEach(row => {
            const cb = row.querySelector('.bulk-checkbox');
            // Identifier la ligne par le code dans le texte
            if (row.querySelector('.suppr-code')?.textContent === code) {
                row.classList.toggle('bulk-selected', _bulkSites.has(code));
                if (cb) cb.checked = _bulkSites.has(code);
            }
        });
        _bulkMajRecap();
    }

    function bulkToutCocher() {
        const rows = document.querySelectorAll('#bulk-resultats .bulk-site-row');
        rows.forEach(row => {
            const code = row.querySelector('.suppr-code')?.textContent ?? '';
            const nom  = row.querySelector('.suppr-nom')?.textContent  ?? '';
            if (code) _bulkSites.set(code, { code, nom });
            row.classList.add('bulk-selected');
            const cb = row.querySelector('.bulk-checkbox');
            if (cb) cb.checked = true;
        });
        _bulkMajRecap();
    }

    function bulkDeselectTout() {
        _bulkSites.clear();
        document.querySelectorAll('#bulk-resultats .bulk-site-row').forEach(row => {
            row.classList.remove('bulk-selected');
            const cb = row.querySelector('.bulk-checkbox');
            if (cb) cb.checked = false;
        });
        _bulkMajRecap();
    }

    function _bulkMajRecap() {
        const recap   = document.getElementById('bulk-recap');
        const nbEl    = document.getElementById('bulk-nb-selectionnes');
        const listeEl = document.getElementById('bulk-liste-selectionnes');
        const nb = _bulkSites.size;

        if (!recap) return;
        recap.classList.toggle('hidden', nb === 0);
        if (nbEl)    nbEl.textContent = String(nb);
        if (listeEl) {
            listeEl.innerHTML = Array.from(_bulkSites.values())
                .map(s => `<span class="bulk-tag">${esc(s.code)} — ${esc(s.nom)}</span>`)
                .join('');
        }
    }

    // ── Suppression en série ──────────────────────────────────────────
    async function bulkConfirmer() {
        if (_bulkSites.size === 0) return;

        const btn = document.getElementById('btn-bulk-confirmer');
        btn.disabled    = true;
        btn.textContent = `Suppression en cours… 0 / ${_bulkSites.size}`;

        const sites  = Array.from(_bulkSites.values());
        let ok = 0, ko = 0, erreurs = [];

        for (let i = 0; i < sites.length; i++) {
            const { code, nom } = sites[i];
            btn.textContent = `Suppression en cours… ${i + 1} / ${sites.length}`;
            try {
                const rep = await fetch('/backend/admin/supprimer_site.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ CODE_GX_SITE: code }),
                }).then(r => r.json());

                if (rep.success) {
                    ok++;
                    _bulkSites.delete(code);
                } else {
                    ko++;
                    erreurs.push(`${code} : ${rep.error}`);
                }
            } catch (_) {
                ko++;
                erreurs.push(`${code} : erreur réseau`);
            }
        }

        btn.disabled    = false;
        btn.textContent = '🗑 Supprimer les sites sélectionnés';

        // Feedback
        const msgOk  = document.getElementById('msg-bulk-ok');
        const msgErr = document.getElementById('msg-bulk-error');
        msgOk.style.display  = 'none';
        msgErr.style.display = 'none';

        if (ok > 0) {
            msgOk.textContent  = `✓ ${ok} site${ok > 1 ? 's' : ''} supprimé${ok > 1 ? 's' : ''} avec succès.`;
            msgOk.style.display = 'block';
        }
        if (ko > 0) {
            msgErr.textContent  = `⚠ ${ko} erreur${ko > 1 ? 's' : ''} : ${erreurs.join(' | ')}`;
            msgErr.style.display = 'block';
        }

        _bulkMajRecap();
        // Recharger les résultats pour retirer les sites supprimés
        if (_bulkQ.length >= 2) _bulkChargerPage(_bulkPage);
    }

    // ── Helpers locaux ────────────────────────────────────────────────
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(str) {
        return String(str ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    // ── Exposition publique dans GS ────────────────────────────────────
    Object.assign(GS, {
        bulkRechercher,
        bulkViderRecherche,
        bulkToggle,
        bulkToutCocher,
        bulkDeselectTout,
        bulkConfirmer,
        _renderPaginationBulk: (page, totalPages, total) => {
            // Réutilise la même logique de pagination que l'onglet supprimer
            const delta = 2;
            const debut = Math.max(1, page - delta);
            const fin   = Math.min(totalPages, page + delta);
            let html = `<span class="pag-info">${total} résultat${total > 1 ? 's' : ''}</span>`;
            html += `<button class="pag-btn" ${page === 1 ? 'disabled' : ''} onclick="GS._bulkChargerPage(${page - 1})" aria-label="Page précédente">‹</button>`;
            if (debut > 1) {
                html += `<button class="pag-btn" onclick="GS._bulkChargerPage(1)">1</button>`;
                if (debut > 2) html += `<span class="pag-ellipsis">…</span>`;
            }
            for (let i = debut; i <= fin; i++) {
                html += `<button class="pag-btn ${i === page ? 'pag-active' : ''}" onclick="GS._bulkChargerPage(${i})">${i}</button>`;
            }
            if (fin < totalPages) {
                if (fin < totalPages - 1) html += `<span class="pag-ellipsis">…</span>`;
                html += `<button class="pag-btn" onclick="GS._bulkChargerPage(${totalPages})">${totalPages}</button>`;
            }
            html += `<button class="pag-btn" ${page === totalPages ? 'disabled' : ''} onclick="GS._bulkChargerPage(${page + 1})" aria-label="Page suivante">›</button>`;
            return html;
        },
        _bulkChargerPage,
    });
})();