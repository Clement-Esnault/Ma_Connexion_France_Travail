/**
 * header.js — Logique commune du header Ma Connexion
 *
 * Responsabilités :
 *   - Badge alertes dans la navigation (compteur sites insuffisants)
 *   - Copier l'IP du poste dans le presse-papiers
 *   - Raccourcis clavier globaux (Echap, R, ?)
 *   - Mode daltonien (persisté en localStorage)
 *
 * Chargé depuis header.php via <script src>.
 * Aucune variable PHP injectée — tout est lu depuis le DOM ou localStorage.
 */

// ── Badge alertes ─────────────────────────────────────────────────────
(function () {
    fetch('/backend/ip/get_alertes.php?periode=30j', { credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data?.total) return;
            const badge = document.getElementById('badge-nav-alertes');
            if (!badge) return;
            badge.textContent = data.total > 99 ? '99+' : data.total;
            badge.classList.remove('hidden');
        })
        .catch(() => {});
})();

// ── Copier IP ──────────────────────────────────────────────────────────
function copierIP() {
    fetch('/backend/ip/getIP.php')
        .then(r => r.json())
        .then(d => {
            if (!d.ip) return;
            navigator.clipboard.writeText(d.ip).then(() => {
                const btn = document.getElementById('btn-copier-ip');
                if (btn) { btn.textContent = '✓'; setTimeout(() => btn.textContent = '📋', 1500); }
            });
        })
        .catch(() => {});
}

// ── Raccourcis clavier ────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
    switch (e.key) {
        case 'Escape':
            document.getElementById('tooltip-overlay')?.classList.add('hidden');
            document.getElementById('daltonien-menu')?.classList.add('hidden');
            break;
        case 'r': case 'R':
            if (typeof reinitialiserFiltre === 'function')         reinitialiserFiltre();
            else if (typeof reinitialiserRecherche === 'function') reinitialiserRecherche();
            break;
        case '?':
            document.querySelector('.encart-aide-toggle')?.click();
            break;
    }
});

// ── Mode daltonien ────────────────────────────────────────────────────
const MODES_DALTONIEN = {
    '':              'Normal',
    'deuteranopie':  'Deutéranopie',
    'protanopie':    'Protanopie',
    'tritanopie':    'Tritanopie',
    'achromatopsie': 'Achromatopsie',
};

const CLASSES_DALTONIEN = Object.keys(MODES_DALTONIEN).filter(m => m !== '');

// Appliquer le mode sauvegardé immédiatement (avant DOMContentLoaded)
(function () {
    const mode = localStorage.getItem('fd_daltonien_mode') || '';
    if (mode && CLASSES_DALTONIEN.includes(mode)) {
        document.documentElement.classList.add('daltonien', 'daltonien-' + mode);
    }
})();

/**
 * Ouvre/ferme le menu de sélection de palette daltonienne.
 * Ajoute un listener 'click' one-shot sur document pour fermer au clic extérieur.
 * Le setTimeout(0) évite que le click qui ouvre le menu ne le referme immédiatement.
 */
function toggleMenuDaltonien() {
    const menu  = document.getElementById('daltonien-menu');
    const ouvert = !menu.classList.contains('hidden');
    menu.classList.toggle('hidden', ouvert);
    document.getElementById('btn-daltonien').setAttribute('aria-expanded', String(!ouvert));
    if (!ouvert) {
        setTimeout(() => document.addEventListener('click', fermerMenuDaltonien, { once: true }), 0);
    }
}

function fermerMenuDaltonien() {
    document.getElementById('daltonien-menu')?.classList.add('hidden');
    document.getElementById('btn-daltonien')?.setAttribute('aria-expanded', 'false');
}

/**
 * Applique un mode daltonien sur <html> et le persiste en localStorage.
 * Retire toutes les classes daltonien-* avant d'appliquer la nouvelle
 * pour éviter les conflits entre palettes.
 *
 * @param {string} mode  '' | 'deuteranopie' | 'protanopie' | 'tritanopie' | 'achromatopsie'
 */
function choisirMode(mode) {
    document.documentElement.classList.remove('daltonien', ...CLASSES_DALTONIEN.map(m => 'daltonien-' + m));
    if (mode && CLASSES_DALTONIEN.includes(mode)) {
        document.documentElement.classList.add('daltonien', 'daltonien-' + mode);
    }
    localStorage.setItem('fd_daltonien_mode', mode);
    document.getElementById('daltonien-label').textContent = MODES_DALTONIEN[mode] || 'Normal';
    fermerMenuDaltonien();
    document.querySelectorAll('.daltonien-option').forEach(btn => {
        const actif = btn.getAttribute('onclick').includes("'" + mode + "'");
        btn.classList.toggle('daltonien-option--active', actif);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const mode = localStorage.getItem('fd_daltonien_mode') || '';
    document.getElementById('daltonien-label').textContent = MODES_DALTONIEN[mode] || 'Normal';
    document.querySelectorAll('.daltonien-option').forEach(btn => {
        const actif = btn.getAttribute('onclick').includes("'" + mode + "'");
        btn.classList.toggle('daltonien-option--active', actif);
    });
});