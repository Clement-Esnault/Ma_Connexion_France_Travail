/**
 * test.speedtest.js — Tests QUnit des fonctions pures de speedtest.js
 * v1.10 — mode unique 'precise' : suppression des tests fast
 *
 * Fonctions testées (aucun réseau, aucun DOM) :
 *   - mediane()
 *   - moyenneTronquee()
 *   - dureePrechauffage()
 *   - estConfigValide()
 *   - migrerConfig()
 */

QUnit.module('speedtest.js — mediane()', () => {

    QUnit.test('tableau d\'un élément', assert => {
        assert.strictEqual(mediane([42]), 42);
    });

    QUnit.test('tableau impair — retourne la valeur centrale', assert => {
        assert.strictEqual(mediane([3, 1, 2]), 2);
    });

    QUnit.test('tableau pair — retourne la moyenne des deux centraux', assert => {
        assert.strictEqual(mediane([1, 2, 3, 4]), 2.5);
    });

    QUnit.test('valeurs non triées en entrée', assert => {
        assert.strictEqual(mediane([10, 1, 5, 3, 8]), 5);
    });

    QUnit.test('valeurs identiques', assert => {
        assert.strictEqual(mediane([7, 7, 7]), 7);
    });

    QUnit.test('ne modifie pas le tableau original', assert => {
        const arr = [3, 1, 2];
        mediane(arr);
        assert.deepEqual(arr, [3, 1, 2]);
    });
});

QUnit.module('speedtest.js — moyenneTronquee()', () => {

    QUnit.test('tableau de 5 valeurs — coupe 15% de chaque côté', assert => {
        // 15% de 5 = 0.75 → floor = 0 → aucun élément coupé → moyenne normale
        const result = moyenneTronquee([1, 2, 3, 4, 5]);
        assert.strictEqual(result, 3);
    });

    QUnit.test('élimine les pics extrêmes avec proportion 0.2', assert => {
        // [1, 5, 5, 5, 100] — 20% de 5 = 1 → coupe min(1) et max(100) → moy(5,5,5) = 5
        const result = moyenneTronquee([100, 5, 5, 5, 1], 0.2);
        assert.strictEqual(result, 5);
    });

    QUnit.test('tableau d\'un seul élément', assert => {
        assert.strictEqual(moyenneTronquee([42]), 42);
    });

    QUnit.test('valeurs identiques', assert => {
        assert.strictEqual(moyenneTronquee([10, 10, 10, 10]), 10);
    });

    QUnit.test('proportion 0 — aucun élément coupé', assert => {
        const result = moyenneTronquee([1, 2, 3], 0);
        assert.strictEqual(result, 2);
    });
});

QUnit.module('speedtest.js — dureePrechauffage()', () => {

    QUnit.test('retourne le plancher minimum pour une durée courte', assert => {
        // 6000 ms × 0.25 = 1500 ms < 2500 ms → plancher 2500
        assert.strictEqual(dureePrechauffage(6000), 2500);
    });

    QUnit.test('retourne 25% pour une durée suffisamment longue', assert => {
        // 20000 ms × 0.25 = 5000 ms > 2500 ms → 5000
        assert.strictEqual(dureePrechauffage(20000), 5000);
    });

    QUnit.test('arrondi à l\'entier le plus proche', assert => {
        // 12000 ms × 0.25 = 3000 ms > 2500 ms → 3000
        assert.strictEqual(dureePrechauffage(12000), 3000);
    });

    QUnit.test('durée 0 — retourne le plancher', assert => {
        assert.strictEqual(dureePrechauffage(0), 2500);
    });

    QUnit.test('proportionnalité — double durée = double préchauffage si au-dessus du plancher', assert => {
        const p1 = dureePrechauffage(20000);
        const p2 = dureePrechauffage(40000);
        assert.strictEqual(p2, p1 * 2);
    });
});

QUnit.module('speedtest.js — estConfigValide()', () => {

    QUnit.test('config valide complète', assert => {
        const config = {
            precise: {
                ping:     { echantillons: 10 },
                download: { dureeMsDownload: 6000 },
                upload:   { dureeMsUpload: 6000, tailleMoBlob: 20 },
            },
        };
        assert.true(estConfigValide(config));
    });

    QUnit.test('null → invalide', assert => {
        assert.false(estConfigValide(null));
    });

    QUnit.test('objet vide → invalide', assert => {
        assert.false(estConfigValide({}));
    });

    QUnit.test('echantillons = 0 → invalide', assert => {
        const config = {
            precise: { ping: { echantillons: 0 }, download: { dureeMsDownload: 1 }, upload: { dureeMsUpload: 1 } },
        };
        assert.false(estConfigValide(config));
    });

    QUnit.test('mode precise manquant → invalide', assert => {
        assert.false(estConfigValide({}));
    });
});

QUnit.module('speedtest.js — migrerConfig()', () => {

    QUnit.test('config v1.10 passthrough — aucune modification', assert => {
        const config = {
            precise: {
                ping:     { prechauffage: 3,  echantillons: 10, delay: 30 },
                download: { dureeMsDownload: 6000, parallel: 1 },
                upload:   { dureeMsUpload: 6000,   parallel: 1, tailleMoBlob: 20 },
            },
        };
        const result = migrerConfig(config);
        assert.strictEqual(result.precise.download.dureeMsDownload, 6000);
        assert.strictEqual(result.precise.upload.tailleMoBlob, 20);
    });

    QUnit.test('parallel toujours forcé à 1', assert => {
        const config = {
            precise: {
                ping:     { prechauffage: 1, echantillons: 5, delay: 30 },
                download: { dureeMsDownload: 6000, parallel: 4 },
                upload:   { dureeMsUpload: 6000, parallel: 4, tailleMoBlob: 20 },
            },
        };
        const result = migrerConfig(config);
        assert.strictEqual(result.precise.download.parallel, 1, 'precise download parallel forcé à 1');
        assert.strictEqual(result.precise.upload.parallel,   1, 'precise upload parallel forcé à 1');
    });

    QUnit.test('migration ancienne config sans dureeMsDownload — injecte la valeur par défaut', assert => {
        const config = {
            precise: {
                ping:     { prechauffage: 3, echantillons: 10, delay: 30 },
                download: { parallel: 1 },   // pas de dureeMsDownload
                upload:   { parallel: 1 },   // pas de dureeMsUpload ni tailleMoBlob
            },
        };
        const result = migrerConfig(config);
        assert.strictEqual(result.precise.download.dureeMsDownload, 6000, 'valeur par défaut download injectée');
        assert.strictEqual(result.precise.upload.dureeMsUpload,     6000, 'valeur par défaut upload injectée');
        assert.strictEqual(result.precise.upload.tailleMoBlob,        20, 'tailleMoBlob par défaut injectée');
    });
});