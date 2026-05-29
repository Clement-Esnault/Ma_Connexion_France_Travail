// config_speedtest.js — logique de la page admin de configuration du moteur de speedtest.
// Requiert que CSRF_TOKEN soit défini en variable globale par config_speedtest.php avant ce script.
// v1.10 — mode unique 'precise' : dureeMsDownload, dureeMsUpload, parallel, tailleMoBlob

// ── Chargement de la config au démarrage ──────────────────────────────
async function chargerConfig() {
    try {
        const res  = await fetch('../../backend/ip/get_config_speedtest.php?r=' + Math.random(),
            { cache: 'no-store' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error ?? 'Erreur inconnue');

        const cfg = data.config;

        // Remplit chaque input dont l'id correspond à precise_{mesure}_{param}
        ['ping', 'download', 'upload'].forEach(mesure => {
            Object.entries(cfg['precise']?.[mesure] ?? {}).forEach(([param, val]) => {
                const el = document.getElementById(`precise_${mesure}_${param}`);
                if (el) el.value = val;
            });
        });

        const inputDebitMax = document.getElementById('debit_max_mbitps');
        if (inputDebitMax && cfg.debitMaxMbitps !== undefined) {
            inputDebitMax.value = cfg.debitMaxMbitps;
        }

        document.getElementById('loading-config').style.display = 'none';
        document.getElementById('config-form').style.display    = 'block';

    } catch (err) {
        document.getElementById('loading-config').innerHTML =
            '<span style="color:#E1000F">⚠ Impossible de charger la configuration : '
            + err.message + '</span>';
    }
}

// ── Validation des paramètres ─────────────────────────────────────────
function validerConfig() {
    const conteneur = document.getElementById('alertes-validation');
    conteneur.innerHTML = '';

    const avertissements = [];
    const erreurs        = [];

    // ── Ping ──────────────────────────────────────────────────────
    const echantillons = parseInt(document.getElementById('precise_ping_echantillons')?.value, 10);
    const delay        = parseInt(document.getElementById('precise_ping_delay')?.value,        10);

    if (echantillons < 3) {
        avertissements.push(`Ping : seulement ${echantillons} mesure(s) — résultats peu fiables. Recommandé : ≥ 5.`);
    }
    if (delay < 10 && echantillons > 10) {
        avertissements.push(`Ping : délai très court (${delay} ms) avec ${echantillons} mesures — risque de saturation.`);
    }

    // ── Download ──────────────────────────────────────────────────
    const dureeDown    = parseInt(document.getElementById('precise_download_dureeMsDownload')?.value, 10);
    const parallelDown = parseInt(document.getElementById('precise_download_parallel')?.value,        10);

    if (isNaN(dureeDown) || dureeDown < 500) {
        erreurs.push('Download : durée minimum 500 ms.');
    }
    if (parallelDown > 3) {
        avertissements.push(`Download : ${parallelDown} connexions parallèles — peut saturer les petits sites.`);
    }

    // ── Upload ────────────────────────────────────────────────────
    const dureeUp      = parseInt(document.getElementById('precise_upload_dureeMsUpload')?.value,  10);
    const tailleMoBlob = parseInt(document.getElementById('precise_upload_tailleMoBlob')?.value,   10);
    const parallelUp   = parseInt(document.getElementById('precise_upload_parallel')?.value,       10);

    if (isNaN(dureeUp) || dureeUp < 500) {
        erreurs.push('Upload : durée minimum 500 ms.');
    }
    if (isNaN(tailleMoBlob) || tailleMoBlob < 1) {
        erreurs.push('Upload : taille blob minimum 1 Mo.');
    }

    const dureeMinBlobS = (tailleMoBlob * 8) / 3;
    if (dureeMinBlobS * 1000 < dureeUp) {
        avertissements.push(
            `Upload : blob de ${tailleMoBlob} Mo peut être envoyé trop vite sur les connexions rapides `
            + `(${dureeMinBlobS.toFixed(1)}s à 3 Mbit/s < ${dureeUp / 1000}s de mesure). `
            + `Augmenter la taille ou réduire la durée.`
        );
    }

    if (parallelUp > 3) {
        avertissements.push(`Upload : ${parallelUp} connexions parallèles — peut saturer les petits sites.`);
    }

    // ── Affichage ─────────────────────────────────────────────────
    for (const err of erreurs) {
        conteneur.innerHTML += `<div class="alerte alerte-erreur">⛔ ${err}</div>`;
    }
    for (const warn of avertissements) {
        conteneur.innerHTML += `<div class="alerte alerte-avertissement">⚠️ ${warn}</div>`;
    }

    return erreurs.length === 0;
}

// ── Sauvegarde via AJAX ───────────────────────────────────────────────
async function sauvegarder() {
    const btn    = document.getElementById('btn-save');
    const msgOk  = document.getElementById('msg-ok');
    const msgErr = document.getElementById('msg-err');

    if (!validerConfig()) return;

    btn.disabled    = true;
    btn.textContent = 'Enregistrement…';
    msgOk.style.display = msgErr.style.display = 'none';
    const garde = activerGardePage();

    const config = { precise: {} };
    ['ping', 'download', 'upload'].forEach(mesure => {
        config['precise'][mesure] = {};
        document.querySelectorAll(`[id^="precise_${mesure}_"]`).forEach(input => {
            const param = input.id.replace(`precise_${mesure}_`, '');
            config['precise'][mesure][param] = parseFloat(input.value);
        });
    });

    try {
        const res  = await fetch('../../backend/admin/save_config_speedtest.php', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN,
            },
            body: JSON.stringify({
                ...config,
                debit_max_mbitps: parseInt(document.getElementById('debit_max_mbitps')?.value ?? 1000, 10),
            }),
        });
        const data = await res.json();

        if (data.success) {
            msgOk.textContent   = '✓ ' + data.message;
            msgOk.style.display = 'block';
            msgOk.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            msgErr.textContent   = '⚠ ' + (data.error ?? 'Erreur inconnue');
            msgErr.style.display = 'block';
        }
    } catch (err) {
        msgErr.textContent   = '⚠ Impossible de contacter le serveur.';
        msgErr.style.display = 'block';
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Enregistrer';
        garde.desactiver();
    }
}

chargerConfig();