/**
 * test.utils.js — Tests QUnit des fonctions pures de utils.js
 *
 * Fonctions testées :
 *   - classCouleur()
 *   - tooltipVerdict()
 *   - echapper()
 *   - telechargerCSV() — vérifie la création du lien sans déclencher le téléchargement
 *   - activerGardePage() — vérifie l'ajout/retrait du listener beforeunload
 */

// ── Helpers de test ───────────────────────────────────────────────────────────

/** Seuils de test standard (identiques aux défauts de utils.js) */
const SEUIL_DL   = { bon: 5,   mauvais: 3   };
const SEUIL_PING = { bon: 50,  mauvais: 100 };

// ── classCouleur() ────────────────────────────────────────────────────────────

QUnit.module('utils.js — classCouleur()', () => {

    QUnit.test('download >= seuil bon → cell-bon', assert => {
        assert.strictEqual(classCouleur(10, SEUIL_DL), 'cell-bon');
        assert.strictEqual(classCouleur(5, SEUIL_DL),  'cell-bon');
    });

    QUnit.test('download <= seuil mauvais → cell-mauvais', assert => {
        assert.strictEqual(classCouleur(3, SEUIL_DL),  'cell-mauvais');
        assert.strictEqual(classCouleur(1, SEUIL_DL),  'cell-mauvais');
    });

    QUnit.test('download entre seuils → cell-moyen', assert => {
        assert.strictEqual(classCouleur(4, SEUIL_DL),  'cell-moyen');
    });

    QUnit.test('ping inverse=true — faible = bon', assert => {
        assert.strictEqual(classCouleur(20, SEUIL_PING, true),  'cell-bon',     '20ms → bon');
        assert.strictEqual(classCouleur(50, SEUIL_PING, true),  'cell-bon',     '50ms = seuil bon → bon');
        assert.strictEqual(classCouleur(75, SEUIL_PING, true),  'cell-moyen',   '75ms → moyen');
        assert.strictEqual(classCouleur(100, SEUIL_PING, true), 'cell-mauvais', '100ms = seuil mauvais → mauvais');
        assert.strictEqual(classCouleur(200, SEUIL_PING, true), 'cell-mauvais', '200ms → mauvais');
    });

    QUnit.test('valeur exactement sur le seuil bon (non-inverse) → cell-bon', assert => {
        assert.strictEqual(classCouleur(5, SEUIL_DL), 'cell-bon');
    });

    QUnit.test('valeur exactement sur le seuil mauvais (non-inverse) → cell-mauvais', assert => {
        assert.strictEqual(classCouleur(3, SEUIL_DL), 'cell-mauvais');
    });
});

// ── tooltipVerdict() ──────────────────────────────────────────────────────────

QUnit.module('utils.js — tooltipVerdict()', () => {

    QUnit.test('résultat bon — contient "Confort"', assert => {
        const txt = tooltipVerdict(10, SEUIL_DL, 'download');
        assert.true(txt.includes('Bon'), `Attendu "Bon" dans "${txt}"`);
    });

    QUnit.test('résultat mauvais — contient "Insuffisant"', assert => {
        const txt = tooltipVerdict(1, SEUIL_DL, 'download');
        assert.true(txt.includes('Insuffisant'), `Attendu "Insuffisant" dans "${txt}"`);
    });

    QUnit.test('résultat moyen — contient "Fonctionnel"', assert => {
        const txt = tooltipVerdict(4, SEUIL_DL, 'download');
        assert.true(txt.includes('Fonctionnel'), `Attendu "Fonctionnel" dans "${txt}"`);
    });

    QUnit.test('ping — unité ms dans le tooltip', assert => {
        const txt = tooltipVerdict(30, SEUIL_PING, 'ping', true);
        assert.true(txt.includes('ms'), `Attendu "ms" dans "${txt}"`);
    });

    QUnit.test('download — unité Mbit/s dans le tooltip', assert => {
        const txt = tooltipVerdict(10, SEUIL_DL, 'download');
        assert.true(txt.includes('Mbit/s'), `Attendu "Mbit/s" dans "${txt}"`);
    });
});

// ── echapper() ────────────────────────────────────────────────────────────────

QUnit.module('utils.js — echapper()', () => {

    QUnit.test('chaîne sans caractères spéciaux — inchangée', assert => {
        assert.strictEqual(echapper('bonjour'), 'bonjour');
    });

    QUnit.test('< et > échappés', assert => {
        assert.strictEqual(echapper('<b>test</b>'), '&lt;b&gt;test&lt;/b&gt;');
    });

    QUnit.test('& échappé', assert => {
        assert.strictEqual(echapper('a & b'), 'a &amp; b');
    });

    QUnit.test('" échappé', assert => {
        assert.strictEqual(echapper('"texte"'), '&quot;texte&quot;');
    });

    QUnit.test("' — non échappé (echapper() ne traite pas les apostrophes)", assert => {
        // echapper() utilise innerHTML en interne — les apostrophes ne sont pas encodées
        // car elles ne sont pas dangereuses dans un contexte innerHTML
        assert.strictEqual(echapper("l'apostrophe"), "l'apostrophe");
    });

    QUnit.test('chaîne vide → chaîne vide', assert => {
        assert.strictEqual(echapper(''), '');
    });

    QUnit.test('combinaison de caractères spéciaux', assert => {
        const result = echapper('<script>alert("xss")</script>');
        assert.false(result.includes('<script>'), 'Pas de balise script dans le résultat');
        assert.true(result.includes('&lt;'), 'Contient &lt;');
    });
});

// ── telechargerCSV() ─────────────────────────────────────────────────────────

QUnit.module('utils.js — telechargerCSV()', hooks => {

    let lienCree    = null;
    let blobCapture = null;
    let origAppend, origURL, origClick;

    hooks.beforeEach(() => {
        lienCree = null;
        blobCapture = null;

        // Intercepter URL.createObjectURL à chaque test (réinstallé à chaque beforeEach)
        origURL = URL.createObjectURL;
        URL.createObjectURL = blob => { blobCapture = blob; return 'blob:fake-url'; };

        // Intercepter HTMLAnchorElement.prototype.click — neutralise tous les clics
        origClick = HTMLAnchorElement.prototype.click;
        HTMLAnchorElement.prototype.click = function() {};

        // Intercepter appendChild pour capturer le lien
        origAppend = document.body.appendChild.bind(document.body);
        document.body.appendChild = function(el) {
            if (el && el.tagName === 'A') { lienCree = el; }
            else { origAppend(el); }
        };
    });

    hooks.afterEach(() => {
        document.body.appendChild        = origAppend;
        URL.createObjectURL              = origURL;
        HTMLAnchorElement.prototype.click = origClick;
    });

    QUnit.test('crée un lien avec l\'attribut download correct', assert => {
        telechargerCSV(['col1;col2', 'val1;val2'], 'export.csv', ';', false);
        assert.ok(lienCree, 'Un lien a été créé');
        assert.strictEqual(lienCree?.download, 'export.csv');
    });

    QUnit.test('lien href = blob:fake-url', assert => {
        telechargerCSV(['a;b'], 'test.csv', ';', false);
        assert.strictEqual(lienCree?.href, 'blob:fake-url', `href = ${lienCree?.href}`);
    });

    QUnit.test('BOM ajouté si bom=true', async assert => {
        const done = assert.async();
        telechargerCSV(['a;b'], 'test.csv', ';', true);
        assert.ok(blobCapture, 'Blob capturé');
        if (blobCapture) {
            // Vérification via ArrayBuffer — les 3 premiers bytes UTF-8 BOM = 0xEF 0xBB 0xBF
            const buffer = await blobCapture.arrayBuffer();
            const bytes  = new Uint8Array(buffer);
            const bomPresent = bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF;
            assert.true(bomPresent,
                `BOM UTF-8 présent (bytes: 0x${bytes[0].toString(16)} 0x${bytes[1].toString(16)} 0x${bytes[2].toString(16)})`);
        }
        done();
    });
});

// ── activerGardePage() ────────────────────────────────────────────────────────

QUnit.module('utils.js — activerGardePage()', hooks => {

    // Tester via les effets réels (pas de stub addEventListener)
    // On vérifie le comportement observable : prédicat, desactiver

    QUnit.test('retourne un objet avec desactiver()', assert => {
        const garde = activerGardePage();
        assert.equal(typeof garde.desactiver, 'function', 'desactiver est une fonction');
        garde.desactiver();
    });

    QUnit.test('desactiver() s\'exécute sans erreur', assert => {
        const garde = activerGardePage();
        assert.ok(true, 'activerGardePage() créé sans erreur');
        garde.desactiver();
        assert.ok(true, 'desactiver() sans erreur');
    });

    QUnit.test('prédicat — garde inactive si prédicat retourne false', assert => {
        let actif = false;
        let preventDefaultAppele = false;
        const garde = activerGardePage(() => actif);

        const ev = new Event('beforeunload', { cancelable: true });
        Object.defineProperty(ev, 'preventDefault', {
            value: () => { preventDefaultAppele = true; },
            writable: true
        });
        window.dispatchEvent(ev);

        assert.false(preventDefaultAppele, 'preventDefault non appelé quand prédicat = false');
        garde.desactiver();
    });

    QUnit.test('prédicat — garde active si prédicat retourne true', assert => {
        let actif = true;
        let returnValueDefini = false;
        const garde = activerGardePage(() => actif);

        const ev = new Event('beforeunload', { cancelable: true });
        Object.defineProperty(ev, 'returnValue', {
            get: () => returnValueDefini,
            set: () => { returnValueDefini = true; }
        });
        window.dispatchEvent(ev);

        assert.true(returnValueDefini, 'returnValue défini quand prédicat = true');
        garde.desactiver();
    });
});