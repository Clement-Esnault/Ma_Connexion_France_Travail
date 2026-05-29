/**
 * test.header.js — Tests QUnit des fonctions de header.js
 *
 * Fonctions testées :
 *   - choisirMode() — application des classes CSS daltonien sur <html>
 *   - fermerMenuDaltonien()
 *   - persistance localStorage
 */

QUnit.module('header.js — choisirMode()', hooks => {

    hooks.beforeEach(() => {
        // Nettoyer les classes et le localStorage avant chaque test
        document.documentElement.classList.remove(
            'daltonien',
            'daltonien-deuteranopie',
            'daltonien-protanopie',
            'daltonien-tritanopie',
            'daltonien-achromatopsie'
        );
        localStorage.removeItem('fd_daltonien_mode');

        // Créer les éléments DOM nécessaires à choisirMode()
        ['daltonien-label', 'daltonien-menu', 'btn-daltonien'].forEach(id => {
            if (!document.getElementById(id)) {
                const el = document.createElement(id === 'daltonien-label' ? 'span' : 'div');
                el.id = id;
                if (id === 'btn-daltonien') el.setAttribute('aria-expanded', 'false');
                document.body.appendChild(el);
            }
        });
    });

    hooks.afterEach(() => {
        ['daltonien-label', 'daltonien-menu', 'btn-daltonien'].forEach(id => {
            document.getElementById(id)?.remove();
        });
    });

    QUnit.test('mode vide — supprime toutes les classes daltonien', assert => {
        document.documentElement.classList.add('daltonien', 'daltonien-deuteranopie');
        choisirMode('');
        assert.false(document.documentElement.classList.contains('daltonien'),            'classe daltonien absente');
        assert.false(document.documentElement.classList.contains('daltonien-deuteranopie'), 'classe deuteranopie absente');
    });

    QUnit.test('deuteranopie — ajoute les bonnes classes', assert => {
        choisirMode('deuteranopie');
        assert.true(document.documentElement.classList.contains('daltonien'),              'classe daltonien présente');
        assert.true(document.documentElement.classList.contains('daltonien-deuteranopie'), 'classe deuteranopie présente');
    });

    QUnit.test('protanopie — ajoute les bonnes classes', assert => {
        choisirMode('protanopie');
        assert.true(document.documentElement.classList.contains('daltonien-protanopie'));
    });

    QUnit.test('tritanopie — ajoute les bonnes classes', assert => {
        choisirMode('tritanopie');
        assert.true(document.documentElement.classList.contains('daltonien-tritanopie'));
    });

    QUnit.test('achromatopsie — ajoute les bonnes classes', assert => {
        choisirMode('achromatopsie');
        assert.true(document.documentElement.classList.contains('daltonien-achromatopsie'));
    });

    QUnit.test('changement de mode — supprime l\'ancien mode', assert => {
        choisirMode('deuteranopie');
        choisirMode('protanopie');
        assert.false(document.documentElement.classList.contains('daltonien-deuteranopie'), 'deuteranopie supprimée');
        assert.true(document.documentElement.classList.contains('daltonien-protanopie'),    'protanopie ajoutée');
    });

    QUnit.test('mode invalide — aucune classe ajoutée', assert => {
        choisirMode('inexistant');
        assert.false(document.documentElement.classList.contains('daltonien'), 'Pas de classe daltonien pour un mode invalide');
    });

    QUnit.test('persistance localStorage — mode sauvegardé', assert => {
        choisirMode('deuteranopie');
        assert.strictEqual(localStorage.getItem('fd_daltonien_mode'), 'deuteranopie');
    });

    QUnit.test('persistance localStorage — mode vide sauvegardé', assert => {
        choisirMode('');
        assert.strictEqual(localStorage.getItem('fd_daltonien_mode'), '');
    });

    QUnit.test('label mis à jour — deuteranopie', assert => {
        choisirMode('deuteranopie');
        const label = document.getElementById('daltonien-label');
        assert.true(label.textContent.toLowerCase().includes('déut') || label.textContent.toLowerCase().includes('deut'),
            `Label = "${label.textContent}" — devrait contenir "deuteranopie"`);
    });

    QUnit.test('label mis à jour — mode normal', assert => {
        choisirMode('');
        const label = document.getElementById('daltonien-label');
        assert.strictEqual(label.textContent, 'Normal');
    });
});

QUnit.module('header.js — fermerMenuDaltonien()', hooks => {

    hooks.beforeEach(() => {
        ['daltonien-menu', 'btn-daltonien'].forEach(id => {
            if (!document.getElementById(id)) {
                const el = document.createElement('div');
                el.id = id;
                if (id === 'btn-daltonien') el.setAttribute('aria-expanded', 'true');
                else el.className = '';
                document.body.appendChild(el);
            }
        });
    });

    hooks.afterEach(() => {
        ['daltonien-menu', 'btn-daltonien'].forEach(id => document.getElementById(id)?.remove());
    });

    QUnit.test('ajoute la classe hidden sur le menu', assert => {
        const menu = document.getElementById('daltonien-menu');
        menu.classList.remove('hidden');
        fermerMenuDaltonien();
        assert.true(menu.classList.contains('hidden'));
    });

    QUnit.test('met aria-expanded à false sur le bouton', assert => {
        fermerMenuDaltonien();
        const btn = document.getElementById('btn-daltonien');
        assert.strictEqual(btn.getAttribute('aria-expanded'), 'false');
    });
});
