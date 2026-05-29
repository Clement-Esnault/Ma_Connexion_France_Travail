import { STATE }       from './state.js';
import { verdictStat } from './verdicts.js';

/**
 * filtres.js — Filtrage des données et mise à jour de l'interface.
 *
 * Regroupe :
 *   - Les fonctions de filtrage des données selon l'état global (STATE)
 *   - La mise à jour des KPIs, de la santé globale, du top/flop
 *   - La mise à jour des bandeaux (filtre actif, nationale)
 */

// ══════════════════════════════════════════════════════════════════════
// FILTRAGE DES DONNÉES
// ══════════════════════════════════════════════════════════════════════

/**
 * Retourne les sites filtrés selon la recherche globale et la portée.
 *
 * Portées possibles :
 *   'all'         → filtre sur code GX, nom, région ET interrégion
 *   'site'        → filtre sur code GX et nom de site uniquement
 *   'region'      → filtre sur nom de région uniquement
 *   'interregion' → filtre sur nom d'interrégion uniquement
 *
 * @returns {Array} Sites correspondant au filtre courant
 */
export function getSitesFiltres() {
    const sites = STATE.DATA.sites || [];
    if (!STATE.recherche) return sites;

    const terme = STATE.recherche.toLowerCase();

    return sites.filter(site => {
        if (STATE.portee === 'all' || STATE.portee === 'site') {
            if ((site.CODE_GX_SITE || '').toLowerCase().includes(terme)) return true;
            if ((site.NOM_SITE     || '').toLowerCase().includes(terme)) return true;
        }
        if (STATE.portee === 'all' || STATE.portee === 'region')
            if ((site.NOM_REGION || '').toLowerCase().includes(terme)) return true;
        if (STATE.portee === 'all' || STATE.portee === 'interregion')
            if ((site.NOM_INTERREGION || '').toLowerCase().includes(terme)) return true;
        return false;
    });
}

/**
 * Retourne les sites filtrés ET triés selon les critères du panel Sites.
 *
 * Applique en plus le filtre textuel local du panel (rechercheSite),
 * indépendant de la recherche globale.
 *
 * @returns {Array} Sites filtrés et triés
 */
export function getSitesTries() {
    let sites = getSitesFiltres();

    // Filtre local du panel Sites (champ "Rechercher un site")
    if (STATE.rechercheSite) {
        const terme = STATE.rechercheSite.toLowerCase();
        sites = sites.filter(site =>
            (site.NOM_SITE     || '').toLowerCase().includes(terme) ||
            (site.CODE_GX_SITE || '').toLowerCase().includes(terme) ||
            (site.NOM_REGION   || '').toLowerCase().includes(terme)
        );
    }

    // Tri selon la colonne sélectionnée
    return [...sites].sort((a, b) => {
        if (STATE.triSites === 'NOM_SITE') {
            return (a.NOM_SITE || '').localeCompare(b.NOM_SITE || '');
        }
        if (STATE.triSites === 'moy_ping') {
            return +a.moy_ping - +b.moy_ping; // Ping : croissant (plus bas = meilleur)
        }
        return +b[STATE.triSites] - +a[STATE.triSites]; // Autres : décroissant
    });
}

/**
 * Filtre les données régions selon la recherche globale.
 * Ignoré si la portée est 'site' (pas pertinent au niveau région).
 *
 * @param {Array} regions — Données régions brutes
 * @returns {Array}
 */
export function filtrerRegions(regions) {
    if (!STATE.recherche || STATE.portee === 'site') return regions;
    const terme = STATE.recherche.toLowerCase();
    return regions.filter(region =>
        ((STATE.portee === 'all' || STATE.portee === 'region')      && (region.NOM_REGION      || '').toLowerCase().includes(terme)) ||
        ((STATE.portee === 'all' || STATE.portee === 'interregion') && (region.NOM_INTERREGION || '').toLowerCase().includes(terme))
    );
}

/**
 * Filtre les données interrégions selon la recherche globale.
 * Ignoré si la portée est 'site' ou 'region'.
 *
 * @param {Array} interregions — Données interrégions brutes
 * @returns {Array}
 */
export function filtrerInterregions(interregions) {
    if (!STATE.recherche || STATE.portee === 'site' || STATE.portee === 'region') return interregions;
    const terme = STATE.recherche.toLowerCase();
    return interregions.filter(ir => (ir.NOM_INTERREGION || '').toLowerCase().includes(terme));
}

/**
 * Filtre les données départements selon la recherche globale.
 *
 * @param {Array} departements — Données départements brutes
 * @returns {Array}
 */
export function filtrerDepts(departements) {
    if (!STATE.recherche) return departements;
    const terme = STATE.recherche.toLowerCase();
    return departements.filter(dept =>
        ((STATE.portee === 'all' || STATE.portee === 'site')        && (dept.NOM_DEPARTEMENT || '').toLowerCase().includes(terme)) ||
        ((STATE.portee === 'all' || STATE.portee === 'region')      && (dept.NOM_REGION      || '').toLowerCase().includes(terme)) ||
        ((STATE.portee === 'all' || STATE.portee === 'interregion') && (dept.NOM_INTERREGION || '').toLowerCase().includes(terme))
    );
}

// ══════════════════════════════════════════════════════════════════════
// MISE À JOUR DES KPIs
// ══════════════════════════════════════════════════════════════════════

/**
 * Met à jour les 4 cartes KPI (download, upload, ping, nb tests)
 * avec les moyennes calculées sur les sites filtrés.
 *
 * Affiche '—' et masque les unités si aucune donnée.
 *
 * @param {Array} sites — Sites à agréger (résultat de getSitesFiltres())
 */
export function majKPIs(sites) {
    if (!sites.length) {
        ['kpi-dl-val', 'kpi-ul-val', 'kpi-ping-val', 'kpi-tests-val'].forEach(id => {
            document.getElementById(id).textContent = '—';
        });
        ['kpi-dl-unit', 'kpi-ul-unit', 'kpi-ping-unit'].forEach(id => {
            document.getElementById(id).style.display = 'none';
        });
        document.getElementById('kpi-sub').textContent = '';
        return;
    }

    // Calcul des moyennes
    const moyenne = cle => (sites.reduce((somme, site) => somme + +site[cle], 0) / sites.length).toFixed(2);
    const totalTests = sites.reduce((somme, site) => somme + +site.nb_tests, 0);
    const sousTitre  = (STATE.periode ? STATE.periode + 'j — ' : 'historique — ') + sites.length + ' site(s)';

    document.getElementById('kpi-dl-val').textContent    = moyenne('moy_download');
    document.getElementById('kpi-ul-val').textContent    = moyenne('moy_upload');
    document.getElementById('kpi-ping-val').textContent  = moyenne('moy_ping');
    document.getElementById('kpi-tests-val').textContent = totalTests.toLocaleString('fr-FR');

    ['kpi-dl-unit', 'kpi-ul-unit', 'kpi-ping-unit'].forEach(id => {
        document.getElementById(id).style.display = '';
    });
    ['kpi-sub', 'kpi-ul-sub', 'kpi-ping-sub'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = sousTitre;
    });
}

// ══════════════════════════════════════════════════════════════════════
// SANTÉ GLOBALE
// ══════════════════════════════════════════════════════════════════════

/**
 * Met à jour le bandeau de santé globale (% confort / fonctionnel / insuffisant).
 *
 * Le verdict d'un site est celui de sa métrique la plus dégradée :
 *   insuffisant > fonctionnel > confort
 *
 * @param {Array} sites — Sites à analyser
 */
export function majSante(sites) {
    const conteneur = document.getElementById('kpi-sante');
    if (!STATE.seuils.length || !sites.length) {
        conteneur.style.display = 'none';
        return;
    }

    let nbConfort = 0, nbFonctionnel = 0, nbInsuffisant = 0;

    sites.forEach(site => {
        const verdicts = [
            verdictStat('download', +site.moy_download),
            verdictStat('upload',   +site.moy_upload),
            verdictStat('ping',     +site.moy_ping),
        ];
        // Prendre le pire verdict parmi les 3 métriques
        const pire = verdicts.includes('insuffisant') ? 'insuffisant'
                   : verdicts.includes('fonctionnel') ? 'fonctionnel'
                   : 'confort';
        if (pire === 'confort')      nbConfort++;
        else if (pire === 'fonctionnel') nbFonctionnel++;
        else                             nbInsuffisant++;
    });

    const total = sites.length;
    document.getElementById('sante-confort').textContent     = '✅ ' + nbConfort      + ' confort ('     + Math.round(nbConfort      / total * 100) + '%)';
    document.getElementById('sante-fonctionnel').textContent = '⚠️ ' + nbFonctionnel  + ' fonctionnel (' + Math.round(nbFonctionnel  / total * 100) + '%)';
    document.getElementById('sante-insuffisant').textContent = '❌ ' + nbInsuffisant   + ' insuffisant (' + Math.round(nbInsuffisant  / total * 100) + '%)';
    conteneur.style.display = '';
}

// ══════════════════════════════════════════════════════════════════════
// TOP / FLOP
// ══════════════════════════════════════════════════════════════════════

/**
 * Met à jour les tableaux Top 5 / Flop 5 par download.
 *
 * @param {Array} sites — Sites à classer
 */
export function majTopFlop(sites) {
    if (!sites.length) return;

    const tries = [...sites].sort((a, b) => +b.moy_download - +a.moy_download);

    const ligneHTML = site =>
        '<tr>'
        + '<td class="tf-nom">' + (site.NOM_SITE || '') + '</td>'
        + '<td class="tf-val tf-good">' + (+site.moy_download).toFixed(2) + '</td>'
        + '<td class="tf-val">'         + (+site.moy_upload).toFixed(2)   + '</td>'
        + '<td class="tf-val">'         + (+site.moy_ping).toFixed(2)     + ' ms</td>'
        + '</tr>';

    document.getElementById('tbody-top').innerHTML  = tries.slice(0, 5).map(ligneHTML).join('');
    document.getElementById('tbody-flop').innerHTML = tries.slice(-5).reverse().map(ligneHTML).join('');
}

// ══════════════════════════════════════════════════════════════════════
// BANDEAUX
// ══════════════════════════════════════════════════════════════════════

/**
 * Met à jour le bandeau "Filtre actif" visible quand une recherche est en cours.
 * Affiche la portée, le terme recherché et le nombre de résultats.
 */
export function majBandeauFiltre() {
    const bandeau = document.getElementById('filter-banner');
    if (!STATE.recherche) { bandeau.style.display = 'none'; return; }

    const labelPortee = {
        all:          'Tout',
        site:         'Site',
        region:       'Région',
        interregion:  'Interrégion',
    }[STATE.portee] || STATE.portee;

    const nbResultats = getSitesFiltres().length;

    bandeau.style.display = '';
    document.getElementById('banner-portee').textContent    = labelPortee;
    document.getElementById('banner-recherche').textContent = '"' + STATE.recherche + '"';
    document.getElementById('banner-nb-sites').textContent  = nbResultats + ' site(s)';
    document.getElementById('search-count').textContent     = nbResultats + ' résultat(s)';
}

/**
 * Met à jour uniquement le compteur de résultats dans la barre de recherche.
 * Appelé lors des changements de filtres mineurs.
 */
export function majCompteurSites() {
    const compteur = document.getElementById('search-count');
    if (compteur) {
        compteur.textContent = STATE.recherche
            ? getSitesFiltres().length + ' résultat(s)'
            : '';
    }
}

/**
 * Met à jour le bandeau "Nationale" avec les moyennes nationales
 * et, si un filtre est actif, les moyennes du sous-ensemble filtré.
 */
export function majBandeauNationale() {
    const nationale = STATE.nationale;
    if (!nationale) return;

    document.getElementById('nationale-banner').style.display = '';
    document.getElementById('nat-dl').textContent   = 'Télé. '  + (+nationale.moy_download).toFixed(2) + ' Mbit/s';
    document.getElementById('nat-ul').textContent   = 'Envoi '  + (+nationale.moy_upload).toFixed(2)   + ' Mbit/s';
    document.getElementById('nat-ping').textContent = 'Ping '   + (+nationale.moy_ping).toFixed(2)     + ' ms';

    // Afficher les moyennes du filtre courant si une recherche est active
    const sitesFiltres = getSitesFiltres();
    if (STATE.recherche && sitesFiltres.length) {
        const moyenne = cle => (sitesFiltres.reduce((s, site) => s + +site[cle], 0) / sitesFiltres.length).toFixed(2);
        document.getElementById('filtre-dl').textContent   = 'Télé. '  + moyenne('moy_download') + ' Mbit/s';
        document.getElementById('filtre-ul').textContent   = 'Envoi '  + moyenne('moy_upload')   + ' Mbit/s';
        document.getElementById('filtre-ping').textContent = 'Ping '   + moyenne('moy_ping')     + ' ms';
        document.getElementById('filtre-bloc-nat').style.display = '';
    } else {
        document.getElementById('filtre-bloc-nat').style.display = 'none';
    }
}