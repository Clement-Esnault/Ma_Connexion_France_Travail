/**
 * speedtest_debug.js — Test comparatif des méthodes de mesure
 * USAGE : changer METHODE ci-dessous et recharger la page
 *
 * 'readablestream' → méthode actuelle (v1.9.0)
 * 'tailleFixe'     → téléchargement fichier complet, calcul taille/temps
 * 'multiChunks'    → plusieurs requêtes taille fixe, médiane des débits
 */

'use strict';

// ── CHANGER ICI ───────────────────────────────────────────────────────
const METHODE = 'multiChunks'; // 'readablestream' | 'tailleFixe' | 'multiChunks'
const MODE    = 'precise';        // 'fast' | 'precise'
// ─────────────────────────────────────────────────────────────────────

const TIMEOUT_DEBUG_MS = 20_000;
const attendreDebug = ms => new Promise(r => setTimeout(r, ms));
function abortApres(ms) {
    const ctrl = new AbortController();
    setTimeout(() => ctrl.abort(), ms);
    return ctrl;
}
function mediane(arr) {
    const t = [...arr].sort((a, b) => a - b);
    const m = Math.floor(t.length / 2);
    return t.length % 2 ? t[m] : (t[m - 1] + t[m]) / 2;
}

// ── Méthode 1 : ReadableStream durée fixe (actuelle v1.9) ─────────────
async function downloadReadableStream(dureeMs = 4000, parallel = 1) {
    const lireStream = async () => {
        const ctrl  = abortApres(dureeMs + 2000);
        const debut = performance.now();
        let bytes   = 0;
        const res = await fetch(
            window.FT_API.garbageEndpoint + '?size=20&r=' + Math.random(),
            { cache: 'no-store', signal: ctrl.signal }
        );
        const reader = res.body.getReader();
        try {
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                bytes += value.byteLength;
                if (performance.now() - debut >= dureeMs) {
                    await reader.cancel();
                    break;
                }
            }
        } catch (_) {}
        finally { reader.releaseLock(); }
        return { bytes, duree: (performance.now() - debut) / 1000 };
    };

    const resultats = await Promise.allSettled(
        Array.from({ length: parallel }, lireStream)
    );
    const valides = resultats
        .filter(r => r.status === 'fulfilled' && r.value.bytes > 0)
        .map(r => r.value);
    const totalBytes  = valides.reduce((a, v) => a + v.bytes, 0);
    const dureeMaxSec = Math.max(...valides.map(v => v.duree));
    return ((totalBytes * 8) / dureeMaxSec / 1_000_000).toFixed(2);
}

// ── Méthode 2 : Taille fixe — télécharge un fichier entier, calcule taille/temps ──
async function downloadTailleFixe(tailleMo = 20) {
    const debut = performance.now();
    const res = await fetch(
        window.FT_API.garbageEndpoint + '?size=' + tailleMo + '&r=' + Math.random(),
        { cache: 'no-store', signal: abortApres(TIMEOUT_MESURE_MS).signal }
    );
    const blob = await res.blob(); // attend la fin complète
    const dureeS = (performance.now() - debut) / 1000;
    return ((blob.size * 8) / dureeS / 1_000_000).toFixed(2);
}

// ── Méthode 3 : Multi-chunks — N requêtes de petite taille, médiane des débits ──
async function downloadMultiChunks(tailleMoParChunk = 5, nbChunks = 6) {
    const mesures = [];
    for (let i = 0; i < nbChunks; i++) {
        try {
            const debut = performance.now();
            const res = await fetch(
                window.FT_API.garbageEndpoint + '?size=' + tailleMoParChunk + '&r=' + Math.random(),
                { cache: 'no-store', signal: abortApres(TIMEOUT_MESURE_MS).signal }
            );
            const blob = await res.blob();
            const dureeS = (performance.now() - debut) / 1000;
            mesures.push((blob.size * 8) / dureeS / 1_000_000);
        } catch (_) {}
    }
    if (mesures.length === 0) throw new Error('Aucune mesure réussie');
    return mediane(mesures).toFixed(2);
}

// ── Dispatch selon METHODE ────────────────────────────────────────────
async function mesurerTelechargement(mode = MODE) {
    console.log(`[debug] Méthode : ${METHODE} | Mode : ${mode}`);
    const dureeMs = mode === 'fast' ? 2000 : 4000;

    switch (METHODE) {
        case 'readablestream':
            return downloadReadableStream(dureeMs, 1);
        case 'tailleFixe':
            // 20Mo pour précis, 5Mo pour rapide
            return downloadTailleFixe(mode === 'fast' ? 5 : 20);
        case 'multiChunks':
            // 6 chunks de 5Mo pour précis, 3 chunks de 2Mo pour rapide
            return downloadMultiChunks(
                mode === 'fast' ? 2 : 5,
                mode === 'fast' ? 3 : 6
            );
        default:
            throw new Error(`Méthode inconnue : ${METHODE}`);
    }
}

// ── Point d'entrée — même signature que speedtest.js ─────────────────
// Lance ping + download + upload et logue les résultats dans la console
async function lancerBenchmark() {
    console.group(`[benchmark] ${METHODE} / ${MODE}`);
    console.time('durée totale');

    try {
        console.time('ping');
        const ping = await mesurerPing(MODE); // réutilise mesurerPing de speedtest.js
        console.timeEnd('ping');
        console.log(`Ping    : ${ping} ms`);

        console.time('download');
        const dl = await mesurerTelechargement(MODE);
        console.timeEnd('download');
        console.log(`Download: ${dl} Mbit/s`);

        console.time('upload');
        const ul = await mesurerEnvoi(MODE); // réutilise mesurerEnvoi de speedtest.js
        console.timeEnd('upload');
        console.log(`Upload  : ${ul} Mbit/s`);

    } catch (err) {
        console.error('[benchmark] Erreur :', err.message);
    }

    console.timeEnd('durée totale');
    console.groupEnd();
}

// Auto-lancement quand chargé
lancerBenchmark();