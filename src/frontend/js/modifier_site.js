// modifier_site.js — gestion du formulaire de modification d'un site France Travail.


// ── Chargement du site + des départements ─────────────────────────────
// Les deux fetches sont lancés en parallèle, le formulaire s'affiche quand les deux sont prêts
async function chargerSite() {
    if (!CODE_GX_SITE) {
        document.getElementById('loading').innerHTML = '⚠️ CODE_GX_SITE manquant dans l\'URL.';
        return;
    }

    try {
        // Chargement parallèle : données du site + liste des départements
        const [resSite, resDepts] = await Promise.all([
            fetch(`/backend/admin/get_site.php?CODE_GX_SITE=${encodeURIComponent(CODE_GX_SITE)}`),
            fetch('/backend/admin/get_departement.php'),
        ]);

        if (!resSite.ok)  throw new Error('HTTP ' + resSite.status);
        if (!resDepts.ok) throw new Error('HTTP ' + resDepts.status);

        const site  = await resSite.json();
        const depts = await resDepts.json();

        if (site.error) throw new Error(site.error);

        // ── Peupler le select département ─────────────────────────────
        const select = document.getElementById('f-dept');
        select.innerHTML = '<option value="">— Choisir un département —</option>';
        depts.forEach(d => {
            const opt = document.createElement('option');
            opt.value       = d.ID_DEPARTEMENT;
            opt.textContent = `${d.NUM_DEPARTEMENT} — ${d.NOM_DEPARTEMENT} (${d.NOM_REGION})`;
            if (d.ID_DEPARTEMENT == site.ID_DEPARTEMENT) opt.selected = true;
            select.appendChild(opt);
        });

        // ── Remplir les champs ────────────────────────────────────────
        document.getElementById('titre-page').textContent = 'Modifier — ' + site.CODE_GX_SITE;
        document.getElementById('sous-titre').textContent = site.NOM_SITE;
        document.getElementById('f-code').value    = site.CODE_GX_SITE    ?? '';
        document.getElementById('f-nom').value     = site.NOM_SITE        ?? '';
        document.getElementById('f-region').value  = site.NOM_REGION      ?? '';
        document.getElementById('f-adresse').value = site.ADRESSE         ?? '';
        document.getElementById('f-ville').value   = site.VILLE           ?? '';
        document.getElementById('f-cp').value      = site.CODE_POSTAL     ?? '';
        document.getElementById('f-ip').value      = site.IP_RESEAU       ?? '';
        document.getElementById('f-masque').value  = site.MASQUE_SITE     ?? '';
        document.getElementById('f-lat').value     = site.LATITUDE        ?? '';
        document.getElementById('f-lng').value     = site.LONGITUDE       ?? '';

        afficherBadgeStatut(parseInt(site.IP_SPECIALE));

        // ── Seuils dérogatoires — pré-remplissage ────────────────────
        const d   = site.derogation_seuils;
        const val = (v) => (v !== null && v !== undefined) ? v : '';
        document.getElementById('f-dl-bon').value       = val(d?.DL_VALEUR_BONNE);
        document.getElementById('f-dl-mauvais').value   = val(d?.DL_VALEUR_MAUVAISE);
        document.getElementById('f-ul-bon').value       = val(d?.UL_VALEUR_BONNE);
        document.getElementById('f-ul-mauvais').value   = val(d?.UL_VALEUR_MAUVAISE);
        document.getElementById('f-ping-bon').value     = val(d?.PING_VALEUR_BONNE);
        document.getElementById('f-ping-mauvais').value = val(d?.PING_VALEUR_MAUVAISE);
        document.getElementById('f-derog-raison').value = val(d?.RAISON);
        if (d) document.getElementById('badge-derogation').style.display = 'inline-block';

        document.getElementById('loading').style.display   = 'none';
        document.getElementById('form-site').style.display = 'block';

    } catch (err) {
        document.getElementById('loading').innerHTML = `⚠️ ${err.message}`;
    }
}

// Affiche le badge de statut : "IP Spéciale" (pas de CIDR) ou "Normal" (IP rattachée)
function afficherBadgeStatut(ip_speciale) {
    const badge = document.getElementById('badge-statut');
    if (ip_speciale === 1) {
        badge.innerHTML = '<span class="badge-speciale">⚠ IP Spéciale</span>';
    } else {
        badge.innerHTML = '<span class="badge-normal">✓ Normal</span>';
    }
}

// ── Soumission du formulaire ──────────────────────────────────────────
document.getElementById('form-site').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn       = document.getElementById('btn-save');
    const msgOk     = document.getElementById('msg-ok');
    const msgErreur = document.getElementById('msg-error');

    btn.disabled        = true;
    btn.innerHTML       = '<span class="spinner-inline"></span>Enregistrement…';
    msgOk.style.display = msgErreur.style.display = 'none';
    const garde = activerGardePage();

    try {
        const res = await fetch('/backend/admin/modifier_site.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                CODE_GX_SITE:   CODE_GX_SITE,
                NOM_SITE:       document.getElementById('f-nom').value.trim(),
                ID_DEPARTEMENT: document.getElementById('f-dept').value,
                IP_RESEAU:      document.getElementById('f-ip').value.trim(),
                MASQUE_SITE:    document.getElementById('f-masque').value,
                ADRESSE:        document.getElementById('f-adresse').value.trim(),
                CODE_POSTAL:    document.getElementById('f-cp').value.trim(),
                VILLE:          document.getElementById('f-ville').value.trim(),
                LATITUDE:       document.getElementById('f-lat').value,
                LONGITUDE:      document.getElementById('f-lng').value,
                // Seuils dérogatoires — chaîne vide = null côté PHP = seuil global
                DL_VALEUR_BONNE:      document.getElementById('f-dl-bon').value,
                DL_VALEUR_MAUVAISE:   document.getElementById('f-dl-mauvais').value,
                UL_VALEUR_BONNE:      document.getElementById('f-ul-bon').value,
                UL_VALEUR_MAUVAISE:   document.getElementById('f-ul-mauvais').value,
                PING_VALEUR_BONNE:    document.getElementById('f-ping-bon').value,
                PING_VALEUR_MAUVAISE: document.getElementById('f-ping-mauvais').value,
                DEROGATION_RAISON:    document.getElementById('f-derog-raison').value.trim(),
            }),
        });

        const data = await res.json();

        if (data.success) {
            msgOk.textContent   = '✓ ' + data.message;
            msgOk.style.display = 'block';
            document.getElementById('sous-titre').textContent = document.getElementById('f-nom').value.trim();
            afficherBadgeStatut(data.ip_speciale ?? (document.getElementById('f-ip').value.trim() ? 0 : 1));
            // Mettre à jour la région affichée selon le département sélectionné
            const opt = document.getElementById('f-dept').selectedOptions[0];
            if (opt) {
                const match = opt.textContent.match(/\((.+)\)$/);
                if (match) document.getElementById('f-region').value = match[1];
            }
            // Afficher un bouton retour vers la recherche après succès
            let btnRetour = document.getElementById('btn-retour-succes');
            if (!btnRetour) {
                btnRetour          = document.createElement('a');
                btnRetour.id       = 'btn-retour-succes';
                btnRetour.className = 'btn-save';
                btnRetour.style.cssText = 'text-decoration:none;display:inline-block;margin-left:.75rem;background:var(--ft-muted)';
                const q = new URLSearchParams(window.location.search).get('q');
                btnRetour.href     = '../recherche.php' + (q ? '?q=' + encodeURIComponent(q) : '');
                btnRetour.textContent = '← Retour à la recherche';
                msgOk.insertAdjacentElement('afterend', btnRetour);
            }
            btnRetour.style.display = 'inline-block';
        } else {
            msgErreur.textContent   = '⚠ ' + (data.error ?? 'Erreur inconnue');
            msgErreur.style.display = 'block';
        }
    } catch (err) {
        msgErreur.textContent   = '⚠ Impossible de contacter le serveur.';
        msgErreur.style.display = 'block';
        console.error('[modifier_site.js]', err);
    } finally {
        btn.disabled  = false;
        btn.innerHTML = 'Enregistrer';
        garde.desactiver();
    }
});

// Chargement au démarrage

// ── Bouton reset dérogation ───────────────────────────────────────────
document.getElementById('btn-reset-derog')?.addEventListener('click', () => {
    ['f-dl-bon', 'f-dl-mauvais', 'f-ul-bon', 'f-ul-mauvais',
     'f-ping-bon', 'f-ping-mauvais', 'f-derog-raison'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('badge-derogation').style.display = 'none';
});

chargerSite();