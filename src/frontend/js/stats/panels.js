import { STATE } from './state.js';
import { api } from './api.js';
import { verdictStat, couleurVerdict, pastilleHTML } from './verdicts.js';
import { creerGraphBarre, creerGraphLigne, creerGraphDoughnut, COULEURS } from './charts.js';
import { getSitesFiltres, getSitesTries, filtrerRegions, filtrerInterregions, filtrerDepts, majKPIs, majSante, majTopFlop } from './filtres.js';

/**
 * panels.js — Logique de chargement et d'affichage de chaque onglet.
 *
 * Chaque panel expose deux fonctions :
 *   - init*()     : branche les écouteurs d'événements (appelé une seule fois au démarrage)
 *   - charger*()  : charge les données via API et met à jour le DOM
 *
 * La carte Leaflet est gérée directement dans statistique.js
 * pour contourner les contraintes d'initialisation avec les panels cachés.
 */

// ══════════════════════════════════════════════════════════════════════
// PANEL SITES
// ══════════════════════════════════════════════════════════════════════

/**
 * Branche les écouteurs du panel Sites :
 *   - Champ "Rechercher un site" (filtre local au panel)
 *   - Sélecteur de tri
 */
export function initSitesFiltres() {
    let minuteur;

    document.getElementById('recherche-site').addEventListener('input', function () {
        clearTimeout(minuteur);
        minuteur = setTimeout(() => {
            STATE.rechercheSite = this.value.trim();
            afficherTableauSites();
        }, 200);
    });

    document.getElementById('tri-sites').addEventListener('change', function () {
        STATE.triSites = this.value;
        afficherTableauSites();
    });
}

/**
 * Charge les données du panel Sites depuis l'API (mise en cache dans STATE.DATA.sites).
 * Met à jour les KPIs, la santé globale, le top/flop et le tableau des sites.
 */
export async function chargerSites() {
    if (!STATE.DATA.sites) {
        document.getElementById('tbody-sites').innerHTML =
            '<tr><td colspan="7" class="td-loading"><div class="spinner spinner--center"></div>Chargement…</td></tr>';
        STATE.DATA.sites = await api('par_site');
        // Peupler tous les sélecteurs de sites (évolution, comparaison, heatmap)
        _peuplerSelecteursSites();
    }

    const sitesFiltres = getSitesFiltres();
    majKPIs(sitesFiltres);
    majSante(sitesFiltres);
    majTopFlop(sitesFiltres);
    afficherTableauSites();
}

/**
 * Peuple les sélecteurs de sites dans les panels Évolution, Comparaison et Heatmap.
 * Appelé une seule fois après le premier chargement de DATA.sites.
 */
function _peuplerSelecteursSites() {
    const sites = STATE.DATA.sites || [];

    // Sélecteurs Évolution et Comparaison
    ['select-site-evolution', 'select-site-comparaison'].forEach(id => {
        const selecteur  = document.getElementById(id);
        const premierOpt = selecteur.options[0].outerHTML;
        selecteur.innerHTML = premierOpt + sites.map(site =>
            '<option value="' + site.CODE_GX_SITE + '">' + (site.NOM_SITE || site.CODE_GX_SITE) + '</option>'
        ).join('');
    });

    // Sélecteur Heatmap horaire (format "GX001 — Caen SIR")
    const selectHeatmap  = document.getElementById('hm-site');
    const premierOptHm   = selectHeatmap.options[0].outerHTML;
    selectHeatmap.innerHTML = premierOptHm + sites.map(site =>
        '<option value="' + site.CODE_GX_SITE + '">'
        + site.CODE_GX_SITE + ' — ' + (site.NOM_SITE || '')
        + '</option>'
    ).join('');
}

/**
 * Met à jour le tableau des sites avec les données filtrées et triées.
 * Appelé à chaque changement de filtre ou de tri.
 */
export function afficherTableauSites() {
    const sites   = getSitesTries();
    const periode = STATE.periode ? STATE.periode + ' jours' : 'historique complète';

    document.getElementById('sites-subtitle').textContent = 'période : ' + periode;
    document.getElementById('sites-count').textContent    = sites.length + ' site(s)';

    if (!sites.length) {
        document.getElementById('tbody-sites').innerHTML =
            '<tr><td colspan="7" class="empty">Aucun site ne correspond.</td></tr>';
        return;
    }

    document.getElementById('tbody-sites').innerHTML = sites.map(site =>
        '<tr>'
        + '<td class="tf-nom" title="' + (site.CODE_GX_SITE || '') + '">' + (site.NOM_SITE || '') + '</td>'
        + '<td style="font-size:12px;color:var(--ft-muted)">' + (site.NOM_REGION || '—') + '</td>'
        + '<td>' + pastilleHTML('download', site.moy_download) + (+site.moy_download).toFixed(2) + '</td>'
        + '<td>' + pastilleHTML('upload',   site.moy_upload)   + (+site.moy_upload).toFixed(2)   + '</td>'
        + '<td>' + pastilleHTML('ping',     site.moy_ping)     + (+site.moy_ping).toFixed(2)     + '</td>'
        + '<td style="color:var(--ft-muted);font-size:11px">' + (+site.nb_tests).toLocaleString('fr-FR') + '</td>'
        + '<td><a href="recherche.php?q=' + encodeURIComponent(site.CODE_GX_SITE) + '" style="font-size:11px;color:var(--ft-primary)">Logs</a></td>'
        + '</tr>'
    ).join('');
}

// ══════════════════════════════════════════════════════════════════════
// PANEL DÉPARTEMENTS
// ══════════════════════════════════════════════════════════════════════

/**
 * Charge et affiche les graphiques en barres horizontales par département.
 * Un graphique pour download/upload, un pour le ping.
 */
export async function chargerDepartements() {
    if (!STATE.DATA.departements) {
        STATE.DATA.departements = await api('par_departement');
    }

    const departements = filtrerDepts(STATE.DATA.departements);
    const etiquettes   = departements.map(dept => dept.NOM_DEPARTEMENT);

    // Options communes aux deux graphiques (barres horizontales)
    const optsHorizontal = {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
            x: { grid: { color: '#eef1f9' }, beginAtZero: true },
            y: { grid: { display: false }, ticks: { font: { size: 10 } } },
        },
    };

    creerGraphBarre('chart-dept-dl-ul', etiquettes, [
        { label: 'Téléchargement (Mbit/s)', data: departements.map(d => d.moy_download), backgroundColor: '#283276cc', borderColor: '#283276', borderWidth: 1, borderRadius: 3 },
        { label: 'Envoi (Mbit/s)',           data: departements.map(d => d.moy_upload),   backgroundColor: '#406BDEcc', borderColor: '#406BDE', borderWidth: 1, borderRadius: 3 },
    ], optsHorizontal);

    creerGraphBarre('chart-dept-ping', etiquettes, [
        { label: 'Ping (ms)', data: departements.map(d => d.moy_ping), backgroundColor: '#E1000Fcc', borderColor: '#E1000F', borderWidth: 1, borderRadius: 3 },
    ], optsHorizontal);
}

// ══════════════════════════════════════════════════════════════════════
// PANEL RÉGIONS
// ══════════════════════════════════════════════════════════════════════

/**
 * Charge et affiche les graphiques par région.
 */
export async function chargerRegions() {
    if (!STATE.DATA.regions) {
        STATE.DATA.regions = await api('par_region');
    }
    afficherGraphiquesRegions(filtrerRegions(STATE.DATA.regions));
}

/**
 * Affiche les graphiques du panel Régions.
 * Ajoute des lignes de référence nationale en superposition.
 *
 * @param {Array} regions — Données régions filtrées
 */
export function afficherGraphiquesRegions(regions) {
    const etiquettes = regions.map(r => r.NOM_REGION);
    const nat        = STATE.nationale;

    // Ligne de référence nationale (dataset de type 'line' superposé aux barres)
    const ligneNationale = (libelle, valeur, couleur) => ({
        label:           libelle,
        data:            Array(regions.length).fill(valeur),
        backgroundColor: couleur + '44',
        borderColor:     couleur + '99',
        borderWidth:     1,
        type:            'line',
        pointRadius:     0,
    });

    creerGraphBarre('chart-region-dl-ul', etiquettes, [
        { label: 'Téléchargement (Mbit/s)', data: regions.map(r => r.moy_download), backgroundColor: '#283276cc', borderColor: '#283276', borderWidth: 1, borderRadius: 3 },
        { label: 'Envoi (Mbit/s)',           data: regions.map(r => r.moy_upload),   backgroundColor: '#406BDEcc', borderColor: '#406BDE', borderWidth: 1, borderRadius: 3 },
        ...(nat?.moy_download ? [ligneNationale('Nat. Télé. ' + (+nat.moy_download).toFixed(2) + ' Mbit/s', +nat.moy_download, '#283276')] : []),
        ...(nat?.moy_upload   ? [ligneNationale('Nat. Envoi ' + (+nat.moy_upload).toFixed(2)   + ' Mbit/s', +nat.moy_upload,   '#406BDE')] : []),
    ]);

    // Graphique en anneau : répartition du nombre de tests par région
    creerGraphDoughnut('chart-region-pie', etiquettes, regions.map(r => r.nb_tests));
}

// ══════════════════════════════════════════════════════════════════════
// PANEL INTERRÉGIONS
// ══════════════════════════════════════════════════════════════════════

/**
 * Charge et affiche le graphique par interrégion (SDP).
 */
export async function chargerInterregions() {
    if (!STATE.DATA.interregions) {
        STATE.DATA.interregions = await api('par_interregion');
    }
    afficherGraphiquesInterregions(filtrerInterregions(STATE.DATA.interregions));
}

/**
 * Affiche le graphique comparatif des interrégions.
 * Inclut download, upload, ping et les 3 références nationales.
 *
 * @param {Array} interregions — Données interrégions filtrées
 */
export function afficherGraphiquesInterregions(interregions) {
    const etiquettes = interregions.map(ir => ir.NOM_INTERREGION);
    const nat        = STATE.nationale;

    const ligneNationale = (libelle, valeur, couleur) => ({
        label:           libelle,
        data:            Array(interregions.length).fill(valeur),
        backgroundColor: couleur + '44',
        borderColor:     couleur + '99',
        borderWidth:     1,
        borderRadius:    4,
        type:            'line',
        pointRadius:     0,
    });

    creerGraphBarre('chart-interregion', etiquettes, [
        { label: 'Téléchargement (Mbit/s)', data: interregions.map(ir => ir.moy_download), backgroundColor: '#283276cc', borderColor: '#283276', borderWidth: 1, borderRadius: 4 },
        { label: 'Envoi (Mbit/s)',           data: interregions.map(ir => ir.moy_upload),   backgroundColor: '#406BDEcc', borderColor: '#406BDE', borderWidth: 1, borderRadius: 4 },
        { label: 'Ping (ms)',                data: interregions.map(ir => ir.moy_ping),     backgroundColor: '#E1000Fcc', borderColor: '#E1000F', borderWidth: 1, borderRadius: 4 },
        ...(nat?.moy_download ? [ligneNationale('Nat. Télé. ' + (+nat.moy_download).toFixed(2) + ' Mbit/s', +nat.moy_download, '#283276')] : []),
        ...(nat?.moy_upload   ? [ligneNationale('Nat. Envoi ' + (+nat.moy_upload).toFixed(2)   + ' Mbit/s', +nat.moy_upload,   '#406BDE')] : []),
        ...(nat?.moy_ping     ? [ligneNationale('Nat. Ping '  + (+nat.moy_ping).toFixed(2)     + ' ms',     +nat.moy_ping,     '#E1000F')] : []),
    ]);
}

// ══════════════════════════════════════════════════════════════════════
// PANEL ÉVOLUTION
// ══════════════════════════════════════════════════════════════════════

/**
 * Branche les écouteurs du panel Évolution :
 *   - Sélecteur de site et de métrique
 *   - Boutons de vue (Ligne / Heatmap mensuelle)
 */
export function initEvolution() {
    document.getElementById('select-site-evolution').addEventListener('change', chargerEvolution);
    document.getElementById('select-metric').addEventListener('change', chargerEvolution);

    document.querySelectorAll('[data-vue]').forEach(bouton => {
        bouton.addEventListener('click', () => {
            document.querySelectorAll('[data-vue]').forEach(b => b.classList.remove('active'));
            bouton.classList.add('active');
            STATE.vueEvolution = bouton.dataset.vue;
            // Afficher/masquer les conteneurs de vue
            document.getElementById('vue-ligne').style.display             = STATE.vueEvolution === 'ligne'   ? '' : 'none';
            document.getElementById('vue-heatmap-mensuelle').style.display = STATE.vueEvolution === 'heatmap' ? '' : 'none';
            chargerEvolution();
        });
    });
}

/**
 * Charge et affiche l'évolution mensuelle des débits.
 *
 * Mode 'ligne'   : graphique linéaire multi-sites
 * Mode 'heatmap' : tableau coloré site × mois
 */
export async function chargerEvolution() {
    const idSite   = document.getElementById('select-site-evolution').value;
    const metrique = document.getElementById('select-metric').value;
    const donnees  = await api('evolution', idSite ? { site_id: idSite } : {});

    if (STATE.vueEvolution === 'heatmap') {
        _afficherHeatmapMensuelle(donnees, metrique);
        return;
    }

    // Construction du graphique linéaire
    const nomsFiltre = new Set(idSite ? [] : getSitesFiltres().map(s => s.NOM_SITE));
    const parSite    = {};
    const tousMois   = new Set();

    donnees.forEach(ligne => {
        // Si un filtre global est actif et qu'on n'a pas sélectionné un site précis,
        // exclure les sites hors du filtre
        if (!idSite && STATE.recherche && !nomsFiltre.has(ligne.NOM_SITE)) return;

        if (!parSite[ligne.NOM_SITE]) parSite[ligne.NOM_SITE] = {};
        parSite[ligne.NOM_SITE][ligne.mois] = ligne[metrique];
        tousMois.add(ligne.mois);
    });

    const mois      = Array.from(tousMois).sort();
    const nomsSites = Object.keys(parSite);

    creerGraphLigne('chart-evolution', mois, nomsSites.map((nomSite, index) => ({
        label:           nomSite,
        data:            mois.map(m => parSite[nomSite][m] ?? null),
        borderColor:     COULEURS[index % COULEURS.length],
        backgroundColor: COULEURS[index % COULEURS.length] + '22',
        borderWidth:     2,
        pointRadius:     3,
        fill:            false,
        spanGaps:        true,
    })));
}

/**
 * Affiche la heatmap mensuelle (tableau site × mois coloré par verdict).
 * Vue alternative au graphique linéaire dans l'onglet Évolution.
 *
 * @param {Array}  donnees  — Données brutes de l'API evolution
 * @param {string} metrique — Colonne à afficher ('moy_download' | 'moy_upload' | 'moy_ping')
 */
function _afficherHeatmapMensuelle(donnees, metrique) {
    const parSite    = {};
    const tousMois   = new Set();
    const nomsFiltre = new Set(getSitesFiltres().map(s => s.NOM_SITE));

    donnees.forEach(ligne => {
        if (STATE.recherche && !nomsFiltre.has(ligne.NOM_SITE)) return;
        if (!parSite[ligne.NOM_SITE]) parSite[ligne.NOM_SITE] = {};
        parSite[ligne.NOM_SITE][ligne.mois] = +ligne[metrique];
        tousMois.add(ligne.mois);
    });

    const mois      = Array.from(tousMois).sort();
    const nomsSites = Object.keys(parSite);
    const conteneur = document.getElementById('heatmap-container');

    if (!nomsSites.length) {
        conteneur.innerHTML = '<div class="empty">Aucune donnée.</div>';
        return;
    }

    // Correspondance métrique SQL → clé de verdict
    const cleVerdict = metrique === 'moy_download' ? 'download'
                     : metrique === 'moy_upload'   ? 'upload' : 'ping';
    const unite      = metrique === 'moy_ping' ? 'ms' : 'Mbit/s';

    // Classe CSS selon le verdict de la cellule
    const classCellule = valeur => {
        if (valeur == null) return 'hm-vide';
        const verdict = verdictStat(cleVerdict, valeur);
        return verdict === 'confort'      ? 'cell-bon'
             : verdict === 'fonctionnel'  ? 'cell-moyen'
             : verdict === 'insuffisant'  ? 'cell-mauvais'
             : 'hm-neutre';
    };

    conteneur.innerHTML =
        '<table class="heatmap-table"><thead><tr><th class="hm-site-th">Site</th>'
        + mois.map(m => '<th class="hm-th">' + m + '</th>').join('')
        + '</tr></thead><tbody>'
        + nomsSites.map(nomSite =>
            '<tr><td class="hm-site">' + nomSite + '</td>'
            + mois.map(m => {
                const valeur = parSite[nomSite][m];
                return '<td class="hm-td ' + classCellule(valeur) + '">'
                     + (valeur != null ? valeur.toFixed(2) : '—')
                     + '</td>';
            }).join('')
            + '</tr>'
        ).join('')
        + '</tbody></table>'
        + '<div style="font-size:11px;color:var(--ft-muted);margin-top:8px">Unité : ' + unite + '</div>';
}

// ══════════════════════════════════════════════════════════════════════
// PANEL COMPARAISON
// ══════════════════════════════════════════════════════════════════════

/**
 * Branche l'écouteur du sélecteur de site du panel Comparaison.
 */
export function initComparaison() {
    document.getElementById('select-site-comparaison').addEventListener('change', chargerComparaison);
}

/**
 * Charge et affiche la comparaison précis vs rapide.
 * Génère un tableau de synthèse et un graphique en barres groupées.
 */
export async function chargerComparaison() {
    const idSite    = document.getElementById('select-site-comparaison').value;
    const conteneur = document.getElementById('comparaison-container');
    conteneur.innerHTML = '<div class="loader"><div class="spinner"></div> Chargement…</div>';

    const donnees = await api('comparaison_modes', idSite ? { site_id: idSite } : {});
    if (!donnees?.length) {
        conteneur.innerHTML = '<div class="empty">Aucune donnée disponible.</div>';
        return;
    }

    const modePrecis = donnees.find(r => r.MODE === 'precise') || null;

    const formaterVal   = (ligne, cle) => ligne ? (+ligne[cle]).toFixed(2) : '—';
    const formaterCount = (ligne)      => ligne ? (+ligne.nb_tests).toLocaleString('fr-FR') : '—';

    const lignesTableau = [
        ['Ping (ms)',               'moy_ping',     'ecart_type_ping'],
        ['Téléchargement (Mbit/s)', 'moy_download', 'ecart_type_download'],
        ['Envoi (Mbit/s)',          'moy_upload',   'ecart_type_upload'],
    ];

    conteneur.innerHTML =
        '<table class="comparaison-table"><thead><tr>'
        + '<th></th><th>📊 Résultats</th><th>± Écart-type</th>'
        + '</tr></thead><tbody>'
        + lignesTableau.map(([libelle, cle, cleEcart]) =>
            '<tr><td class="comp-label">' + libelle + '</td>'
            + '<td>' + formaterVal(modePrecis, cle)     + '</td>'
            + '<td class="comp-sub">' + formaterVal(modePrecis, cleEcart) + '</td></tr>'
        ).join('')
        + '<tr><td class="comp-label">Nb tests</td>'
        + '<td>' + formaterCount(modePrecis) + '</td>'
        + '<td class="comp-sub">—</td></tr>'
        + '</tbody></table>';

    creerGraphBarre('chart-comparaison',
        ['Ping (ms)', 'Téléchargement (Mbit/s)', 'Envoi (Mbit/s)'],
        [
            {
                label:           '📊 Résultats',
                data:            modePrecis ? [+modePrecis.moy_ping, +modePrecis.moy_download, +modePrecis.moy_upload] : [0, 0, 0],
                backgroundColor: '#283276cc', borderColor: '#283276', borderWidth: 1, borderRadius: 4,
            },
        ]
    );
}

// ══════════════════════════════════════════════════════════════════════
// PANEL ALERTES
// ══════════════════════════════════════════════════════════════════════

/**
 * Branche le bouton "Appliquer" du panel Alertes.
 */
export function initAlertes() {
    document.getElementById('btn-appliquer-alertes').addEventListener('click', () => {
        STATE.alertes.seuilDl   = +document.getElementById('seuil-dl').value;
        STATE.alertes.seuilPing = +document.getElementById('seuil-ping').value;
        chargerAlertes();
    });
}

/**
 * Charge et affiche les sites en alerte selon les seuils courants.
 * Applique le filtre de recherche globale si actif.
 */
export async function chargerAlertes() {
    const conteneur = document.getElementById('alertes-container');
    conteneur.innerHTML = '<div class="loader"><div class="spinner"></div> Chargement…</div>';

    let sites = await api('alertes', {
        dl_seuil:   STATE.alertes.seuilDl,
        ping_seuil: STATE.alertes.seuilPing,
    });

    // Appliquer le filtre de recherche globale côté client
    if (STATE.recherche) {
        const terme = STATE.recherche.toLowerCase();
        sites = sites.filter(site =>
            ((STATE.portee === 'all' || STATE.portee === 'site')        && ((site.CODE_GX_SITE || '').toLowerCase().includes(terme) || (site.NOM_SITE || '').toLowerCase().includes(terme))) ||
            ((STATE.portee === 'all' || STATE.portee === 'region')      && (site.NOM_REGION || '').toLowerCase().includes(terme)) ||
            ((STATE.portee === 'all' || STATE.portee === 'interregion') && (site.NOM_INTERREGION || '').toLowerCase().includes(terme))
        );
    }

    if (!sites.length) {
        conteneur.innerHTML = '<div class="empty">✓ Aucun site en alerte.</div>';
        return;
    }

    conteneur.innerHTML =
        '<table class="alertes-table"><thead><tr>'
        + '<th>Code</th><th>Site</th><th>Région</th>'
        + '<th>Téléchargement</th><th>Envoi</th><th>Ping</th>'
        + '<th>Tests</th><th>Actions</th>'
        + '</tr></thead><tbody>'
        + sites.map(site => {
            // Badge rouge si en dessous du seuil, vert sinon
            const classeDl   = +site.moy_download < STATE.alertes.seuilDl   ? 'badge-crit' : 'badge-ok';
            const classePing = +site.moy_ping     > STATE.alertes.seuilPing ? 'badge-warn' : 'badge-ok';
            return '<tr>'
                + '<td>' + (site.CODE_GX_SITE || '') + '</td>'
                + '<td>' + (site.NOM_SITE     || '') + '</td>'
                + '<td>' + (site.NOM_REGION   || '') + '</td>'
                + '<td><span class="badge-alerte ' + classeDl   + '">' + (+site.moy_download).toFixed(2) + ' Mbit/s</span></td>'
                + '<td><span class="badge-alerte ' + classeDl   + '">' + (+site.moy_upload).toFixed(2)   + ' Mbit/s</span></td>'
                + '<td><span class="badge-alerte ' + classePing + '">' + (+site.moy_ping).toFixed(2)     + ' ms</span></td>'
                + '<td>' + (+site.nb_tests).toLocaleString('fr-FR') + '</td>'
                + '<td><a href="recherche.php?q=' + encodeURIComponent(site.CODE_GX_SITE) + '" class="btn-logs">Voir</a></td>'
                + '</tr>';
        }).join('')
        + '</tbody></table>';
}

// ── Carte Leaflet ─────────────────────────────────────────────────────
// Gérée directement dans statistique.js pour éviter les problèmes
// d'initialisation liés aux panels cachés (display:none au chargement).

// ══════════════════════════════════════════════════════════════════════
// PANEL HEATMAP HORAIRE
// ══════════════════════════════════════════════════════════════════════

/**
 * Branche les écouteurs des 4 sélecteurs de la heatmap horaire
 * (site, métrique, période, mode).
 */
export function initHeatmap() {
    ['hm-site', 'hm-metrique', 'hm-periode', 'hm-mode'].forEach(id => {
        document.getElementById(id).addEventListener('change', function () {
            // Mettre à jour STATE.hm en extrayant la clé après le préfixe 'hm-'
            STATE.hm[id.replace('hm-', '')] = this.value;
            chargerHeatmap();
        });
    });
}

/**
 * Charge et affiche la heatmap horaire (débit moyen par heure × jour).
 * Affiche un spinner pendant le chargement.
 */
export async function chargerHeatmap() {
    document.getElementById('hm-loading').style.display = '';
    document.getElementById('hm-empty').style.display   = 'none';
    document.getElementById('hm-wrap').style.display    = 'none';

    const donnees = await api('heatmap_horaire', {
        site:       STATE.hm.site,
        metrique:   STATE.hm.metrique,
        hm_periode: STATE.hm.periode,
        mode:       STATE.hm.mode,
    });

    document.getElementById('hm-loading').style.display = 'none';

    if (!donnees?.length) {
        document.getElementById('hm-empty').style.display = '';
        return;
    }

    _afficherHeatmapHoraire(donnees);
    document.getElementById('hm-wrap').style.display = '';
}

/**
 * Construit et injecte le tableau heatmap heure × jour de la semaine.
 *
 * Structure de l'index : clé = "heure_jourMySQL" (ex: "9_3" = 9h mardi)
 * DAYOFWEEK MySQL : 2 = lundi, 3 = mardi, …, 6 = vendredi
 *
 * @param {Array} donnees — Données brutes de l'API heatmap_horaire
 */
function _afficherHeatmapHoraire(donnees) {
    const JOURS  = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven'];
    const HEURES = Array.from({ length: 13 }, (_, i) => i + 7); // 7h → 19h

    // Indexation rapide des données pour éviter les recherches en O(n) dans la boucle
    const index     = {};
    let totalTests  = 0;

    donnees.forEach(enreg => {
        index[enreg.heure + '_' + enreg.jour_semaine] = {
            valeur: +enreg.valeur,
            nb:     +enreg.nb,
        };
        totalTests += +enreg.nb;
    });

    // En-tête : Heure | Lun | Mar | Mer | Jeu | Ven
    document.getElementById('hm-thead').innerHTML =
        '<tr><th class="hm-th hm-site-th">Heure</th>'
        + JOURS.map(j => '<th class="hm-th">' + j + '</th>').join('')
        + '</tr>';

    // Corps : une ligne par heure, une cellule par jour
    document.getElementById('hm-tbody').innerHTML = HEURES.map(heure =>
        '<tr><td class="hm-site-th">' + heure + 'h</td>'
        + [2, 3, 4, 5, 6].map(jourMySQL => {
            const cellule = index[heure + '_' + jourMySQL];
            if (!cellule) return '<td class="hm-cell hm-vide" title="Aucune donnée">—</td>';

            const verdict  = verdictStat(STATE.hm.metrique, cellule.valeur);
            const classeCss = verdict === 'confort'     ? 'hm-confort'
                            : verdict === 'fonctionnel' ? 'hm-fonctionnel'
                            : verdict === 'insuffisant' ? 'hm-insuffisant'
                            : '';
            const unite = STATE.hm.metrique === 'ping' ? 'ms' : 'Mbit/s';

            return '<td class="hm-cell ' + classeCss + '"'
                 + ' title="' + cellule.valeur.toFixed(2) + ' ' + unite + ' — ' + cellule.nb + ' test(s)">'
                 + '<span class="hm-val">' + cellule.valeur.toFixed(2) + '</span>'
                 + '<span class="hm-nb">'  + cellule.nb                + '</span>'
                 + '</td>';
        }).join('')
        + '</tr>'
    ).join('');

    // Résumé sous le tableau
    const unite = STATE.hm.metrique === 'ping' ? 'ms' : 'Mbit/s';
    document.getElementById('hm-info').textContent =
        donnees.length + ' créneaux avec données — '
        + totalTests + ' tests au total — valeurs en ' + unite;
}