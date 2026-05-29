/**
 * index.js — v1.10.0
 *
 * Orchestre le test de débit en une phase unique ('precise', ~8s).
 * Suppression du mode fast — test unique enchaîné ping → download → upload.
 *
 * Dépendances :
 *   speedtest.js  → mesurerPing(), mesurerTelechargement(), mesurerEnvoi(),
 *                   chargerConfigSpeedtest()
 *   window.FT_API → URLs des endpoints (déclaré dans index.php <head>)
 */

'use strict';

// ══════════════════════════════════════════════════════════════════════
// UTILITAIRES DOM
// ══════════════════════════════════════════════════════════════════════

const getElementById = id => document.getElementById(id);
const masquer = (element, masque) => element?.classList.toggle('hidden', masque);
const definirTexte = (id, texte) => {
    const element = getElementById(id);
    if (element) element.textContent = texte;
};

// ══════════════════════════════════════════════════════════════════════
// ÉTAT GLOBAL
// ══════════════════════════════════════════════════════════════════════

const etat = {
    tokenFile: null,
    enCours:   false,
    abortCtrl: null,
};

const genererSessionId = () =>
    crypto.randomUUID?.() ??
    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const octet = Math.random() * 16 | 0;
        return (c === 'x' ? octet : (octet & 0x3 | 0x8)).toString(16);
    });

let identifiantSession = genererSessionId();

// ══════════════════════════════════════════════════════════════════════
// PHASES DE PROGRESSION — test unique
// ══════════════════════════════════════════════════════════════════════

const PHASES = {
    precise: [
        { libelle: '🔁\u00a0Ping…',           cible: 15 },
        { libelle: '⬇\u00a0Téléchargement…',  cible: 60 },
        { libelle: '⬆\u00a0Envoi…',            cible: 95 },
    ],
};

// ══════════════════════════════════════════════════════════════════════
// BARRE DE PROGRESSION
// ══════════════════════════════════════════════════════════════════════

let _progressionCourante = 0;
let _progressionCible    = 0;
let _minuteurProgression = null;

function majProgression(pct, libelle) {
    const barre = getElementById('global-progress-fill');
    if (barre) barre.style.width = pct.toFixed(1) + '%';
    definirTexte('global-progress-pct', Math.round(pct) + '\u00a0%');
    if (libelle) definirTexte('global-progress-label', libelle);
    getElementById('global-progress-box')?.setAttribute('aria-valuenow', String(Math.round(pct)));
}

function demarrerProgression() {
    _progressionCourante = 0;
    _progressionCible    = 0;
    masquer(getElementById('global-progress-box'), false);
    majProgression(0, PHASES.precise[0].libelle);
    clearInterval(_minuteurProgression);
    _minuteurProgression = setInterval(() => {
        if (_progressionCourante >= _progressionCible) return;
        _progressionCourante += (_progressionCible - _progressionCourante) * 0.03;
        if (_progressionCible - _progressionCourante < 0.2) {
            _progressionCourante = _progressionCible;
        }
        majProgression(_progressionCourante);
    }, 50);
}

function avancerProgression(mode, index) {
    const etape = PHASES[mode]?.[index];
    if (etape) {
        _progressionCible = etape.cible;
        majProgression(_progressionCourante, etape.libelle);
    }
}

function terminerProgression() {
    clearInterval(_minuteurProgression);
    majProgression(100, '✅\u00a0Terminé');
    setTimeout(() => masquer(getElementById('global-progress-box'), true), 1400);
}

const stopperProgression = () => {
    clearInterval(_minuteurProgression);
    masquer(getElementById('global-progress-box'), true);
};

// ══════════════════════════════════════════════════════════════════════
// ÉTAT DU BOUTON PRINCIPAL
// ══════════════════════════════════════════════════════════════════════

function definirEtatBouton(etatBouton) {
    const bouton = getElementById('btn-lancer');
    if (!bouton) return;

    const configurations = {
        connexion: { desactive: true,  texte: 'Connexion…',      classes: []          },
        running:   { desactive: true,  texte: 'Test en cours…',  classes: ['running'] },
        annuler:   { desactive: false, texte: 'Annuler',         classes: ['annuler'] },
        libre:     { desactive: false, texte: 'Tester à nouveau',classes: []          },
        reessayer: { desactive: false, texte: 'Réessayer',       classes: []          },
    };

    const config = configurations[etatBouton];
    bouton.disabled    = config.desactive;
    bouton.textContent = config.texte;
    bouton.classList.remove('running', 'annuler');
    config.classes.forEach(c => bouton.classList.add(c));
}

// ══════════════════════════════════════════════════════════════════════
// NOTICE DE STATUT
// ══════════════════════════════════════════════════════════════════════

function afficherNotice(type, texte) {
    const element = getElementById('save-notice');
    if (!element) return;
    element.className   = type === 'reset' ? 'save-notice' : `save-notice save-notice--${type}`;
    element.textContent = type === 'reset' ? '' : (texte ?? '');
}


// ══════════════════════════════════════════════════════════════════════
// FILE D'ATTENTE
// ══════════════════════════════════════════════════════════════════════

const TIMEOUT_FILE_MS = 45_000;

async function rejoindreFile() {
    const reponse = await fetch(window.FT_API.queueJoin, { signal: etat.abortCtrl.signal });
    const donnees = await reponse.json();
    etat.tokenFile = donnees.token;
    if (donnees.ready) return;

    definirEtatBouton('annuler');
    masquer(getElementById('queue-box'), false);
    const debutAttente = Date.now();

    await new Promise((resoudre, rejeter) => {
        const intervalle = setInterval(async () => {
            if (Date.now() - debutAttente > TIMEOUT_FILE_MS) {
                clearInterval(intervalle);
                rejeter(new Error('timeout_file'));
                return;
            }
            if (etat.abortCtrl.signal.aborted) {
                clearInterval(intervalle);
                rejeter(new Error('annule'));
                return;
            }

            try {
                const statut = await (await fetch(
                    window.FT_API.queueStatus + etat.tokenFile,
                    { signal: etat.abortCtrl.signal }
                )).json();

                if (statut.error) {
                    clearInterval(intervalle);
                    const renouvellement = await (await fetch(
                        window.FT_API.queueJoin,
                        { signal: etat.abortCtrl.signal }
                    )).json();
                    etat.tokenFile = renouvellement.token;
                    renouvellement.ready
                        ? resoudre()
                        : rejoindreFile().then(resoudre).catch(rejeter);

                } else if (statut.ready) {
                    clearInterval(intervalle);
                    masquer(getElementById('queue-box'), true);
                    resoudre();

                } else {
                    definirTexte('queue-position', statut.position === 1
                        ? 'Position 1 — Vous êtes le prochain…'
                        : `Position ${statut.position} — attente estimée\u00a0: ${statut.position * 30}s`
                    );
                }
            } catch (erreur) {
                if (erreur.name === 'AbortError') {
                    clearInterval(intervalle);
                    rejeter(new Error('annule'));
                }
            }
        }, 2000);
    });

    definirEtatBouton('running');
}

function libererFile() {
    if (!etat.tokenFile) return;
    navigator.sendBeacon(window.FT_API.queueDone + etat.tokenFile);
    etat.tokenFile = null;
}

// ══════════════════════════════════════════════════════════════════════
// ANIMATION DES VALEURS
// ══════════════════════════════════════════════════════════════════════

function animerValeur(id, barId, cible, max, duree, dec) {
    const element = getElementById(id);
    const barre   = getElementById(barId);
    if (!element || !barre) return;

    const depart = performance.now();
    (function etape(maintenant) {
        const progression = Math.min((maintenant - depart) / duree, 1);
        element.textContent  = (cible * progression).toFixed(dec);
        barre.style.width    = Math.min((cible / max) * 100, 100) * progression + '%';
        if (progression < 1) {
            requestAnimationFrame(etape);
        } else {
            element.textContent = cible.toFixed(dec);
        }
    })(depart);
}

// ══════════════════════════════════════════════════════════════════════
// CONFIGURATION DES MÉTRIQUES
// ══════════════════════════════════════════════════════════════════════

const METRIQUES = [
    { id: 'ping',     prefixeBarre: 'ping', max: 100, dureeAnim: 600,  decimales: 2 },
    { id: 'download', prefixeBarre: 'dl',   max: 200, dureeAnim: 1000, decimales: 2 },
    { id: 'upload',   prefixeBarre: 'ul',   max: 200, dureeAnim: 1000, decimales: 2 },
];

// ══════════════════════════════════════════════════════════════════════
// REMISE À ZÉRO DES SECTIONS
// ══════════════════════════════════════════════════════════════════════

function reinitialiserSection(mode) {
    METRIQUES.forEach(({ id, prefixeBarre }) => {
        definirTexte(`${id}-${mode}`, '--');

        const barre   = getElementById(`${prefixeBarre}-bar-${mode}`);
        const verdict = getElementById(`verdict-${id}-${mode}`);
        const desc    = getElementById(`desc-${id}-${mode}`);

        if (barre)   barre.style.width   = '0%';
        if (verdict) { verdict.className = 'card-verdict';      verdict.textContent = ''; }
        if (desc)    { desc.className    = 'card-verdict-desc'; desc.textContent    = ''; }
    });
}

// ══════════════════════════════════════════════════════════════════════
// EXÉCUTION D'UNE PHASE
// ══════════════════════════════════════════════════════════════════════

async function executerPhase(mode) {
    const fonctionsMesure = [mesurerPing, mesurerTelechargement, mesurerEnvoi];
    const resultats       = [];

    for (let i = 0; i < METRIQUES.length; i++) {
        avancerProgression(mode, i);
        const { id, prefixeBarre, max, dureeAnim, decimales } = METRIQUES[i];
        const valeur = parseFloat(await fonctionsMesure[i](mode));
        animerValeur(`${id}-${mode}`, `${prefixeBarre}-bar-${mode}`, valeur, max, dureeAnim, decimales);
        resultats.push(valeur);
    }

    return { ping: resultats[0], dl: resultats[1], ul: resultats[2] };
}

// ══════════════════════════════════════════════════════════════════════
// SAUVEGARDE DES RÉSULTATS
// ══════════════════════════════════════════════════════════════════════

async function sauvegarderPhase(mode, ping, dl, ul, afficherConfirmation) {
    try {
        const reponse = await (await fetch(window.FT_API.saveResult, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                ping,
                download:   dl,
                upload:     ul,
                mode,
                session_id: identifiantSession,
            }),
        })).json();

        if (!afficherConfirmation) return;

        reponse.success
            ? afficherNotice('ok',  '✓\u00a0Résultat enregistré pour le site\u00a0' + reponse.CODE_GX_SITE)
            : afficherNotice('ok',  '✓\u00a0Test terminé — résultat enregistré.');

    } catch (_) {
        if (afficherConfirmation) {
            afficherNotice('err', '⚠\u00a0Impossible de contacter le serveur pour enregistrer le résultat.');
        }
    }
}

// ══════════════════════════════════════════════════════════════════════
// GESTION DES ERREURS
// ══════════════════════════════════════════════════════════════════════

const MESSAGES_ERREUR = {
    annule:       null,
    timeout_file: '⚠\u00a0La file d\'attente a expiré. Veuillez réessayer.',
};

function estErreurAberrante(erreur) {
    return erreur?.message?.includes('aberrant') || erreur?.message?.includes('loopback') || erreur?.message?.includes('trop courte');
}

function gererEchecTest(erreur) {
    stopperProgression();
    masquer(getElementById('queue-box'), true);

    const message = estErreurAberrante(erreur)
        ? '⚠\u00a0Mesure invalide : débit anormalement élevé détecté (connexion locale ou loopback). Le résultat n\'a pas été enregistré. Contactez la DSI si ce message se répète.'
        : (MESSAGES_ERREUR[erreur.message]
            ?? '⚠\u00a0Une erreur réseau est survenue. Vérifiez votre connexion et réessayez.');
    message ? afficherNotice('err', message) : afficherNotice('reset');

    libererFile();
    etat.enCours = false;
    definirEtatBouton('reessayer');
}

// ══════════════════════════════════════════════════════════════════════
// POINT D'ENTRÉE DU BOUTON
// ══════════════════════════════════════════════════════════════════════

async function gererClicBouton() {
    const bouton = getElementById('btn-lancer');
    if (!bouton) return;
    if (bouton.classList.contains('annuler')) {
        etat.abortCtrl?.abort();
        return;
    }
    if (bouton.disabled) return;
    await lancerTestComplet();
}

/**
 * Lance le test complet — phase unique 'precise'.
 */
async function lancerTestComplet() {
    identifiantSession = genererSessionId();
    etat.abortCtrl     = new AbortController();
    etat.enCours       = true;
    libererFile();

    // Remise à zéro de l'interface
    definirEtatBouton('connexion');
    afficherNotice('reset');
    stopperProgression();
    masquer(getElementById('section-precise'), true);
    reinitialiserSection('precise');

    try {
        await rejoindreFile();
        demarrerProgression();

        // ── Test unique ─────────────────────────────────────────────
        masquer(getElementById('section-precise'), false);
        const mesures = await executerPhase('precise');
        await sauvegarderPhase('precise', mesures.ping, mesures.dl, mesures.ul, true);
        await chargerSeuils();
        afficherBilanSection('precise', mesures.ping, mesures.dl, mesures.ul);
        libererFile();
        terminerProgression();
        etat.enCours = false;
        definirEtatBouton('libre');

    } catch (erreur) {
        gererEchecTest(erreur);
    }
}

// ══════════════════════════════════════════════════════════════════════
// INITIALISATION DE LA PAGE
// ══════════════════════════════════════════════════════════════════════

function initialiserPage() {
    chargerConfigSpeedtest();

    getElementById('btn-lancer')     ?.addEventListener('click', gererClicBouton);
    getElementById('tooltip-fermer') ?.addEventListener('click', fermerTooltip);

    // Encart pédagogique repliable
    const boutonToggle = getElementById('encart-aide-toggle');
    const corpsEncart  = getElementById('encart-aide-corps');
    boutonToggle?.addEventListener('click', () => {
        const estOuvert = !corpsEncart.classList.contains('hidden');
        corpsEncart.classList.toggle('hidden', estOuvert);
        boutonToggle.textContent = estOuvert ? 'Afficher ▼' : 'Masquer ▲';
        boutonToggle.setAttribute('aria-expanded', String(!estOuvert));
    });

    // Délégation des clics sur les boutons tooltip
    document.addEventListener('click', evenement => {
        const bouton = evenement.target.closest('.tooltip-btn');
        if (bouton) ouvrirTooltip(bouton.dataset.tooltipTitre, bouton.dataset.tooltipCorps);
    });

    // Fermer le tooltip en cliquant sur le fond
    getElementById('tooltip-overlay')?.addEventListener('click', evenement => {
        if (evenement.target.id === 'tooltip-overlay') fermerTooltip();
    });

    // Détection IP et site de l'utilisateur
    const ipTimeout = new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 4000));
    Promise.race([fetch(window.FT_API.getIP).then(r => r.json()), ipTimeout])
        .then(({ ip, site }) => {
            definirTexte('ip',   'IP\u00a0: '   + (ip   ?? '—'));
            definirTexte('site', 'Site\u00a0: ' + (site ?? '—'));
        })
        .catch(() => {
            definirTexte('ip',   'IP\u00a0: —');
            definirTexte('site', 'Site\u00a0: —');
        });

    chargerSeuils().then(mettreAJourEncartSeuils);
}

// ══════════════════════════════════════════════════════════════════════
// TOOLTIP
// ══════════════════════════════════════════════════════════════════════

function ouvrirTooltip(titre, corps) {
    definirTexte('tooltip-titre', titre ?? '');
    definirTexte('tooltip-corps', corps ?? '');
    masquer(getElementById('tooltip-overlay'), false);
    document.body.style.overflow = 'hidden';
    getElementById('tooltip-fermer')?.focus();
}

function fermerTooltip() {
    masquer(getElementById('tooltip-overlay'), true);
    document.body.style.overflow = '';
}

document.addEventListener('keydown', evenement => {
    if (evenement.key === 'Escape') fermerTooltip();
});

// ══════════════════════════════════════════════════════════════════════
// SEUILS & VERDICTS
// ══════════════════════════════════════════════════════════════════════

let seuilsCache = null;

async function chargerSeuils() {
    if (seuilsCache) return;
    try {
        const reponse = await fetch(window.FT_API.getSeuils);
        if (!reponse.ok) throw new Error('HTTP ' + reponse.status);
        const donnees = await reponse.json();
        if (donnees?.ping && donnees?.download && donnees?.upload) {
            seuilsCache = donnees;
        }
    } catch (erreur) {
        console.warn('[index.js] Seuils non chargés :', erreur);
        seuilsCache = {};
    }
}

function mettreAJourEncartSeuils() {
    if (!seuilsCache?.ping) return;
    const s = seuilsCache;

    [
        ['encart-ping-confort',     `< ${s.ping.bon}\u00a0ms`],
        ['encart-ping-fonctionnel', `${s.ping.bon}–${s.ping.mauvais}\u00a0ms`],
        ['encart-ping-insuffisant', `> ${s.ping.mauvais}\u00a0ms`],
        ['encart-dl-confort',       `> ${s.download.bon}\u00a0Mbit/s`],
        ['encart-dl-fonctionnel',   `${s.download.mauvais}–${s.download.bon}\u00a0Mbit/s`],
        ['encart-dl-insuffisant',   `< ${s.download.mauvais}\u00a0Mbit/s`],
        ['encart-ul-confort',       `> ${s.upload.bon}\u00a0Mbit/s`],
        ['encart-ul-fonctionnel',   `${s.upload.mauvais}–${s.upload.bon}\u00a0Mbit/s`],
        ['encart-ul-insuffisant',   `< ${s.upload.mauvais}\u00a0Mbit/s`],
    ].forEach(([id, texte]) => definirTexte(id, texte));
}

function obtenirVerdict(metrique, valeur) {
    const seuil = seuilsCache?.[metrique];
    if (!seuil) return null;

    if (metrique === 'ping') {
        return valeur <= seuil.bon ? 'confort' : valeur >= seuil.mauvais ? 'insuffisant' : 'fonctionnel';
    }
    return valeur >= seuil.bon ? 'confort' : valeur <= seuil.mauvais ? 'insuffisant' : 'fonctionnel';
}

const VERDICTS_CONFIG = {
    confort:     { icone: '✅', libelle: 'Confort'      },
    fonctionnel: { icone: '⚠️', libelle: 'Fonctionnel'  },
    insuffisant: { icone: '❌', libelle: 'Insuffisant'   },
};

const TEXTES_VERDICT = {
    ping: {
        confort:     'Réactivité excellente, navigation et visioconférence fluides.',
        fonctionnel: 'Légères lenteurs possibles sur les applications sensibles.',
        insuffisant: 'Latence élevée, risque de décrochages en visioconférence.',
    },
    download: {
        confort:     'Débit suffisant pour toutes les applications métier.',
        fonctionnel: 'Navigation correcte, mais les gros fichiers seront lents.',
        insuffisant: 'Débit trop faible, chargement des applications dégradé.',
    },
    upload: {
        confort:     'Envoi de fichiers et visioconférence dans de bonnes conditions.',
        fonctionnel: 'Envoi acceptable, qualité vidéo réduite en visio.',
        insuffisant: "Envoi très lent, visioconférence ou partage d'écran difficile.",
    },
};

function afficherBilanSection(mode, ping, dl, ul) {
    [['ping', ping], ['download', dl], ['upload', ul]].forEach(([metrique, valeur]) => {
        const verdict = obtenirVerdict(metrique, valeur);
        if (!verdict) return;

        const badge       = getElementById(`verdict-${metrique}-${mode}`);
        const description = getElementById(`desc-${metrique}-${mode}`);
        const cfg         = VERDICTS_CONFIG[verdict];

        if (badge) {
            badge.className   = 'card-verdict ' + verdict;
            badge.textContent = cfg.icone + '\u00a0' + cfg.libelle;
        }
        if (description) {
            description.className   = 'card-verdict-desc ' + verdict;
            description.textContent = TEXTES_VERDICT[metrique][verdict];
        }
    });
}

// ══════════════════════════════════════════════════════════════════════
// GESTION DE LA FERMETURE DE PAGE
// ══════════════════════════════════════════════════════════════════════

window.addEventListener('beforeunload', (e) => {
    if (etat.enCours) {
        e.preventDefault();
        e.returnValue = '';
    }
    etat.abortCtrl?.abort();
    libererFile();
});

window.addEventListener('pagehide', () => {
    etat.abortCtrl?.abort();
    libererFile();
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && etat.tokenFile && !etat.enCours) {
        libererFile();
    }
});

// ── Démarrage ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', initialiserPage);