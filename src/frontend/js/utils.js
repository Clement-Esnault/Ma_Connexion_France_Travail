/**
 * utils.js — Utilitaires partagés par toutes les pages France Débit.
 *
 * Fournit :
 *   - fetchJson()         Wrapper fetch avec vérification HTTP
 *   - SEUILS              Seuils de qualité réseau (chargés depuis la BDD)
 *   - chargerSeuils()     Charge les seuils depuis get_seuils.php
 *   - classCouleur()      Retourne la classe CSS cell-bon/moyen/mauvais
 *   - tooltipVerdict()    Texte explicatif pour le title="" d'une cellule
 *   - echapper()          Échappe le HTML pour innerHTML sécurisé
 *   - creerPagination()   Génère un bloc de pagination dans un élément DOM
 *
 * Doit être chargé avant tous les autres scripts de page.
 */

// ── Fetch JSON générique ──────────────────────────────────────────────
/**
 * Effectue un fetch et retourne le JSON parsé.
 * Lève une Error si la réponse HTTP n'est pas ok (status 2xx).
 *
 * @param  {string} url
 * @param  {RequestInit} [options]
 * @returns {Promise<any>}
 */
async function fetchJson(url, options = {}) {
    const res = await fetch(url, options);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
}

// ── Seuils de qualité ─────────────────────────────────────────────────
/**
 * Valeurs de seuil par défaut — remplacées au chargement par chargerSeuils().
 * Format : { bon: number, mauvais: number }
 *   ping     : inverse (plus bas = meilleur)
 *   download : plus haut = meilleur
 *   upload   : plus haut = meilleur
 */
const SEUILS = {
    ping:     { bon: 50,  mauvais: 100 },
    download: { bon: 5,   mauvais: 3   },
    upload:   { bon: 5,   mauvais: 3   },
};

/**
 * Charge les seuils depuis la BDD via get_seuils.php et met à jour SEUILS.
 * En cas d'erreur réseau, conserve les valeurs par défaut silencieusement.
 *
 * @returns {Promise<void>}
 */
async function chargerSeuils() {
    try {
        const res = await fetch('/backend/admin/get_seuils.php');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (data.ping)     SEUILS.ping     = data.ping;
        if (data.download) SEUILS.download = data.download;
        if (data.upload)   SEUILS.upload   = data.upload;
    } catch (err) {
        console.warn('[utils.js] Seuils non chargés, valeurs par défaut :', err);
    }
}

// ── Coloration des cellules ───────────────────────────────────────────
/**
 * Retourne la classe CSS de couleur pour une cellule selon la valeur et le seuil.
 *
 * @param  {number}  val      Valeur mesurée
 * @param  {{bon: number, mauvais: number}} seuil
 * @param  {boolean} [inverse=false]  true pour ping (plus bas = meilleur)
 * @returns {'cell-bon'|'cell-moyen'|'cell-mauvais'}
 */
function classCouleur(val, seuil, inverse = false) {
    if (inverse) {
        if (val <= seuil.bon)     return 'cell-bon';
        if (val >= seuil.mauvais) return 'cell-mauvais';
    } else {
        if (val >= seuil.bon)     return 'cell-bon';
        if (val <= seuil.mauvais) return 'cell-mauvais';
    }
    return 'cell-moyen';
}

/**
 * Génère le texte de tooltip explicatif pour une cellule de mesure.
 *
 * @param  {number}  val
 * @param  {{bon: number, mauvais: number}} seuil
 * @param  {string}  metrique  'ping' | 'download' | 'upload'
 * @param  {boolean} [inverse=false]
 * @returns {string}
 */
function tooltipVerdict(val, seuil, metrique, inverse = false) {
    const unite  = metrique === 'ping' ? 'ms' : 'Mbit/s';
    const classe = classCouleur(val, seuil, inverse);
    if (classe === 'cell-bon')
        return inverse
            ? `✅ Bon : ${val} ${unite} ≤ seuil confort (${seuil.bon} ${unite})`
            : `✅ Bon : ${val} ${unite} ≥ seuil confort (${seuil.bon} ${unite})`;
    if (classe === 'cell-mauvais')
        return inverse
            ? `❌ Insuffisant : ${val} ${unite} ≥ seuil critique (${seuil.mauvais} ${unite})`
            : `❌ Insuffisant : ${val} ${unite} ≤ seuil critique (${seuil.mauvais} ${unite})`;
    return inverse
        ? `⚠️ Fonctionnel : entre ${seuil.bon} et ${seuil.mauvais} ${unite}`
        : `⚠️ Fonctionnel : entre ${seuil.mauvais} et ${seuil.bon} ${unite}`;
}

// ── Échappement HTML ──────────────────────────────────────────────────
/**
 * Échappe les caractères HTML spéciaux pour usage sécurisé dans innerHTML.
 *
 * @param  {any} chaine
 * @returns {string}
 */
function echapper(chaine) {
    return String(chaine ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Pagination générique ──────────────────────────────────────────────
/**
 * Génère un bloc de pagination dans l'élément DOM identifié par `id`.
 * Affiche : [← Précédent] [info page / total] [aller à X] [Suivant →]
 *
 * Ne génère rien si totalPages <= 1.
 *
 * @param {string}   id               ID de l'élément conteneur
 * @param {number}   pageActuelle     Numéro de page courant (1-based)
 * @param {number}   totalPages       Nombre total de pages
 * @param {number}   total            Nombre total d'éléments
 * @param {string}   libelle          Libellé des éléments ('logs', 'résultats'…)
 * @param {function} auChangementPage Callback appelé avec le numéro de page cible
 */
function creerPagination(id, pageActuelle, totalPages, total, libelle, auChangementPage) {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = '';
    if (totalPages <= 1) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'pagination-wrapper';

    // ← Précédent
    const precedent = document.createElement('button');
    precedent.textContent = '← Précédent';
    precedent.className   = 'btn-page';
    precedent.disabled    = pageActuelle <= 1;
    precedent.onclick     = () => auChangementPage(pageActuelle - 1);
    wrapper.appendChild(precedent);

    // Info page
    const info = document.createElement('span');
    info.className   = 'pagination-info';
    info.textContent = `Page ${pageActuelle} / ${totalPages} — ${total} ${libelle}`;
    wrapper.appendChild(info);

    // Aller à la page X
    const allerWrapper = document.createElement('span');
    allerWrapper.className = 'pagination-aller';

    const labelAller = document.createElement('span');
    labelAller.textContent = 'Aller à :';
    allerWrapper.appendChild(labelAller);

    const input = document.createElement('input');
    input.type      = 'number';
    input.min       = '1';
    input.max       = String(totalPages);
    input.value     = String(pageActuelle);
    input.className = 'pagination-input';
    input.title     = `Page entre 1 et ${totalPages}`;

    const aller = () => {
        const p = parseInt(input.value, 10);
        if (p >= 1 && p <= totalPages && p !== pageActuelle) {
            auChangementPage(p);
        } else {
            input.value = String(pageActuelle);
        }
    };
    input.addEventListener('keydown', e => { if (e.key === 'Enter') aller(); });
    input.addEventListener('blur', aller);
    allerWrapper.appendChild(input);

    const labelSur = document.createElement('span');
    labelSur.textContent = `/ ${totalPages}`;
    allerWrapper.appendChild(labelSur);
    wrapper.appendChild(allerWrapper);

    // Suivant →
    const suivant = document.createElement('button');
    suivant.textContent = 'Suivant →';
    suivant.className   = 'btn-page';
    suivant.disabled    = pageActuelle >= totalPages;
    suivant.onclick     = () => auChangementPage(pageActuelle + 1);
    wrapper.appendChild(suivant);

    el.appendChild(wrapper);
}
// ── Téléchargement CSV générique ──────────────────────────────────────
/**
 * Déclenche le téléchargement d'un fichier CSV côté client.
 *
 * @param {string[]} lignes      Tableau de lignes déjà formatées (avec séparateur)
 * @param {string}   nomFichier  Nom du fichier téléchargé
 * @param {string}   separateur  ';' ou ','
 * @param {boolean}  bom         true = UTF-8 BOM (pour Excel)
 */
function telechargerCSV(lignes, nomFichier, separateur, bom) {
    const contenu = lignes.join('\r\n');
    const prefixe = bom ? '\uFEFF' : '';
    const blob = new Blob([prefixe + contenu], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const lien = Object.assign(document.createElement('a'), { href: url, download: nomFichier });
    document.body.appendChild(lien);
    lien.click();
    setTimeout(() => { URL.revokeObjectURL(url); lien.remove(); }, 1000);
}

// ══════════════════════════════════════════════════════════════════════
// MODAL EXPORT GENERIQUE — utils.js
// Fournit ouvrirModalExport(config) utilisable sur toutes les pages.
//
// config = {
//   titre       : string,
//   type        : 'csv' | 'pdf',
//   colonnes    : [{ id, label, checked }],   // optionnel
//   avecStats   : boolean,                    // afficher option stats
//   avecCouleurs: boolean,                    // afficher option couleurs (PDF)
//   seuilsRecap : string,                     // HTML recap seuils (optionnel)
//   onConfirm   : function(opts)              // callback avec les options choisies
//     opts = { separateur, bom, colonnes[], stats, couleurs }
// }
// ══════════════════════════════════════════════════════════════════════

(function() {
    // Injecter le HTML du modal une seule fois
    function _injecterModal() {
        if (document.getElementById('export-modal-overlay')) return;
        const div = document.createElement('div');
        div.innerHTML = `
<div id="export-modal-overlay" class="export-modal-overlay">
  <div class="export-modal" role="dialog" aria-modal="true">
    <div class="export-modal-header">
      <span class="export-modal-icon" id="export-modal-icon"></span>
      <span class="export-modal-titre" id="export-modal-titre"></span>
    </div>

    <!-- Options CSV -->
    <div id="export-opts-csv" class="export-modal-section" style="display:none">
      <div class="export-modal-row">
        <span class="export-modal-label">Séparateur</span>
        <div class="export-toggle-group">
          <button class="export-toggle-btn active" data-val=";" id="sep-semicolon">Virgule française &nbsp;<code>;</code></button>
          <button class="export-toggle-btn" data-val="," id="sep-comma">Standard &nbsp;<code>,</code></button>
        </div>
      </div>
      <div class="export-modal-row">
        <span class="export-modal-label">Encodage</span>
        <div class="export-toggle-group">
          <button class="export-toggle-btn active" data-val="bom" id="enc-bom">UTF-8 + BOM <small>(Excel)</small></button>
          <button class="export-toggle-btn" data-val="utf8" id="enc-utf8">UTF-8 pur</button>
        </div>
      </div>
    </div>

    <!-- Options PDF -->
    <div id="export-opts-pdf" class="export-modal-section" style="display:none">
      <div class="export-modal-row" id="export-row-couleurs" style="display:none">
        <span class="export-modal-label">Code couleur</span>
        <div class="export-toggle-group">
          <button class="export-toggle-btn active" data-val="1" id="pdf-couleurs-oui">&#127912; Avec couleurs</button>
          <button class="export-toggle-btn" data-val="0" id="pdf-couleurs-non">&#9634; Sans couleurs</button>
        </div>
      </div>
      <div class="export-modal-seuils-recap" id="export-seuils-recap" style="display:none"></div>
    </div>

    <!-- Option stats (CSV + PDF) -->
    <div id="export-row-stats" class="export-modal-section export-modal-row" style="display:none">
      <span class="export-modal-label">Statistiques</span>
      <div class="export-toggle-group">
        <button class="export-toggle-btn active" data-val="1" id="stats-oui">Inclure (moy / min / max)</button>
        <button class="export-toggle-btn" data-val="0" id="stats-non">Ne pas inclure</button>
      </div>
    </div>

    <!-- Choix colonnes -->
    <div id="export-row-colonnes" class="export-modal-section" style="display:none">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
        <span class="export-modal-label">Colonnes</span>
        <button class="export-col-toggle-all" id="export-col-toggle-all" onclick="_toggleToutesColonnes(this)" type="button">Tout d&eacute;cocher</button>
      </div>
      <div class="export-colonnes-grid" id="export-colonnes-grid"></div>
    </div>

    <div class="export-modal-actions">
      <button class="export-btn-annuler" id="export-btn-annuler">Annuler</button>
      <button class="export-btn-lancer" id="export-btn-lancer">Exporter</button>
    </div>
  </div>
</div>`;
        document.body.appendChild(div.firstElementChild);

        // Fermer sur overlay click
        document.getElementById('export-modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) _fermerModal();
        });
        document.getElementById('export-btn-annuler').addEventListener('click', _fermerModal);

        // Toggle groupes
        document.querySelectorAll('.export-toggle-group').forEach(function(grp) {
            grp.addEventListener('click', function(e) {
                const btn = e.target.closest('.export-toggle-btn');
                if (!btn) return;
                grp.querySelectorAll('.export-toggle-btn').forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
            });
        });
    }

    function _fermerModal() {
        const el = document.getElementById('export-modal-overlay');
        if (el) el.classList.remove('visible');
    }

    function _valToggle(grpSelector) {
        const btn = document.querySelector(grpSelector + ' .export-toggle-btn.active');
        return btn ? btn.dataset.val : null;
    }

    function _toggleToutesColonnes(btn) {
        const cases = document.querySelectorAll('#export-colonnes-grid input[type=checkbox]');
        const toutCoche = Array.from(cases).every(cb => cb.checked);
        cases.forEach(cb => { cb.checked = !toutCoche; });
        btn.textContent = toutCoche ? 'Tout sélectionner' : 'Tout décocher';
    }

    window.ouvrirModalExport = function(config) {
        _injecterModal();

        const overlay = document.getElementById('export-modal-overlay');
        const isCSV   = config.type === 'csv';
        const isPDF   = config.type === 'pdf';

        // Titre + icone
        document.getElementById('export-modal-titre').textContent = config.titre || (isCSV ? 'Export CSV' : 'Export PDF');
        document.getElementById('export-modal-icon').textContent  = isCSV ? '\u2B07' : '\uD83D\uDDB8';

        // Sections conditionnelles
        document.getElementById('export-opts-csv').style.display        = isCSV ? '' : 'none';
        document.getElementById('export-opts-pdf').style.display        = isPDF ? '' : 'none';
        document.getElementById('export-row-stats').style.display       = config.avecStats   ? '' : 'none';
        document.getElementById('export-row-couleurs').style.display    = config.avecCouleurs ? '' : 'none';
        document.getElementById('export-row-colonnes').style.display    = (config.colonnes && config.colonnes.length) ? '' : 'none';

        // Reset toggles puis charger préférences sauvegardées
        ['#sep-semicolon','#enc-bom','#pdf-couleurs-oui','#stats-oui'].forEach(function(sel) {
            const el = document.querySelector(sel);
            if (!el) return;
            const grp = el.closest('.export-toggle-group');
            if (!grp) return;
            grp.querySelectorAll('.export-toggle-btn').forEach(function(b) { b.classList.remove('active'); });
            el.classList.add('active');
        });

        // Restaurer préférences depuis localStorage
        try {
            const prefs = JSON.parse(localStorage.getItem('mc_export_prefs') || '{}');
            if (prefs.separateur) {
                const grpSep = document.querySelector('#export-opts-csv .export-toggle-group:first-child');
                if (grpSep) {
                    const btn = grpSep.querySelector('[data-val="' + prefs.separateur + '"]');
                    if (btn) { grpSep.querySelectorAll('.export-toggle-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); }
                }
            }
            if (prefs.bom === false) {
                const grpEnc = document.querySelector('#export-opts-csv .export-toggle-group:last-child');
                if (grpEnc) {
                    const btn = grpEnc.querySelector('[data-val="utf8"]');
                    if (btn) { grpEnc.querySelectorAll('.export-toggle-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); }
                }
            }
            if (prefs.couleurs === false) {
                const grpCol = document.querySelector('#export-row-couleurs .export-toggle-group');
                if (grpCol) {
                    const btn = grpCol.querySelector('[data-val="0"]');
                    if (btn) { grpCol.querySelectorAll('.export-toggle-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); }
                }
            }
        } catch(e) {}

        // Recap seuils
        const recapEl = document.getElementById('export-seuils-recap');
        if (config.seuilsRecap) {
            recapEl.innerHTML  = config.seuilsRecap;
            recapEl.style.display = '';
        } else {
            recapEl.style.display = 'none';
        }

        // Colonnes
        const grid = document.getElementById('export-colonnes-grid');
        grid.innerHTML = '';
        const btnToggleAll = document.getElementById('export-col-toggle-all');
        if (btnToggleAll) btnToggleAll.textContent = 'Tout décocher';
        if (config.colonnes && config.colonnes.length) {
            config.colonnes.forEach(function(col) {
                const lbl = document.createElement('label');
                lbl.className = 'export-col-check';
                lbl.innerHTML = '<input type="checkbox" value="' + col.id + '"' + (col.checked !== false ? ' checked' : '') + '> ' + col.label;
                grid.appendChild(lbl);
            });
        }

        // Bouton lancer
        const btnLancer = document.getElementById('export-btn-lancer');
        btnLancer.textContent = isPDF ? 'Générer le PDF' : 'Télécharger le CSV';
        btnLancer.onclick = function() {
            _fermerModal();
            const colonnesCochees = Array.from(
                document.querySelectorAll('#export-colonnes-grid input[type=checkbox]:checked')
            ).map(function(cb) { return cb.value; });

            const opts = {
                separateur : _valToggle('#export-opts-csv .export-toggle-group:first-child') || ';',
                bom        : _valToggle('#export-opts-csv .export-toggle-group:last-child') !== 'utf8',
                stats      : _valToggle('#export-row-stats .export-toggle-group') !== '0',
                couleurs   : _valToggle('#export-opts-pdf .export-toggle-group') !== '0',
                colonnes   : colonnesCochees,
            };

            // Sauvegarder préférences globales
            try {
                localStorage.setItem('mc_export_prefs', JSON.stringify({
                    separateur: opts.separateur,
                    bom:        opts.bom,
                    couleurs:   opts.couleurs,
                }));
            } catch(e) {}

            config.onConfirm(opts);
        };

        overlay.classList.add('visible');
    };
})();

// ══════════════════════════════════════════════════════════════════════
// TOOLTIPS CONTEXTUELS — utils.js
// Usage : <span class="ft-aide" data-aide="Texte explicatif">?</span>
// Ou en JS : ajouterAide(element, "Texte explicatif")
// ══════════════════════════════════════════════════════════════════════

(function() {
    let bulleActive = null;

    function _creerBulle() {
        const b = document.createElement('div');
        b.id = 'ft-tooltip';
        b.className = 'ft-tooltip';
        b.setAttribute('role', 'tooltip');
        document.body.appendChild(b);
        return b;
    }

    function _positionner(bulle, cible) {
        const r   = cible.getBoundingClientRect();
        const bW  = bulle.offsetWidth;
        const bH  = bulle.offsetHeight;
        const gap = 8;

        let top  = r.top + window.scrollY - bH - gap;
        let left = r.left + window.scrollX + r.width / 2 - bW / 2;

        // Ne pas sortir à gauche/droite
        left = Math.max(8, Math.min(left, window.innerWidth - bW - 8));

        // Passer en dessous si pas de place en haut
        if (top < window.scrollY + 4) {
            top = r.bottom + window.scrollY + gap;
            bulle.classList.add('ft-tooltip--bas');
        } else {
            bulle.classList.remove('ft-tooltip--bas');
        }

        bulle.style.top  = top  + 'px';
        bulle.style.left = left + 'px';
    }

    function _afficher(e) {
        const texte = this.dataset.aide;
        if (!texte) return;

        const bulle = document.getElementById('ft-tooltip') || _creerBulle();
        bulle.textContent = texte;
        bulle.classList.add('visible');
        bulleActive = bulle;
        _positionner(bulle, this);
    }

    function _masquer() {
        const bulle = document.getElementById('ft-tooltip');
        if (bulle) bulle.classList.remove('visible');
        bulleActive = null;
    }

    // Attacher sur tous les .ft-aide présents + futurs (délégation)
    document.addEventListener('DOMContentLoaded', function() {
        document.body.addEventListener('mouseenter', function(e) {
            const el = e.target.closest('[data-aide]');
            if (el) _afficher.call(el, e);
        }, true);
        document.body.addEventListener('mouseleave', function(e) {
            const el = e.target.closest('[data-aide]');
            if (el) _masquer();
        }, true);
        document.body.addEventListener('focusin', function(e) {
            const el = e.target.closest('[data-aide]');
            if (el) _afficher.call(el, e);
        }, true);
        document.body.addEventListener('focusout', function(e) {
            const el = e.target.closest('[data-aide]');
            if (el) _masquer();
        }, true);

        // Repositionner si scroll
        window.addEventListener('scroll', function() {
            if (bulleActive) _masquer();
        }, { passive: true });
    });

    /**
     * Ajoute un badge ? avec tooltip à côté d'un élément DOM existant.
     * @param {HTMLElement|string} cible  Élément ou sélecteur CSS
     * @param {string}             texte  Texte de l'aide
     */
    window.ajouterAide = function(cible, texte) {
        const el = typeof cible === 'string' ? document.querySelector(cible) : cible;
        if (!el) return;
        const badge = document.createElement('span');
        badge.className      = 'ft-aide';
        badge.textContent    = '?';
        badge.dataset.aide   = texte;
        badge.setAttribute('tabindex', '0');
        badge.setAttribute('aria-label', 'Aide : ' + texte);
        el.appendChild(badge);
    };


/**
 * Protection contre les rechargements accidentels (F5, Ctrl+R, fermeture onglet)
 * pendant une action en cours (sauvegarde, import, soumission de formulaire).
 *
 * Usage :
 *   const garde = activerGardePage();
 *   // ... action async ...
 *   garde.desactiver();
 *
 * Ou avec un état booléen externe :
 *   activerGardePage(() => monEtat.enCours);
 *
 * @param {Function|null} [predicat]  Fonction retournant true si une action est en cours.
 *                                    Si null, la garde est active jusqu'à desactiver().
 * @returns {{ desactiver: Function }}
 */
window.activerGardePage = function(predicat) {
    let actif = true;

    function handler(e) {
        const enCours = typeof predicat === 'function' ? predicat() : actif;
        if (enCours) {
            e.preventDefault();
            e.returnValue = '';
        }
    }

    window.addEventListener('beforeunload', handler);

    return {
        desactiver() {
            actif = false;
            window.removeEventListener('beforeunload', handler);
        }
    };
};

})();