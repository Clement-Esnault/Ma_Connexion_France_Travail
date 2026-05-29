
import { STATE } from './state.js';

/**
 * verdicts.js — Calcul des verdicts de qualité et indicateurs visuels.
 *
 * Centralise la logique seuils pour que tous les panels utilisent
 * les mêmes règles de coloration (confort / fonctionnel / insuffisant).
 *
 * Règles métier :
 *   - Ping    : sens inverse (plus bas = meilleur)
 *   - Download/Upload : sens direct (plus haut = meilleur)
 */

/**
 * Calcule le verdict qualitatif d'une valeur par rapport aux seuils FT_SEUILS.
 *
 * @param {'download'|'upload'|'ping'} metrique — Métrique à évaluer
 * @param {number} valeur — Valeur mesurée
 * @returns {'confort'|'fonctionnel'|'insuffisant'|null}
 *          null si les seuils ne sont pas encore chargés
 */
export function verdictStat(metrique, valeur) {
    if (!STATE.seuils.length) return null;

    const seuil = STATE.seuils.find(s => s.NOM_SEUIL === metrique);
    if (!seuil) return null;

    const bon     = +seuil.VALEUR_BONNE;
    const mauvais = +seuil.VALEUR_MAUVAISE;

    // Ping : sens inverse (latence basse = bon)
    if (metrique === 'ping') {
        if (valeur <= bon)     return 'confort';
        if (valeur >= mauvais) return 'insuffisant';
        return 'fonctionnel';
    }

    // Download / Upload : sens direct (débit élevé = bon)
    if (valeur >= bon)     return 'confort';
    if (valeur <= mauvais) return 'insuffisant';
    return 'fonctionnel';
}

/**
 * Retourne les couleurs CSS associées à un verdict.
 *
 * @param {'confort'|'fonctionnel'|'insuffisant'|null} verdict
 * @returns {{ fill: string, border: string }}
 */
export function couleurVerdict(verdict) {
    if (verdict === 'confort')     return { fill: '#1a7a3c', border: '#145a2e' };
    if (verdict === 'fonctionnel') return { fill: '#f0c040', border: '#b8860b' };
    if (verdict === 'insuffisant') return { fill: '#E1000F', border: '#a0000a' };
    return { fill: '#B0BFF0', border: '#8090c0' }; // inconnu / null
}

/**
 * Génère le HTML d'une pastille colorée (petit disque) selon le verdict.
 *
 * Utilisée dans le tableau des sites pour indiquer visuellement
 * la qualité de chaque métrique sans texte supplémentaire.
 *
 * @param {'download'|'upload'|'ping'} metrique
 * @param {number|string} valeur
 * @returns {string} Fragment HTML <span> inline
 */
export function pastilleHTML(metrique, valeur) {
    const couleurs = couleurVerdict(verdictStat(metrique, +valeur));
    return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;'
         + 'background:' + couleurs.fill + ';margin-right:4px;vertical-align:middle"></span>';
}