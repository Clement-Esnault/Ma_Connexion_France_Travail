/**
 * import_logs.js — Logique de la page d'import de logs CSV
 *
 * Gère :
 *   - Sélection de fichier via clic ou drag & drop
 *   - Validation du format (.csv)
 *   - Envoi multipart POST vers backend/admin/import_logs.php
 *   - Affichage du résumé d'import (insérés / écrasés / ignorés / erreurs)
 *   - Protection F5 pendant l'import via activerGardePage() (utils.js)
 */

const drop  = document.getElementById('import-drop');
const input = document.getElementById('import-file-input');
const btn   = document.getElementById('import-btn');
const nomEl = document.getElementById('import-file-name');

let fichierSelectionne = null;

// ── Sélection fichier ─────────────────────────────────────────────────
input.addEventListener('change', () => {
    if (input.files[0]) selectionnerFichier(input.files[0]);
});

drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('dragover'); });
drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
drop.addEventListener('drop', e => {
    e.preventDefault();
    drop.classList.remove('dragover');
    if (e.dataTransfer.files[0]) selectionnerFichier(e.dataTransfer.files[0]);
});

/**
 * Valide et mémorise le fichier sélectionné.
 * @param {File} f
 */
function selectionnerFichier(f) {
    if (!f.name.endsWith('.csv') && f.type !== 'text/csv') {
        alert('Format non supporté — veuillez sélectionner un fichier .csv');
        return;
    }
    fichierSelectionne    = f;
    nomEl.textContent     = f.name + ' (' + (f.size / 1024).toFixed(1) + ' Ko)';
    btn.disabled          = false;
    document.getElementById('import-result').style.display = 'none';
}

// ── Import ────────────────────────────────────────────────────────────
/**
 * Envoie le fichier CSV en POST et affiche le résultat.
 */
async function lancerImport() {
    if (!fichierSelectionne) return;

    btn.disabled    = true;
    btn.textContent = 'Import en cours…';
    const garde     = activerGardePage();

    const formData  = new FormData();
    formData.append('fichier_csv', fichierSelectionne);

    try {
        const res  = await fetch('../../backend/admin/import_logs.php', {
            method:      'POST',
            body:        formData,
            credentials: 'same-origin',
        });
        const data = await res.json();
        afficherResultat(data);
    } catch (e) {
        afficherResultat({ error: 'Erreur réseau : ' + e.message });
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Importer';
        garde.desactiver();
    }
}

// ── Affichage résultat ────────────────────────────────────────────────
/**
 * Affiche le résumé d'import retourné par le backend.
 * @param {object} data  Réponse JSON du backend
 */
function afficherResultat(data) {
    const el    = document.getElementById('import-result');
    const titre = document.getElementById('import-result-titre');
    const stats = document.getElementById('import-result-stats');
    const errs  = document.getElementById('import-erreurs');

    el.style.display = 'block';
    errs.innerHTML   = '';
    stats.innerHTML  = '';

    if (data.error) {
        el.className      = 'import-result erreur';
        titre.textContent = '❌ Erreur : ' + data.error;
        return;
    }

    el.className      = 'import-result ok';
    titre.textContent = '✅ Import terminé — ' + data.total_lignes + ' ligne(s) traitée(s)';

    [
        { val: data.inseres, lbl: 'Insérés'  },
        { val: data.ecrases, lbl: 'Écrasés'  },
        { val: data.ignores, lbl: 'Ignorés'  },
    ].forEach(({ val, lbl }) => {
        stats.innerHTML +=
            '<div class="import-stat">' +
            '<div class="import-stat-val">' + val + '</div>' +
            '<div class="import-stat-lbl">' + lbl + '</div>' +
            '</div>';
    });

    if (data.erreurs?.length) {
        el.className    = 'import-result erreur';
        errs.innerHTML  = '<strong>Erreurs :</strong>';
        data.erreurs.forEach(e => {
            errs.innerHTML += '<li>' + e + '</li>';
        });
        if (data.total_lignes > 20) {
            errs.innerHTML += '<li><em>… (max 20 erreurs affichées)</em></li>';
        }
    }
}