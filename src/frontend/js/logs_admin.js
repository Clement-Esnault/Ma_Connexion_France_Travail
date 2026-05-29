/**
 * logs_admin.js — Coloration du tableau admin des logs.
 *
 * Dépend de utils.js (SEUILS, chargerSeuils, classCouleur, tooltipVerdict).
 *
 * Colonnes du tableau (index TD 0-based) :
 *   0=# | 1=Date | 2=Site | 3=Région | 4=Interrégion | 5=IP
 *   6=Ping | 7=Téléchargement | 8=Envoi | [9=Supprimer si admin]
 */

/**
 * Parcourt toutes les lignes du tableau .logs-table et applique les classes
 * cell-bon / cell-moyen / cell-mauvais sur les colonnes Ping, DL et Upload
 * selon les seuils FT_SEUILS chargés dynamiquement.
 * Ajoute également un title= d'explication sur chaque cellule.
 */
function colorierTableau() {
    const CFG = [
        { idx: 6, seuil: SEUILS.ping,     metrique: 'ping',     inverse: true  },
        { idx: 7, seuil: SEUILS.download, metrique: 'download', inverse: false },
        { idx: 8, seuil: SEUILS.upload,   metrique: 'upload',   inverse: false },
    ];

    document.querySelectorAll('.logs-table tbody tr').forEach(tr => {
        const tds = tr.querySelectorAll('td');
        CFG.forEach(({ idx, seuil, metrique, inverse }) => {
            const td  = tds[idx];
            if (!td) return;
            const val = parseFloat(td.textContent);
            if (isNaN(val)) return;
            td.className = classCouleur(val, seuil, inverse);
            td.title     = tooltipVerdict(val, seuil, metrique, inverse);
        });
    });
}

// frontend/admin/ → ../../ pour remonter à la racine du projet
// Charge les seuils depuis la BDD, puis colorie le tableau
chargerSeuils().then(colorierTableau);