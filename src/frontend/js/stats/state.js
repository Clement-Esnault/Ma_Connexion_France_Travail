/**
 * state.js — Source de vérité centrale du dashboard statistiques.
 *
 * Importé par tous les modules. Contient l'état courant de l'interface :
 * filtres actifs, données chargées, seuils, cache API.
 *
 * ⚠️  Ne jamais réassigner STATE lui-même (STATE = {...}).
 *     Modifier uniquement ses propriétés (STATE.periode = '30').
 */
export const STATE = {
    // ── Navigation ──────────────────────────────────────────────────
    /** Identifiant de l'onglet actif ('sites' | 'regions' | 'carte' | ...) */
    ongletActif:   'sites',

    // ── Filtres globaux ──────────────────────────────────────────────
    /** Texte saisi dans la barre de recherche globale */
    recherche:     '',
    /** Portée du filtre ('all' | 'site' | 'region' | 'interregion') */
    portee:        'all',
    /** Fenêtre temporelle en jours ('7' | '30' | '90' | '365' | '') */
    periode:       '30',
    /** Mode de test filtré ('' = tous | 'precise' | 'fast') */
    mode:          '',

    // ── Onglet Évolution ─────────────────────────────────────────────
    /** Vue active dans l'onglet évolution ('ligne' | 'heatmap') */
    vueEvolution:  'ligne',

    // ── Panel Sites ──────────────────────────────────────────────────
    /** Colonne de tri du tableau des sites */
    triSites:      'moy_download',
    /** Filtre textuel dans le panel sites (indépendant de la recherche globale) */
    rechercheSite: '',

    // ── Panel Carte ──────────────────────────────────────────────────
    carte: {
        /** Niveau de granularité ('sites' | 'regions' | 'departements') */
        granule:  'sites',
        /** Métrique colorant la carte ('moy_download' | 'moy_upload' | 'moy_ping') */
        metrique: 'moy_download',
    },

    // ── Panel Heatmap horaire ─────────────────────────────────────────
    hm: {
        /** Code GX du site filtré ('all' = tous les sites) */
        site:     'all',
        /** Métrique affichée ('download' | 'upload' | 'ping') */
        metrique: 'download',
        /** Fenêtre temporelle ('7' | '30' | '90' | '') */
        periode:  '30',
        /** Mode de test ('all' | 'precise' | 'fast') */
        mode:     'all',
    },

    // ── Données chargées (cache côté client) ─────────────────────────
    /** Moyenne nationale, chargée une fois par session */
    nationale: null,
    /** Seuils de qualité depuis FT_SEUILS */
    seuils:    [],
    /** Cache des réponses API par type (sites, regions, etc.) */
    DATA:      {},

    // ── Seuils d'alerte (panel Alertes) ─────────────────────────────
    alertes: {
        /** Download minimum acceptable en Mbit/s */
        seuilDl:   10,
        /** Ping maximum acceptable en ms */
        seuilPing: 100,
    },
};