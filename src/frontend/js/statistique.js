/**
 * statistique.js — v1.9.8
 *
 * Point d'entrée du dashboard statistiques.
 *
 * ── Architecture ─────────────────────────────────────────────────────────────
 * Le dashboard est découpé en modules ES6 dans js/stats/ :
 *   state.js   → STATE partagé (période, mode, données en cache, seuils, filtres)
 *   api.js     → fetch vers stat.php avec gestion d'erreur centralisée
 *   filtres.js → filtrage/tri local des données déjà chargées (pas de re-fetch)
 *   panels.js  → rendu de chaque onglet (Chart.js, tableaux, heatmap, Leaflet)
 *
 * Ce fichier orchestre : init des listeners, chargement initial, navigation
 * entre onglets. La carte Leaflet est gérée ici (pas dans panels.js) car
 * son initialisation dépend d'un timing précis lié au panel caché au chargement.
 *
 * ── Flux de données ──────────────────────────────────────────────────────────
 *   1. chargerSeuils() + chargerNationale() en parallèle (Promise.all)
 *   2. chargerOnglet('sites') — onglet par défaut
 *   3. Changement d'onglet → chargerOnglet(tab) → module panels.js
 *   4. Changement de filtre → surFiltreChange() → re-rendu local sans re-fetch
 *      (les données sont mises en cache dans STATE.DATA par clé d'onglet)
 *
 * Dépend de : stat.php, get_heatmap.php, get_seuils.php
 */

import { STATE }                                        from './stats/state.js';
import { api }                                          from './stats/api.js';
import { majKPIs, majSante, majTopFlop,
         majBandeauFiltre, majCompteurSites,
         majBandeauNationale,
         getSitesFiltres, filtrerRegions,
         filtrerInterregions, filtrerDepts }            from './stats/filtres.js';
import { initSitesFiltres, chargerSites, afficherTableauSites,
         chargerDepartements, chargerRegions, afficherGraphiquesRegions,
         chargerInterregions, afficherGraphiquesInterregions,
         initEvolution, chargerEvolution,
         initComparaison, chargerComparaison,
         initAlertes, chargerAlertes,
         initHeatmap, chargerHeatmap }                  from './stats/panels.js';

// ── Init ──────────────────────────────────────────────────────────────
// Ordre d'init important :
//   - les filtres/onglets sont initialisés avant le premier chargement
//   - seuils + nationale chargés en parallèle (Promise.all) pour éviter
//     deux requêtes séquentielles qui doubleraient le temps de chargement
//   - l'onglet 'sites' est chargé en dernier pour bénéficier des seuils
document.addEventListener('DOMContentLoaded', async () => {
    initOnglets();
    initRecherche();
    initPeriode();
    initMode();
    initSitesFiltres();
    initEvolution();
    initComparaison();
    initAlertes();
    initCarteLocale();
    initHeatmap();

    await Promise.all([chargerSeuils(), chargerNationale()]);
    await chargerOnglet('sites');
});

// ── Onglets ───────────────────────────────────────────────────────────
function initOnglets() {
    document.querySelectorAll('#tabs-nav .tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#tabs-nav .tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
            document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
            STATE.ongletActif = btn.dataset.tab;
            chargerOnglet(btn.dataset.tab);
        });
    });
}

async function chargerOnglet(tab) {
    const map = {
        sites:        chargerSites,
        departements: chargerDepartements,
        regions:      chargerRegions,
        interregions: chargerInterregions,
        evolution:    chargerEvolution,
        comparaison:  chargerComparaison,
        alertes:      chargerAlertes,
        carte:        chargerCarteLocale,
        heatmap:      chargerHeatmap,
    };
    await map[tab]?.();
}

// ── Recherche & filtres globaux ───────────────────────────────────────
function initRecherche() {
    const input    = document.getElementById('recherche-globale');
    const btnReset = document.getElementById('btn-reset-recherche');
    let debounce;

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            STATE.recherche = input.value.trim();
            btnReset.style.display = STATE.recherche ? '' : 'none';
            surFiltreChange();
        }, 200);
    });
    input.addEventListener('keydown', e => { if (e.key === 'Escape') reinitialiserRecherche(); });
    btnReset.addEventListener('click', reinitialiserRecherche);

    document.querySelectorAll('#scope-btns .scope-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#scope-btns .scope-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            STATE.portee = btn.dataset.val;
            surFiltreChange();
        });
    });
}

function reinitialiserRecherche() {
    STATE.recherche = '';
    STATE.portee    = 'all';
    document.getElementById('recherche-globale').value = '';
    document.getElementById('btn-reset-recherche').style.display = 'none';
    document.querySelectorAll('#scope-btns .scope-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.val === 'all');
    });
    surFiltreChange();
}

function initPeriode() {
    document.getElementById('select-periode').addEventListener('change', async function () {
        STATE.periode   = this.value;
        STATE.DATA      = {};
        STATE.nationale = null;
        await chargerNationale();
        chargerOnglet(STATE.ongletActif);
    });
}

function initMode() {
    document.querySelectorAll('.mode-filter-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            document.querySelectorAll('.mode-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            STATE.mode      = btn.dataset.mode;
            STATE.DATA      = {};
            STATE.nationale = null;
            await chargerNationale();
            chargerOnglet(STATE.ongletActif);
        });
    });
}

/**
 * Réapplique tous les filtres actifs et re-rend l'onglet courant.
 *
 * Appelée à chaque changement de filtre (recherche, scope, période, mode).
 * Ne re-fetche PAS les données — filtre uniquement STATE.DATA déjà chargé.
 * Exception : evolution/comparaison/alertes re-fetchent car elles ont
 * leur propre logique de pagination/paramètre côté serveur.
 */
function surFiltreChange() {
    const filtered = getSitesFiltres();
    majKPIs(filtered);
    majSante(filtered);
    majTopFlop(filtered);
    majBandeauFiltre();
    majBandeauNationale();
    majCompteurSites();

    const tab = STATE.ongletActif;
    if (tab === 'sites')        afficherTableauSites();
    if (tab === 'evolution')    chargerEvolution();
    if (tab === 'comparaison')  chargerComparaison();
    if (tab === 'alertes')      chargerAlertes();
    if (tab === 'regions')      afficherGraphiquesRegions(filtrerRegions(STATE.DATA.regions || []));
    if (tab === 'departements') chargerDepartements();
    if (tab === 'interregions') afficherGraphiquesInterregions(filtrerInterregions(STATE.DATA.interregions || []));
    if (tab === 'carte')        afficherCarteLocale();
}

// ── Seuils & nationale ────────────────────────────────────────────────
/**
 * Charge les seuils depuis la BDD et les injecte dans STATE.seuils.
 * Met aussi à jour les inputs de filtre DL/Ping dans l'onglet alertes.
 * Appelé une seule fois au démarrage — les seuils ne changent pas en cours
 * de session (toute modification via admin/seuils.php nécessite un rechargement).
 */
async function chargerSeuils() {
    const data = await api('seuils');
    if (!data?.length) return;
    STATE.seuils = data;
    data.forEach(s => {
        if (s.NOM_SEUIL === 'download') {
            STATE.alertes.seuilDl = s.VALEUR_BONNE;
            document.getElementById('seuil-dl').value = s.VALEUR_BONNE;
        }
        if (s.NOM_SEUIL === 'ping') {
            STATE.alertes.seuilPing = s.VALEUR_MAUVAISE;
            document.getElementById('seuil-ping').value = s.VALEUR_MAUVAISE;
        }
    });
}

/**
 * Charge les moyennes nationales France Travail (toutes agences confondues).
 * Utilisées comme référence dans les graphiques Chart.js (ligne de base).
 * Guard STATE.nationale : ne re-fetche pas si déjà chargé pour la session.
 * Invalidé à chaque changement de période ou de mode (initPeriode/initMode).
 */
async function chargerNationale() {
    if (STATE.nationale) return;
    STATE.nationale = await api('nationale');
    majBandeauNationale();
}

// ══════════════════════════════════════════════════════════════════════
// CARTE LEAFLET (choroplèthe + marqueurs de sites)
//
// Pourquoi ici et pas dans panels.js ?
//   Leaflet exige que le conteneur #carte-leaflet soit visible lors de
//   l.map(). Avec les modules ES6, le panel est caché (display:none) au
//   premier import de panels.js, ce qui rend la carte vide. En gardant
//   le code ici et en appellant invalidateSize() après affichage, on
//   contourne ce comportement.
//
// _carteFond      : instance L.map (créée une seule fois, réutilisée)
// _coucheActuelle : couche GeoJSON active (régions ou départements)
// _marqueurs      : layer group des cercles de sites
// _cacheGeojson   : GeoJSON déjà chargés, évite les re-fetches
// _granularite    : 'sites' | 'regions' | 'departements'
// _metrique       : 'moy_download' | 'moy_upload' | 'moy_ping'
// ══════════════════════════════════════════════════════════════════════
let _carteFond      = null;
let _coucheActuelle = null;
let _marqueurs      = null;
let _cacheGeojson   = {};
let _granularite    = 'sites';
let _metrique       = 'moy_download';

const URLS_GEOJSON = {
    regions:      'geojson/regions-version-simplifiee.geojson',
    departements: 'geojson/departements-version-simplifiee.geojson',
};

function _verdictStat(metrique, valeur) {
    if (!STATE.seuils.length) return null;
    const row = STATE.seuils.find(s => s.NOM_SEUIL === metrique);
    if (!row) return null;
    const bon = +row.VALEUR_BONNE, mauvais = +row.VALEUR_MAUVAISE;
    if (metrique === 'ping') {
        if (valeur <= bon)     return 'confort';
        if (valeur >= mauvais) return 'insuffisant';
        return 'fonctionnel';
    }
    if (valeur >= bon)     return 'confort';
    if (valeur <= mauvais) return 'insuffisant';
    return 'fonctionnel';
}

function _couleurVerdict(verdict) {
    if (verdict === 'confort')     return { fill: '#1a7a3c', border: '#145a2e' };
    if (verdict === 'fonctionnel') return { fill: '#f0c040', border: '#b8860b' };
    if (verdict === 'insuffisant') return { fill: '#E1000F', border: '#a0000a' };
    return { fill: '#B0BFF0', border: '#8090c0' };
}

/**
 * Normalise une chaîne pour la recherche insensible aux accents et casse.
 * Utilisé pour faire correspondre les noms de régions/départements du GeoJSON
 * (souvent sans accents) avec les données BDD (avec accents).
 */
function _normaliser(chaine) {
    return (chaine ?? '').toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]/g, ' ').trim();
}

function initCarteLocale() {
    document.querySelectorAll('[data-granule]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-granule]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            _granularite = btn.dataset.granule;
            afficherCarteLocale();
        });
    });
    document.querySelectorAll('[data-metrique]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-metrique]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            _metrique = btn.dataset.metrique;
            afficherCarteLocale();
        });
    });
}

async function chargerCarteLocale() {
    if (!_carteFond) {
        _carteFond = L.map('carte-leaflet', { zoomControl: true, scrollWheelZoom: true })
            .setView([46.5, 2.5], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap', maxZoom: 10
        }).addTo(_carteFond);
    }
    await afficherCarteLocale();
    // invalidateSize() recalcule les dimensions de la carte après que
    // le panel est devenu visible — sans ça, la tuile de fond est mal centrée.
    setTimeout(() => _carteFond.invalidateSize(), 200);
}

async function afficherCarteLocale() {
    if (!_carteFond) return;
    if (_coucheActuelle) { _carteFond.removeLayer(_coucheActuelle); _coucheActuelle = null; }
    if (_marqueurs)      { _carteFond.removeLayer(_marqueurs);      _marqueurs      = null; }

    if (_granularite === 'sites') {
        if (!STATE.DATA.sites) STATE.DATA.sites = await api('par_site');
        const filtered = STATE.recherche ? getSitesFiltres() : STATE.DATA.sites;
        const metrique = _metrique === 'moy_download' ? 'download'
                       : _metrique === 'moy_upload'   ? 'upload' : 'ping';
        _marqueurs = L.layerGroup();
        filtered.forEach(site => {
            const lat = parseFloat(site.LATITUDE);
            const lng = parseFloat(site.LONGITUDE);
            if (!lat || !lng) return;
            const val     = +site[_metrique];
            const verdict = _verdictStat(metrique, val);
            const c       = _couleurVerdict(verdict);
            const inactif = site.dernier_test && ((Date.now() - new Date(site.dernier_test)) > 30 * 86400 * 1000);
            const icon    = L.divIcon({
                html: `<div style="width:14px;height:14px;background:${c.fill};border:2px solid ${c.border};border-radius:50%;opacity:${inactif ? 0.4 : 0.9};box-shadow:0 1px 3px rgba(0,0,0,0.3)"></div>`,
                className: '', iconSize: [14, 14], iconAnchor: [7, 7],
            });
            const infoAdresse  = site.ADRESSE ? `<br><span style="color:#666;font-size:11px">${site.ADRESSE}, ${site.CODE_POSTAL} ${site.VILLE}</span>` : '';
            const badgeInactif = inactif ? `<br><span style="color:#888;font-size:11px">⚠️ Dernier test : ${site.dernier_test?.slice(0, 10) ?? '?'}</span>` : '';
            L.marker([lat, lng], { icon }).bindPopup(
                `<strong>${site.NOM_SITE}</strong>${infoAdresse}${badgeInactif}<br>`
                + `<span style="color:#666;font-size:11px">${site.NOM_REGION || ''} — ${site.NOM_INTERREGION || ''}</span><br><br>`
                + `📥 Télé. : ${(+site.moy_download).toFixed(2)} Mbit/s<br>`
                + `📤 Envoi : ${(+site.moy_upload).toFixed(2)} Mbit/s<br>`
                + `📶 Ping : ${(+site.moy_ping).toFixed(2)} ms<br>`
                + `🔢 Tests : ${(+site.nb_tests).toLocaleString('fr-FR')}`
            ).addTo(_marqueurs);
        });
        _marqueurs.addTo(_carteFond);
        // invalidateSize() recalcule les dimensions de la carte après que
    // le panel est devenu visible — sans ça, la tuile de fond est mal centrée.
    setTimeout(() => _carteFond.invalidateSize(), 200);
        return;
    }

    if (!STATE.DATA.regions)      STATE.DATA.regions      = await api('par_region');
    if (!STATE.DATA.departements) STATE.DATA.departements = await api('par_departement');

    if (!_cacheGeojson[_granularite]) {
        const r = await fetch(URLS_GEOJSON[_granularite]);
        _cacheGeojson[_granularite] = await r.json();
    }

    const geojson = _cacheGeojson[_granularite];
    const source  = _granularite === 'regions' ? STATE.DATA.regions : STATE.DATA.departements;
    const cleNom  = _granularite === 'regions' ? 'NOM_REGION' : 'NOM_DEPARTEMENT';
    const index   = {};
    source.forEach(r => { index[_normaliser(r[cleNom])] = r; });

    _coucheActuelle = L.geoJSON(geojson, {
        style: feature => {
            const d = index[_normaliser(feature.properties.nom)];
            if (!d) return { fillColor: '#e0e0e0', color: '#aaa', weight: 1, fillOpacity: 0.7 };
            const m = _metrique === 'moy_download' ? 'download' : _metrique === 'moy_upload' ? 'upload' : 'ping';
            const c = _couleurVerdict(_verdictStat(m, +d[_metrique]));
            const inactif = d.dernier_test && ((Date.now() - new Date(d.dernier_test)) > 30 * 86400 * 1000);
            return { fillColor: c.fill, color: inactif ? '#888' : c.border, weight: inactif ? 1 : 1.5, fillOpacity: inactif ? 0.25 : 0.75, dashArray: inactif ? '5,5' : null };
        },
        onEachFeature: (feature, layer) => {
            const d = index[_normaliser(feature.properties.nom)];
            if (!d) { layer.bindPopup(`<strong>${feature.properties.nom}</strong><br>Aucune donnée`); return; }
            const inactif      = d.dernier_test && ((Date.now() - new Date(d.dernier_test)) > 30 * 86400 * 1000);
            const badgeInactif = inactif ? `<br><span style="color:#888;font-size:11px">⚠️ Dernier test : ${d.dernier_test?.slice(0, 10) ?? '?'}</span>` : '';
            layer.bindPopup(
                `<strong>${feature.properties.nom}</strong>${badgeInactif}<br>`
                + `📥 Télé. : ${(+d.moy_download).toFixed(2)} Mbit/s<br>`
                + `📤 Envoi : ${(+d.moy_upload).toFixed(2)} Mbit/s<br>`
                + `📶 Ping : ${(+d.moy_ping).toFixed(2)} ms<br>`
                + `🔢 Tests : ${(+d.nb_tests).toLocaleString('fr-FR')}`
            );
            layer.on('mouseover', function () { this.setStyle({ fillOpacity: 0.95, weight: 2.5 }); });
            layer.on('mouseout',  function () { _coucheActuelle.resetStyle(this); });
        },
    }).addTo(_carteFond);
    // invalidateSize() recalcule les dimensions de la carte après que
    // le panel est devenu visible — sans ça, la tuile de fond est mal centrée.
    setTimeout(() => _carteFond.invalidateSize(), 200);
}
// ── Export CSV / PDF statistiques ─────────────────────────────────────

/**
 * Retourne les données et la config de colonnes selon l'onglet actif.
 */
function _getExportContext() {
    const onglet = STATE.ongletActif;

    const cfgOnglets = {
        sites: {
            label:   'Par site',
            data:    () => STATE.DATA.sites || [],
            colonnes: [
                { id: 'code',       label: 'Code GX',          checked: true  },
                { id: 'nom',        label: 'Nom du site',       checked: true  },
                { id: 'cp',         label: 'Code postal',       checked: false },
                { id: 'region',     label: 'Région',            checked: true  },
                { id: 'interreg',   label: 'Interrégion',       checked: false },
                { id: 'ping',       label: 'Ping moy. (ms)',    checked: true  },
                { id: 'ecart_ping', label: 'Écart-type ping',   checked: false },
                { id: 'dl',         label: 'DL moy. (Mbit/s)',  checked: true  },
                { id: 'ecart_dl',   label: 'Écart-type DL',     checked: false },
                { id: 'ul',         label: 'UL moy. (Mbit/s)',  checked: true  },
                { id: 'ecart_ul',   label: 'Écart-type UL',     checked: false },
                { id: 'nb',         label: 'Nb tests',          checked: true  },
            ],
            toRow: (s, cols) => [
                cols.includes('code')       && s.CODE_GX_SITE,
                cols.includes('nom')        && s.NOM_SITE,
                cols.includes('cp')         && s.CODE_POSTAL,
                cols.includes('region')     && (s.NOM_REGION ?? ''),
                cols.includes('interreg')   && (s.NOM_INTERREGION ?? ''),
                cols.includes('ping')       && s.moy_ping,
                cols.includes('ecart_ping') && s.ecart_type_ping,
                cols.includes('dl')         && s.moy_download,
                cols.includes('ecart_dl')   && s.ecart_type_download,
                cols.includes('ul')         && s.moy_upload,
                cols.includes('ecart_ul')   && s.ecart_type_upload,
                cols.includes('nb')         && s.nb_tests,
            ].filter(v => v !== false),
        },
        regions: {
            label:   'Par région',
            data:    () => STATE.DATA.regions || [],
            colonnes: [
                { id: 'region',   label: 'Région',           checked: true },
                { id: 'interreg', label: 'Interrégion',      checked: true },
                { id: 'ping',     label: 'Ping moy. (ms)',   checked: true },
                { id: 'dl',       label: 'DL moy. (Mbit/s)', checked: true },
                { id: 'ul',       label: 'UL moy. (Mbit/s)', checked: true },
                { id: 'nb',       label: 'Nb tests',         checked: true },
            ],
            toRow: (r, cols) => [
                cols.includes('region')   && r.NOM_REGION,
                cols.includes('interreg') && (r.NOM_INTERREGION ?? ''),
                cols.includes('ping')     && r.moy_ping,
                cols.includes('dl')       && r.moy_download,
                cols.includes('ul')       && r.moy_upload,
                cols.includes('nb')       && r.nb_tests,
            ].filter(v => v !== false),
        },
        departements: {
            label:   'Par département',
            data:    () => STATE.DATA.departements || [],
            colonnes: [
                { id: 'dept',     label: 'Département',      checked: true },
                { id: 'region',   label: 'Région',           checked: true },
                { id: 'ping',     label: 'Ping moy. (ms)',   checked: true },
                { id: 'dl',       label: 'DL moy. (Mbit/s)', checked: true },
                { id: 'ul',       label: 'UL moy. (Mbit/s)', checked: true },
                { id: 'nb',       label: 'Nb tests',         checked: true },
            ],
            toRow: (d, cols) => [
                cols.includes('dept')   && d.NOM_DEPARTEMENT,
                cols.includes('region') && (d.NOM_REGION ?? ''),
                cols.includes('ping')   && d.moy_ping,
                cols.includes('dl')     && d.moy_download,
                cols.includes('ul')     && d.moy_upload,
                cols.includes('nb')     && d.nb_tests,
            ].filter(v => v !== false),
        },
        interregions: {
            label:   'Par interrégion',
            data:    () => STATE.DATA.interregions || [],
            colonnes: [
                { id: 'interreg', label: 'Interrégion',      checked: true },
                { id: 'ping',     label: 'Ping moy. (ms)',   checked: true },
                { id: 'dl',       label: 'DL moy. (Mbit/s)', checked: true },
                { id: 'ul',       label: 'UL moy. (Mbit/s)', checked: true },
                { id: 'nb',       label: 'Nb tests',         checked: true },
            ],
            toRow: (i, cols) => [
                cols.includes('interreg') && i.NOM_INTERREGION,
                cols.includes('ping')     && i.moy_ping,
                cols.includes('dl')       && i.moy_download,
                cols.includes('ul')       && i.moy_upload,
                cols.includes('nb')       && i.nb_tests,
            ].filter(v => v !== false),
        },
    };

    return cfgOnglets[onglet] || cfgOnglets['sites'];
}

function exporterCSVStat() {
    const ctx = _getExportContext();
    const donnees = ctx.data();
    if (!donnees.length) { alert('Aucune donnée à exporter sur cet onglet.'); return; }

    ouvrirModalExport({
        titre:     'Export CSV — Statistiques ' + ctx.label,
        type:      'csv',
        avecStats: true,
        colonnes:  ctx.colonnes,
        onConfirm(opts) {
            const cols = opts.colonnes;
            const sep  = opts.separateur;
            const esc  = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';

            const entetes = ctx.colonnes
                .filter(c => cols.includes(c.id))
                .map(c => c.label);

            const lignes = [entetes.map(esc).join(sep)];

            if (opts.stats && donnees.length) {
                const avg = k => (donnees.reduce((s, r) => s + (+r[k] || 0), 0) / donnees.length).toFixed(2);
                lignes.push(['# Statistiques globales'].map(esc).join(sep));
                lignes.push(['Métrique', 'Moyenne', 'Min', 'Max'].map(esc).join(sep));
                [
                    ['Ping (ms)',          'moy_ping',     'moy_ping',     'moy_ping'],
                    ['Téléch. (Mbit/s)',   'moy_download', 'moy_download', 'moy_download'],
                    ['Envoi (Mbit/s)',     'moy_upload',   'moy_upload',   'moy_upload'],
                ].forEach(([lbl, avgK]) => {
                    const vals = donnees.map(r => +r[avgK]).filter(v => !isNaN(v));
                    lignes.push([lbl, (vals.reduce((a,b) => a+b, 0)/vals.length).toFixed(2),
                        Math.min(...vals).toFixed(2), Math.max(...vals).toFixed(2)].map(esc).join(sep));
                });
                lignes.push([]);
            }

            donnees.forEach(row => lignes.push(ctx.toRow(row, cols).map(esc).join(sep)));

            const periode = STATE.periode ? STATE.periode + 'j' : 'tout';
            telechargerCSV(lignes,
                'statistiques_' + STATE.ongletActif + '_' + periode + '_' + new Date().toISOString().slice(0,10) + '.csv',
                sep, opts.bom);
        }
    });
}

function exporterPDFStat() {
    const ctx    = _getExportContext();
    const donnees = ctx.data();
    if (!donnees.length) { alert('Aucune donnée à exporter sur cet onglet.'); return; }

    ouvrirModalExport({
        titre:        'Export PDF — Statistiques ' + ctx.label,
        type:         'pdf',
        avecStats:    true,
        avecCouleurs: true,
        onConfirm(opts) {
            const avecCouleurs = opts.couleurs;
            const cols = ctx.colonnes.map(c => c.id); // toutes les colonnes pour PDF

            function couleur(val, metrique) {
                if (!avecCouleurs || !STATE.seuils) return '';
                const s = STATE.seuils.find ? STATE.seuils.find(x => x.NOM_SEUIL === metrique) : null;
                if (!s) return '';
                let verdict;
                if (metrique === 'ping') {
                    verdict = val <= +s.VALEUR_BONNE ? 'bon' : val >= +s.VALEUR_MAUVAISE ? 'mauvais' : 'moyen';
                } else {
                    verdict = val >= +s.VALEUR_BONNE ? 'bon' : val <= +s.VALEUR_MAUVAISE ? 'mauvais' : 'moyen';
                }
                const c = { bon: '#d4edda', moyen: '#fff3cd', mauvais: '#f8d7da' };
                return ' style="background:' + c[verdict] + '"';
            }

            const entetes = ctx.colonnes.map(c => '<th>' + c.label + '</th>').join('');

            const lignesHTML = donnees.map(row => {
                const cells = ctx.toRow(row, cols).map((val, i) => {
                    const colId  = ctx.colonnes[i]?.id ?? '';
                    const metMap = { ping: 'ping', dl: 'download', ul: 'upload' };
                    const met    = metMap[colId] ?? null;
                    const attr   = met ? couleur(+val, met) : '';
                    return '<td' + attr + '>' + (val ?? '') + '</td>';
                }).join('');
                return '<tr>' + cells + '</tr>';
            }).join('');

            const blocStats = opts.stats ? (() => {
                const avg = k => (donnees.reduce((s, r) => s + (+r[k] || 0), 0) / donnees.length).toFixed(2);
                return '<table class="pdf-stats"><thead><tr>' +
                    '<th></th><th>Ping (ms)</th><th>Téléch. (Mbit/s)</th><th>Envoi (Mbit/s)</th>' +
                    '</tr></thead><tbody>' +
                    '<tr><td>Moyenne</td>' +
                    '<td' + couleur(+avg('moy_ping'), 'ping') + '>' + avg('moy_ping') + '</td>' +
                    '<td' + couleur(+avg('moy_download'), 'download') + '>' + avg('moy_download') + '</td>' +
                    '<td' + couleur(+avg('moy_upload'), 'upload') + '>' + avg('moy_upload') + '</td>' +
                    '</tr><tr><td>Min</td>' +
                    '<td>' + Math.min(...donnees.map(r => +r.moy_ping)).toFixed(2) + '</td>' +
                    '<td>' + Math.min(...donnees.map(r => +r.moy_download)).toFixed(2) + '</td>' +
                    '<td>' + Math.min(...donnees.map(r => +r.moy_upload)).toFixed(2) + '</td>' +
                    '</tr><tr><td>Max</td>' +
                    '<td>' + Math.max(...donnees.map(r => +r.moy_ping)).toFixed(2) + '</td>' +
                    '<td>' + Math.max(...donnees.map(r => +r.moy_download)).toFixed(2) + '</td>' +
                    '<td>' + Math.max(...donnees.map(r => +r.moy_upload)).toFixed(2) + '</td>' +
                    '</tr></tbody></table>';
            })() : '';

            const periode = STATE.periode ? STATE.periode + ' derniers jours' : 'toute la période';
            const entete  = document.createElement('div');
            entete.id     = 'pdf-header';
            entete.innerHTML =
                '<div class="pdf-meta">' +
                '<div class="pdf-meta-source">France Travail — Ma Connexion</div>' +
                '<div class="pdf-meta-title">Statistiques — ' + ctx.label + '</div>' +
                '<div class="pdf-meta-date">Exporté le ' + new Date().toLocaleDateString('fr-FR') +
                ' — ' + periode + ' — ' + donnees.length + ' entrée(s)' +
                (avecCouleurs ? ' · Avec code couleur' : '') + '</div></div>' +
                blocStats +
                '<table class="pdf-table"><thead><tr>' + entetes + '</tr></thead>' +
                '<tbody>' + lignesHTML + '</tbody></table>';

            document.body.appendChild(entete);
            const style = document.createElement('style');
            style.id = 'style-print-tmp';
            style.textContent =
                '@media print{' +
                '.bandeau-dsi,.header,.main-nav,.footer,.page,.export-modal-overlay{display:none!important}' +
                '#pdf-header{display:block!important}' +
                '#pdf-header .pdf-meta,#pdf-header .pdf-meta *{display:block!important}' +
                '}';
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