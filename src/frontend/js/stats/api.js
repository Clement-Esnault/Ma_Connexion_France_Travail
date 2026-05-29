
import { STATE } from './state.js';

/**
 * api.js — Couche HTTP du dashboard statistiques.
 *
 * Point d'entrée unique vers backend/admin/stat.php.
 * Injecte automatiquement les filtres globaux (periode, mode) dans chaque requête.
 * Tous les modules appellent api() — jamais fetch() directement.
 */

/**
 * Appelle stat.php et retourne les données JSON.
 *
 * Les paramètres globaux STATE.periode et STATE.mode sont ajoutés
 * automatiquement si définis. Les $params locaux les écrasent si besoin.
 *
 * En cas d'erreur réseau ou HTTP, logue en console et retourne [].
 *
 * @param {string} type    — valeur du paramètre GET "type"
 *                           ex: 'par_site' | 'nationale' | 'heatmap_horaire'
 * @param {Object} [params={}] — paramètres supplémentaires optionnels
 * @returns {Promise<any>}  Tableau ou objet JSON, [] en cas d'erreur
 */
export async function api(type, params = {}) {
    const parametres = {
        type,
        ...(STATE.periode ? { periode: STATE.periode } : {}),
        ...(STATE.mode    ? { mode:    STATE.mode    } : {}),
        ...params,
    };

    try {
        const reponse = await fetch(
            '../../backend/admin/stat.php?' + new URLSearchParams(parametres)
        );
        if (!reponse.ok) throw new Error('HTTP ' + reponse.status);
        return await reponse.json();
    } catch (erreur) {
        console.error('[api]', type, erreur);
        return [];
    }
}