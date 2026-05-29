/**
 * speedtest.js — v1.9.2 — Moteur de mesure de débit réseau
 *
 * Mode : 'precise' (~8s)
 *
 * Méthode :
 *   - Ping     : médiane sur N requêtes légères
 *   - Download : préchauffage TCP + ReadableStream pendant durée fixe
 *   - Upload   : préchauffage TCP + envoi continu pendant durée fixe
 *
 * Changements v1.9.2 vs v1.9.1 :
 *   - Taille garbage dynamique : calculée depuis la durée de mesure pour garantir
 *     que le stream ne se termine jamais avant la durée (base 200 Mbit/s max)
 *   - Plafond de sanité : résultat > DEBIT_MAX_MBITPS → invalide (loopback/LAN détecté)
 *   - Préchauffage TCP augmenté : 1200→2500 ms DL, 800→1500 ms UL
 *     (distance Normandie→Montpellier — slow start plus long sur longue distance WAN)
 *   - Validation durée effective : si bytes reçus en < DUREE_MESURE_MIN_MS → invalide
 *
 * Changements v1.9.1 vs v1.9.0 :
 *   - Préchauffage TCP ajouté avant download et upload
 *   - parallel forcé à 1 (WAN asymétrique)
 *
 * La configuration est chargée depuis FT_CONFIG_SPEEDTEST via chargerConfigSpeedtest().
 * En cas d'échec, les valeurs par défaut ci-dessous sont utilisées.
 */

'use strict';

// ── Configuration par défaut (fallback si BDD indisponible) ───────────────────

/**
 * @type {Record<string, {ping: object, download: object, upload: object}>}
 */
let CONFIGS_MODE = {
    precise: {
        ping:     { prechauffage: 3,  echantillons: 10, delay: 30 },
        download: { dureeMsDownload: 6000, parallel: 1 },
        upload:   { dureeMsUpload:   6000, parallel: 1, tailleMoBlob: 20 },
    },
};

/** Timeout maximal par mesure individuelle (ms). */
const TIMEOUT_MESURE_MS = 30_000;

/**
 * Proportion de la durée de mesure utilisée pour le préchauffage TCP.
 * Ex : 0.15 → 15% de dureeMsDownload, avec un plancher de PRECHAUFFAGE_MIN_MS.
 * S'adapte automatiquement aux durées configurées en BDD sans modifier ce fichier.
 */
const PRECHAUFFAGE_PROPORTION = 0.25;

/**
 * Durée minimale de préchauffage TCP (ms).
 * Appliquée si PRECHAUFFAGE_PROPORTION × durée < cette valeur.
 * Valeur élevée pour absorber le slow start sur liaison WAN longue distance.
 */
const PRECHAUFFAGE_MIN_MS = 2500;

/**
 * Calcule la durée de préchauffage TCP adaptée à une durée de mesure donnée.
 * @param {number} dureeMesureMs  dureeMsDownload ou dureeMsUpload depuis la BDD
 * @returns {number} Durée de préchauffage en ms
 */
function dureePrechauffage(dureeMesureMs) {
    return Math.max(PRECHAUFFAGE_MIN_MS, Math.round(dureeMesureMs * PRECHAUFFAGE_PROPORTION));
}

/**
 * Débit maximum plausible (Mbit/s) — chargé depuis FT_CONFIG_SPEEDTEST (debit_max_mbitps).
 * 0 = désactivé (sites fibrés datacenter, tests en LAN).
 * Initialisé après chargerConfigSpeedtest().
 */
let DEBIT_MAX_MBITPS = 1000;

/**
 * Durée effective minimale acceptable pour une mesure (ms).
 * Si les bytes sont reçus / envoyés en moins de ce laps de temps,
 * le stream s'est terminé trop vite (fichier trop petit ou connexion LAN)
 * et la mesure est invalide.
 */
const DUREE_MESURE_MIN_MS = 1000;

// ── Chargement de la configuration BDD ───────────────────────────────────────

/**
 * Valide la structure minimale d'une config reçue depuis la BDD.
 * Accepte l'ancienne structure (size/tailleMo) et la nouvelle (dureeMsDownload).
 * @param {unknown} config
 * @returns {boolean}
 */
function estConfigValide(config) {
    return !!config && ['precise'].every(mode =>
        config[mode]?.ping?.echantillons > 0 &&
        (config[mode]?.download?.dureeMsDownload > 0 || config[mode]?.download?.size > 0) &&
        (config[mode]?.upload?.dureeMsUpload > 0   || config[mode]?.upload?.tailleMo > 0)
    );
}

/**
 * Migre une config ancienne format (size/tailleMo) vers le nouveau (dureeMsDownload).
 * Rétrocompatibilité avec les entrées FT_CONFIG_SPEEDTEST antérieures à v1.9.
 * @param {object} config
 * @returns {object}
 */
function migrerConfig(config) {
    const copie = JSON.parse(JSON.stringify(config));
    for (const mode of ['precise']) {
        const dl = copie[mode]?.download;
        const ul = copie[mode]?.upload;
        if (dl && !dl.dureeMsDownload) {
            dl.dureeMsDownload = 6000;
            dl.parallel        = 1;
        }
        if (ul && !ul.dureeMsUpload) {
            ul.dureeMsUpload  = 6000;
            ul.parallel       = 1;
            ul.tailleMoBlob   = ul.tailleMo ?? 20;
        }
        // Forcer parallel=1 sur toutes les configs chargées depuis la BDD
        // parallel > 1 fausse le calcul sur les connexions WAN asymétriques
        if (dl) dl.parallel = 1;
        if (ul) ul.parallel = 1;
    }
    return copie;
}

/**
 * Charge et applique la configuration moteur depuis FT_CONFIG_SPEEDTEST.
 * Silencieux en cas d'échec — utilise les valeurs par défaut.
 */
async function chargerConfigSpeedtest() {
    try {
        const ctrl = new AbortController();
        setTimeout(() => ctrl.abort(), 5000);
        const reponse = await fetch(
            window.FT_API.getConfig + '?r=' + Math.random(),
            { cache: 'no-store', signal: ctrl.signal }
        );
        if (!reponse.ok) throw new Error('HTTP ' + reponse.status);
        const { success, config } = await reponse.json();
        if (success && estConfigValide(config)) {
            CONFIGS_MODE = migrerConfig(config);
            // Plafond de sanité — 0 désactive la vérification
            if (typeof config.debitMaxMbitps === 'number') {
                DEBIT_MAX_MBITPS = config.debitMaxMbitps;
            }
        } else {
            console.warn('[speedtest.js] Config BDD invalide — valeurs par défaut utilisées.');
        }
    } catch (erreur) {
        console.warn('[speedtest.js] Config BDD indisponible — valeurs par défaut utilisées.', erreur.message);
    }
}

// ── Utilitaires ───────────────────────────────────────────────────────────────

/**
 * Calcule la médiane d'un tableau de nombres.
 * @param {number[]} valeurs
 * @returns {number}
 */
function mediane(valeurs) {
    const tries  = [...valeurs].sort((a, b) => a - b);
    const milieu = Math.floor(tries.length / 2);
    return tries.length % 2
        ? tries[milieu]
        : (tries[milieu - 1] + tries[milieu]) / 2;
}

/**
 * Calcule la moyenne après suppression symétrique des valeurs extrêmes.
 * Utilisé pour le ping — élimine les pics ponctuels.
 * @param {number[]} valeurs
 * @param {number}   [proportion=0.15]  Fraction à supprimer de chaque côté
 * @returns {number}
 */
function moyenneTronquee(valeurs, proportion = 0.15) {
    const tries   = [...valeurs].sort((a, b) => a - b);
    const coupage = Math.floor(tries.length * proportion);
    const tronque = coupage > 0 ? tries.slice(coupage, tries.length - coupage) : tries;
    return tronque.reduce((acc, v) => acc + v, 0) / (tronque.length || 1);
}

/**
 * Pause asynchrone.
 * @param {number} ms
 * @returns {Promise<void>}
 */
const attendre = ms => new Promise(resoudre => setTimeout(resoudre, ms));

/**
 * Crée un AbortController qui s'annule automatiquement après `ms` millisecondes.
 * @param {number} ms
 * @returns {AbortController}
 */
function abortApres(ms) {
    const ctrl = new AbortController();
    setTimeout(() => ctrl.abort(), ms);
    return ctrl;
}

// ── Mesure du ping ────────────────────────────────────────────────────────────

/**
 * Mesure la latence réseau — médiane sur N requêtes légères vers pingEndpoint.
 *
 * Effectue d'abord quelques requêtes de préchauffage ignorées
 * pour établir la connexion TCP et vider le DNS cache.
 *
 * @param {'precise'} mode
 * @returns {Promise<string>} Latence en ms (2 décimales)
 */
async function mesurerPing(mode = 'precise') {
    const cfg = CONFIGS_MODE[mode]?.ping;
    if (!cfg) throw new Error(`[speedtest] Mode inconnu : ${mode}`);

    // Préchauffage — établit la connexion TCP, évite que le 1er ping inclue
    // la résolution DNS et la négociation TCP
    for (let i = 0; i < cfg.prechauffage; i++) {
        try {
            await fetch(
                window.FT_API.pingEndpoint + '?r=' + Math.random(),
                { cache: 'no-store', signal: abortApres(TIMEOUT_MESURE_MS).signal }
            );
        } catch (_) { /* préchauffage — ignorer les erreurs */ }
        await attendre(30);
    }

    // Mesures
    const mesures = [];
    for (let i = 0; i < cfg.echantillons; i++) {
        try {
            const debut = performance.now();
            await fetch(
                window.FT_API.pingEndpoint + '?r=' + Math.random(),
                { cache: 'no-store', signal: abortApres(TIMEOUT_MESURE_MS).signal }
            );
            const duree = performance.now() - debut;
            if (duree > 0) mesures.push(duree);
        } catch (_) { /* mesure échouée — continuer */ }
        await attendre(cfg.delay);
    }

    if (mesures.length === 0) {
        throw new Error('[speedtest] Aucune mesure de ping réussie — connexion indisponible.');
    }

    return mediane(mesures).toFixed(2);
}

// ── Mesure du téléchargement ──────────────────────────────────────────────────

/**
 * Effectue le préchauffage TCP download.
 *
 * Lance un stream sacrifié pendant dureeMs ms et l'abandonne.
 * But : forcer le slow start TCP à se terminer AVANT la vraie mesure,
 * pour que la connexion soit à plein régime dès le début du comptage.
 *
 * Sans préchauffage : les premières secondes du stream sont lentes (slow start),
 * ce qui sous-estime le débit sur les durées courtes.
 */
/**
 * @param {number} dureeMs  Durée de préchauffage calculée via dureePrechauffage()
 */
async function prechauffageDownload(dureeMs) {
    try {
        const ctrl    = abortApres(dureeMs + 500);
        const reponse = await fetch(
            window.FT_API.garbageEndpoint + '?size=1&r=' + Math.random(),
            { cache: 'no-store', signal: ctrl.signal }
        );
        const lecteur = reponse.body?.getReader();
        if (!lecteur) return;
        const debut = performance.now();
        try {
            while (true) {
                const { done } = await lecteur.read();
                if (done) break;
                if (performance.now() - debut >= dureeMs) {
                    await lecteur.cancel();
                    break;
                }
            }
        } catch (_) { /* AbortError normal — ignorer */ }
        finally { lecteur.releaseLock(); }
    } catch (_) { /* préchauffage échoué — continuer quand même */ }
}

/**
 * Mesure le débit descendant via ReadableStream pendant une durée fixe.
 *
 * Principe :
 *   1. Préchauffage TCP (connexion établie et slow start terminé)
 *   2. Stream vers garbageEndpoint pendant dureeMsDownload ms
 *   3. Arrêt dès la durée atteinte via reader.cancel()
 *   4. Calcul : bytes reçus × 8 / durée effective (Mbit/s)
 *
 * @param {'precise'} mode
 * @returns {Promise<string>} Débit en Mbit/s (2 décimales)
 */
async function mesurerTelechargement(mode = 'precise') {
    const cfg = CONFIGS_MODE[mode]?.download;
    if (!cfg) throw new Error(`[speedtest] Mode inconnu : ${mode}`);

    const dureeMs = cfg.dureeMsDownload ?? 4000;

    // Taille du fichier garbage calculée dynamiquement depuis la durée de mesure.
    // Base : 200 Mbit/s max plausible sur le réseau intranet FT.
    // Formule : durée (s) × 200 Mbit/s / 8 bits = Mo nécessaires pour ne jamais
    // terminer le stream avant la durée, même sur une connexion LAN rapide.
    // Minimum 20 Mo pour les courtes durées.
    const tailleFichierMo = Math.max(20, Math.ceil((dureeMs / 1000) * 200 / 8));

    // ── Préchauffage TCP ──────────────────────────────────────────────
    await prechauffageDownload(dureePrechauffage(dureeMs));

    // ── Mesure ────────────────────────────────────────────────────────
    const ctrl  = abortApres(dureeMs + 2000); // +2s de marge de sécurité
    const debut = performance.now();
    let   bytes = 0;

    const reponse = await fetch(
        window.FT_API.garbageEndpoint + '?size=' + tailleFichierMo + '&r=' + Math.random(),
        { cache: 'no-store', signal: ctrl.signal }
    );
    if (!reponse.ok) throw new Error('HTTP ' + reponse.status);

    const lecteur = reponse.body.getReader();
    try {
        while (true) {
            const { done, value } = await lecteur.read();
            if (done) break;
            bytes += value.byteLength;
            // Arrêt dès la durée cible atteinte — pas d'attente fin de fichier
            if (performance.now() - debut >= dureeMs) {
                await lecteur.cancel();
                break;
            }
        }
    } catch (_) {
        // AbortError normal à la fin de la durée — ignorer
    } finally {
        lecteur.releaseLock();
    }

    const dureeSec = (performance.now() - debut) / 1000;

    if (bytes === 0 || dureeSec <= 0) {
        throw new Error('[speedtest] Aucune mesure de téléchargement réussie — connexion indisponible.');
    }

    // Validation durée effective : si le stream s'est terminé en moins de DUREE_MESURE_MIN_MS,
    // le fichier était trop petit (impossible normalement avec la taille dynamique)
    // ou la connexion est en loopback/LAN — mesure non représentative.
    if (dureeSec * 1000 < DUREE_MESURE_MIN_MS) {
        throw new Error(
            `[speedtest] Durée effective trop courte (${(dureeSec * 1000).toFixed(0)} ms) — ` +
            'connexion locale détectée, mesure invalide.'
        );
    }

    const debitMbitps = (bytes * 8) / dureeSec / 1_000_000;

    // Plafond de sanité — ignoré si DEBIT_MAX_MBITPS = 0 (désactivé en config BDD)
    if (DEBIT_MAX_MBITPS > 0 && debitMbitps > DEBIT_MAX_MBITPS) {
        throw new Error(
            `[speedtest] Débit download aberrant (${debitMbitps.toFixed(1)} Mbit/s > ${DEBIT_MAX_MBITPS} Mbit/s) — ` +
            'connexion locale ou loopback détectée, mesure non enregistrée.'
        );
    }

    return debitMbitps.toFixed(2);
}

// ── Mesure de l'envoi ─────────────────────────────────────────────────────────

/**
 * Effectue le préchauffage TCP upload.
 *
 * Envoie un petit blob (256 KB) avant la vraie mesure pour établir
 * la connexion TCP et dépasser le slow start côté montant.
 */
/**
 * @param {number} dureeMs  Durée de préchauffage calculée via dureePrechauffage()
 */
async function prechauffageUpload(dureeMs) {
    try {
        const blobPrechauffage = new Blob([new Uint8Array(4 * 1024 * 1024)]);
        await fetch(
            window.FT_API.uploadEndpoint + '?r=' + Math.random(),
            {
                method:  'POST',
                body:    blobPrechauffage,
                cache:   'no-store',
                signal:  abortApres(dureeMs + 500).signal,
            }
        );
    } catch (_) { /* préchauffage échoué — continuer quand même */ }
}

/**
 * Mesure le débit montant via envoi continu pendant une durée fixe.
 *
 * Principe :
 *   1. Préchauffage TCP (connexion établie et slow start terminé)
 *   2. Crée un blob de tailleMoBlob MB (données aléatoires pour éviter compression)
 *   3. Envoie des requêtes POST successives jusqu'à dureeMsUpload ms
 *   4. L'endpoint serveur lit le body entier et renvoie les bytes reçus
 *   5. Calcul : bytes envoyés confirmés × 8 / durée effective (Mbit/s)
 *
 * @param {'precise'} mode
 * @returns {Promise<string>} Débit en Mbit/s (2 décimales)
 */
async function mesurerEnvoi(mode = 'precise') {
    const cfg = CONFIGS_MODE[mode]?.upload;
    if (!cfg) throw new Error(`[speedtest] Mode inconnu : ${mode}`);

    const dureeMs  = cfg.dureeMsUpload ?? 4000;
    const tailleMo = cfg.tailleMoBlob  ?? 8;

    // Données aléatoires pour éviter la compression réseau/HTTP
    // crypto.getRandomValues limité à 65536 bytes — remplir le reste par répétition
    const tampon = new Uint8Array(tailleMo * 1024 * 1024);
    crypto.getRandomValues(tampon.subarray(0, Math.min(tampon.length, 65536)));
    for (let i = 65536; i < tampon.length; i += 65536) {
        tampon.copyWithin(i, 0, Math.min(65536, tampon.length - i));
    }
    const blob = new Blob([tampon]);

    // ── Préchauffage TCP ──────────────────────────────────────────────
    await prechauffageUpload(dureePrechauffage(dureeMs));

    // ── Mesure ────────────────────────────────────────────────────────
    const debut      = performance.now();
    let bytesEnvoyes = 0;
    let continuer    = true;

    // Stopper la boucle après la durée cible
    const minuteur = setTimeout(() => { continuer = false; }, dureeMs);

    try {
        while (continuer) {
            const ctrl = abortApres(TIMEOUT_MESURE_MS);
            try {
                const reponse = await fetch(
                    window.FT_API.uploadEndpoint + '?r=' + Math.random(),
                    { method: 'POST', body: blob, cache: 'no-store', signal: ctrl.signal }
                );
                if (reponse.ok) {
                    // On compte blob.size sans attendre reponse.json() :
                    // upload_measure.php v1.9.2 répond immédiatement via Content-Length,
                    // attendre le JSON bloquerait la boucle pendant toute la durée
                    // de lecture du body côté serveur (~20s sur WAN 36 Mbit/s).
                    bytesEnvoyes += blob.size;
                    // Consommer la réponse sans bloquer
                    reponse.body?.cancel().catch(() => {});
                }
            } catch (_) {
                // Requête annulée ou réseau temporairement indisponible — continuer
            }
        }
    } finally {
        clearTimeout(minuteur);
    }

    const dureeSec = (performance.now() - debut) / 1000;

    if (bytesEnvoyes === 0 || dureeSec <= 0) {
        throw new Error("[speedtest] Aucune mesure d'envoi réussie — connexion indisponible.");
    }

    // Validation durée effective : si tous les bytes ont été envoyés en < DUREE_MESURE_MIN_MS,
    // une seule requête POST a suffi — le blob est trop petit ou c'est du LAN.
    if (dureeSec * 1000 < DUREE_MESURE_MIN_MS) {
        throw new Error(
            `[speedtest] Durée effective upload trop courte (${(dureeSec * 1000).toFixed(0)} ms) — ` +
            'connexion locale détectée, mesure invalide.'
        );
    }

    const debitMbitps = (bytesEnvoyes * 8) / dureeSec / 1_000_000;

    // Plafond de sanité upload — ignoré si DEBIT_MAX_MBITPS = 0
    if (DEBIT_MAX_MBITPS > 0 && debitMbitps > DEBIT_MAX_MBITPS) {
        throw new Error(
            `[speedtest] Débit upload aberrant (${debitMbitps.toFixed(1)} Mbit/s > ${DEBIT_MAX_MBITPS} Mbit/s) — ` +
            'connexion locale ou loopback détectée, mesure non enregistrée.'
        );
    }

    return debitMbitps.toFixed(2);
}