// profil.js — changement de mot de passe du compte connecté.
// Envoie le formulaire en POST via fetch et affiche le retour sans rechargement de page.

document.getElementById('profil-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const notice   = document.getElementById('profil-notice');
    const donneesFormulaire = new FormData(this);

    notice.style.display = 'none';
    notice.className     = '';
    const garde = activerGardePage();

    try {
        const res  = await fetch('../backend/admin/profil.php', {
            method: 'POST',
            body:   donneesFormulaire
        });
        const data = await res.json();

        notice.style.display = 'block';
        if (data.success) {
            notice.classList.add('notice-ok');
            notice.textContent = '✓ ' + data.message;
            this.reset(); // Vide les champs après succès
        } else {
            notice.classList.add('notice-err');
            notice.textContent = '⚠ ' + (data.error ?? 'Erreur inconnue.');
        }
    } catch {
        notice.style.display = 'block';
        notice.classList.add('notice-err');
        notice.textContent = '⚠ Impossible de contacter le serveur.';
    } finally {
        garde.desactiver();
    }
});