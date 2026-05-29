
/**
 * charts.js — Helpers de création et gestion des graphiques Chart.js.
 *
 * Centralise la création et la destruction des instances Chart.js.
 * Chaque panel importe uniquement les fonctions dont il a besoin.
 *
 * Toutes les fonctions détruisent l'instance précédente avant d'en
 * créer une nouvelle pour éviter les fuites mémoire.
 */

// ── Configuration globale Chart.js ────────────────────────────────────
Chart.defaults.font.family = '"Marianne", sans-serif';
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#6b7a9e';

/** Palette France Travail étendue pour les graphiques multi-séries */
export const COULEURS = [
    '#283276', '#406BDE', '#008ECF', '#00b8d4', '#00bfa5',
    '#1a7a3c', '#7cb342', '#e65000', '#E1000F', '#9c27b0',
];

/** Registre des instances actives — clé = id du canvas */
const GRAPHIQUES = {};

// ── Options par défaut réutilisées ────────────────────────────────────
const OPTS_BARRE_DEFAUT = {
    responsive:          true,
    maintainAspectRatio: false,
    plugins: { legend: { display: true, position: 'top' } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { grid: { color: '#eef1f9' }, beginAtZero: true },
    },
};

const OPTS_LIGNE_DEFAUT = {
    ...OPTS_BARRE_DEFAUT,
    tension: 0.4,
};

/**
 * Crée (ou recrée) un graphique en barres.
 *
 * @param {string}    id        — id du <canvas> cible
 * @param {string[]}  labels    — étiquettes de l'axe X
 * @param {Object[]}  datasets  — jeux de données Chart.js
 * @param {Object=}   options   — options Chart.js personnalisées (écrase les défauts)
 */
export function creerGraphBarre(id, labels, datasets, options) {
    if (GRAPHIQUES[id]) GRAPHIQUES[id].destroy();
    const canvas = document.getElementById(id);
    if (!canvas) return;
    GRAPHIQUES[id] = new Chart(canvas, {
        type: 'bar',
        data: { labels, datasets },
        options: options ?? OPTS_BARRE_DEFAUT,
    });
}

/**
 * Crée (ou recrée) un graphique en courbes.
 *
 * @param {string}   id       — id du <canvas> cible
 * @param {string[]} labels   — étiquettes de l'axe X (mois, dates…)
 * @param {Object[]} datasets — jeux de données Chart.js
 */
export function creerGraphLigne(id, labels, datasets) {
    if (GRAPHIQUES[id]) GRAPHIQUES[id].destroy();
    const canvas = document.getElementById(id);
    if (!canvas) return;
    GRAPHIQUES[id] = new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        options: OPTS_LIGNE_DEFAUT,
    });
}

/**
 * Crée (ou recrée) un graphique en anneau (doughnut).
 *
 * Utilisé pour la répartition des tests par région.
 *
 * @param {string}   id     — id du <canvas> cible
 * @param {string[]} labels — noms des segments
 * @param {number[]} valeurs — valeurs de chaque segment
 */
export function creerGraphDoughnut(id, labels, valeurs) {
    if (GRAPHIQUES[id]) GRAPHIQUES[id].destroy();
    const canvas = document.getElementById(id);
    if (!canvas) return;
    GRAPHIQUES[id] = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data:            valeurs,
                backgroundColor: COULEURS.slice(0, labels.length),
                borderWidth:     2,
                borderColor:     '#fff',
            }],
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels:   { font: { size: 11 }, padding: 10 },
                },
            },
            cutout: '60%',
        },
    });
}