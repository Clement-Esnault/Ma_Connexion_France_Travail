<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/services/StatService.php';

/**
 * Tests d'intégration pour StatService.
 *
 * Utilise la base ft_speedtest réelle (données existantes).
 * Aucune modification de données — tous les tests sont en lecture seule.
 *
 * Prérequis : au moins un site, un log et un seuil en base.
 *
 * Lancement : C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c tests/phpunit.xml
 */
class StatTest extends TestCase
{
    private static PDO $pdo;
    private StatService $s;

    // ── Connexion unique pour toute la suite ──────────────────────────
    public static function setUpBeforeClass(): void
    {
        // Cherche d'abord speedtest/.env puis htdocs/.env
        $envFile = realpath(__DIR__ . '/../../.env') ?: realpath(__DIR__ . '/../../../.env');
        if (!$envFile) {
            throw new RuntimeException('.env introuvable — placer le fichier dans htdocs/');
        }
        $env = parse_ini_file($envFile);
        self::$pdo = new PDO(
            "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . ";dbname=" . ($env['DB_NAME'] ?? 'ft_speedtest') . ";charset=utf8mb4",
            $env['DB_USERNAME'] ?? '',
            $env['DB_PASSWORD'] ?? ''
        );
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function setUp(): void
    {
        $this->s = new StatService(self::$pdo);
    }

    // ══════════════════════════════════════════════════════════════════
    // parSite()
    // ══════════════════════════════════════════════════════════════════

    /** Retourne un tableau (éventuellement vide) */
    public function test_parSite_retourne_un_tableau(): void
    {
        $result = $this->s->parSite();
        $this->assertIsArray($result);
    }

    /** Chaque ligne contient les clés attendues */
    public function test_parSite_contient_les_cles_attendues(): void
    {
        $result = $this->s->parSite();
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        $cles = ['CODE_GX_SITE', 'NOM_SITE', 'NOM_REGION', 'NOM_INTERREGION',
                 'moy_ping', 'moy_download', 'moy_upload', 'nb_tests', 'dernier_test'];
        foreach ($cles as $cle) {
            $this->assertArrayHasKey($cle, $result[0], "Clé manquante : $cle");
        }
    }

    /** Les moyennes sont des valeurs numériques positives */
    public function test_parSite_moyennes_numeriques_positives(): void
    {
        $result = $this->s->parSite();
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        foreach ($result as $r) {
            $this->assertGreaterThanOrEqual(0, (float)$r['moy_ping'],     'moy_ping négatif');
            $this->assertGreaterThanOrEqual(0, (float)$r['moy_download'], 'moy_download négatif');
            $this->assertGreaterThanOrEqual(0, (float)$r['moy_upload'],   'moy_upload négatif');
            $this->assertGreaterThan(0,        (int)$r['nb_tests'],       'nb_tests <= 0');
        }
    }

    /** dernier_test est une date valide ou null */
    public function test_parSite_dernier_test_est_une_date_valide(): void
    {
        $result = $this->s->parSite();
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        foreach ($result as $r) {
            if ($r['dernier_test'] !== null) {
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}/',
                    $r['dernier_test'],
                    'dernier_test n\'est pas une date ISO'
                );
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // parRegion()
    // ══════════════════════════════════════════════════════════════════

    public function test_parRegion_retourne_un_tableau(): void
    {
        $result = $this->s->parRegion();
        $this->assertIsArray($result);
    }

    public function test_parRegion_contient_les_cles_attendues(): void
    {
        $result = $this->s->parRegion();
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        $cles = ['NOM_REGION', 'NOM_INTERREGION', 'moy_ping', 'moy_download', 'moy_upload', 'nb_tests'];
        foreach ($cles as $cle) {
            $this->assertArrayHasKey($cle, $result[0], "Clé manquante : $cle");
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // parInterregion()
    // ══════════════════════════════════════════════════════════════════

    public function test_parInterregion_retourne_un_tableau(): void
    {
        $result = $this->s->parInterregion();
        $this->assertIsArray($result);
    }

    public function test_parInterregion_contient_les_cles_attendues(): void
    {
        $result = $this->s->parInterregion();
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        $cles = ['NOM_INTERREGION', 'moy_ping', 'moy_download', 'moy_upload', 'nb_tests'];
        foreach ($cles as $cle) {
            $this->assertArrayHasKey($cle, $result[0], "Clé manquante : $cle");
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // parDepartement()
    // ══════════════════════════════════════════════════════════════════

    public function test_parDepartement_retourne_un_tableau(): void
    {
        $result = $this->s->parDepartement();
        $this->assertIsArray($result);
    }

    public function test_parDepartement_contient_les_cles_attendues(): void
    {
        $result = $this->s->parDepartement();
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        $cles = ['NOM_DEPARTEMENT', 'NOM_REGION', 'NOM_INTERREGION',
                 'moy_ping', 'moy_download', 'moy_upload', 'nb_tests'];
        foreach ($cles as $cle) {
            $this->assertArrayHasKey($cle, $result[0], "Clé manquante : $cle");
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // nationale()
    // ══════════════════════════════════════════════════════════════════

    public function test_nationale_retourne_un_tableau_associatif(): void
    {
        $result = $this->s->nationale();
        $this->assertIsArray($result);
    }

    public function test_nationale_contient_les_cles_attendues(): void
    {
        $result = $this->s->nationale();
        $cles   = ['moy_ping', 'moy_download', 'moy_upload', 'nb_tests'];
        foreach ($cles as $cle) {
            $this->assertArrayHasKey($cle, $result, "Clé manquante : $cle");
        }
    }

    /** nb_tests est >= 0 même si la table est vide */
    public function test_nationale_nb_tests_est_positif_ou_zero(): void
    {
        $result = $this->s->nationale();
        $this->assertGreaterThanOrEqual(0, (int)$result['nb_tests']);
    }

    // ══════════════════════════════════════════════════════════════════
    // evolution()
    // ══════════════════════════════════════════════════════════════════

    public function test_evolution_sans_filtre_retourne_un_tableau(): void
    {
        $result = $this->s->evolution();
        $this->assertIsArray($result);
    }

    public function test_evolution_avec_site_valide_retourne_uniquement_ce_site(): void
    {
        // Récupère un site qui a des logs
        $sites = $this->s->parSite();
        if (empty($sites)) $this->markTestSkipped('Aucun log en base.');

        $siteId = $sites[0]['CODE_GX_SITE'];
        $result = $this->s->evolution($siteId);

        foreach ($result as $r) {
            $this->assertEquals($siteId, $r['CODE_GX_SITE'], 'Site inattendu dans evolution()');
        }
    }

    public function test_evolution_avec_site_inexistant_retourne_tableau_vide(): void
    {
        $result = $this->s->evolution('SITE_INEXISTANT_XYZ');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_evolution_contient_les_cles_attendues(): void
    {
        $result = $this->s->evolution();
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        $cles = ['CODE_GX_SITE', 'NOM_SITE', 'mois', 'moy_ping', 'moy_download', 'moy_upload', 'nb_tests'];
        foreach ($cles as $cle) {
            $this->assertArrayHasKey($cle, $result[0], "Clé manquante : $cle");
        }
    }

    /** Le champ mois doit être au format YYYY-MM */
    public function test_evolution_mois_au_bon_format(): void
    {
        $result = $this->s->evolution();
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        foreach ($result as $r) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}$/',
                $r['mois'],
                "Format mois invalide : {$r['mois']}"
            );
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // alertes()
    // ══════════════════════════════════════════════════════════════════

    public function test_alertes_retourne_un_tableau(): void
    {
        $result = $this->s->alertes();
        $this->assertIsArray($result);
    }

    public function test_alertes_seuil_tres_haut_retourne_tous_les_sites(): void
    {
        $parSite  = $this->s->parSite();
        $alertes  = $this->s->alertes(seuilDl: 9999, seuilPing: 0);
        // Avec un seuil download irréaliste, tous les sites devraient être en alerte
        $this->assertCount(count($parSite), $alertes);
    }

    public function test_alertes_seuil_zero_retourne_tableau_vide(): void
    {
        // Avec seuil download = 0 et ping = 9999, aucun site ne devrait être en alerte
        $result = $this->s->alertes(seuilDl: 0, seuilPing: 9999);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_alertes_contient_les_cles_attendues(): void
    {
        $result = $this->s->alertes(seuilDl: 9999, seuilPing: 0);
        if (empty($result)) $this->markTestSkipped('Aucun log en base.');

        $cles = ['CODE_GX_SITE', 'NOM_SITE', 'NOM_REGION',
                 'moy_download', 'moy_upload', 'moy_ping', 'nb_tests'];
        foreach ($cles as $cle) {
            $this->assertArrayHasKey($cle, $result[0], "Clé manquante : $cle");
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // seuils()
    // ══════════════════════════════════════════════════════════════════

    public function test_seuils_retourne_un_tableau(): void
    {
        $result = $this->s->seuils();
        $this->assertIsArray($result);
    }

    public function test_seuils_contient_trois_entrees(): void
    {
        $result = $this->s->seuils();
        $this->assertGreaterThanOrEqual(3, count($result), 'FT_SEUILS doit contenir au moins 3 entrées');

    }

    public function test_seuils_contient_les_cles_attendues(): void
    {
        $result = $this->s->seuils();
        if (empty($result)) $this->markTestSkipped('Aucun seuil en base.');

        $cles = ['NOM_SEUIL', 'VALEUR_BONNE', 'VALEUR_MAUVAISE'];
        foreach ($cles as $cle) {
            $this->assertArrayHasKey($cle, $result[0], "Clé manquante : $cle");
        }
    }

    public function test_seuils_noms_attendus_presents(): void
    {
        $result = $this->s->seuils();
        $noms   = array_column($result, 'NOM_SEUIL');
        $this->assertContains('ping',     $noms, 'Seuil ping manquant');
        $this->assertContains('download', $noms, 'Seuil download manquant');
        $this->assertContains('upload',   $noms, 'Seuil upload manquant');
    }

    public function test_seuils_valeurs_sont_numeriques_positives(): void
    {
        $result = $this->s->seuils();
        foreach ($result as $r) {
            $this->assertGreaterThan(0, (float)$r['VALEUR_BONNE'],    "{$r['NOM_SEUIL']} VALEUR_BONNE <= 0");
            $this->assertGreaterThan(0, (float)$r['VALEUR_MAUVAISE'], "{$r['NOM_SEUIL']} VALEUR_MAUVAISE <= 0");
        }
    }
}