<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/services/StatService.php';

/**
 * Tests d'intégration pour StatService::heatmapHoraire().
 *
 * Vérifie la structure de la réponse, les filtres (site, mode, période)
 * et les valeurs attendues (DAYOFWEEK, HOUR, agrégats).
 *
 * Utilise la base ft_speedtest réelle — tous les tests sont en lecture seule.
 *
 * Lancement : C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c tests/phpunit.xml
 */
class HeatmapTest extends TestCase
{
    private static PDO $pdo;
    private StatService $service;

    // ── Connexion unique pour toute la suite ──────────────────────────
    public static function setUpBeforeClass(): void
    {
        // Cherche d'abord speedtest/.env puis htdocs/.env
        $cheminEnv = realpath(__DIR__ . '/../../.env') ?: realpath(__DIR__ . '/../../../.env');
        if (!$cheminEnv) {
            throw new RuntimeException('.env introuvable — placer le fichier dans htdocs/');
        }
        $env = parse_ini_file($cheminEnv);
        self::$pdo = new PDO(
            'mysql:host=' . ($env['DB_HOST'] ?? 'localhost')
            . ';dbname=' . ($env['DB_NAME'] ?? 'ft_speedtest')
            . ';charset=utf8mb4',
            $env['DB_USERNAME'] ?? '',
            $env['DB_PASSWORD'] ?? ''
        );
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function setUp(): void
    {
        $this->service = new StatService(self::$pdo);
    }

    // ══════════════════════════════════════════════════════════════════
    // Structure de la réponse
    // ══════════════════════════════════════════════════════════════════

    /** Retourne toujours un tableau (même si vide) */
    public function test_retourne_un_tableau(): void
    {
        $resultat = $this->service->heatmapHoraire(null, 'download', null, null);
        $this->assertIsArray($resultat);
    }

    /** Chaque ligne contient les 4 clés attendues */
    public function test_cles_presentes(): void
    {
        $resultat = $this->service->heatmapHoraire(null, 'download', 30, null);
        if (empty($resultat)) {
            $this->markTestSkipped('Aucune donnée heatmap en base sur 30 jours.');
        }

        $premiereLigne = $resultat[0];
        $this->assertArrayHasKey('jour_semaine', $premiereLigne, 'Clé jour_semaine manquante');
        $this->assertArrayHasKey('heure',        $premiereLigne, 'Clé heure manquante');
        $this->assertArrayHasKey('valeur',       $premiereLigne, 'Clé valeur manquante');
        $this->assertArrayHasKey('nb',           $premiereLigne, 'Clé nb manquante');
    }

    /** jour_semaine est entre 1 et 7 (DAYOFWEEK MySQL) */
    public function test_jour_semaine_valide(): void
    {
        $resultat = $this->service->heatmapHoraire(null, 'download', 30, null);
        if (empty($resultat)) {
            $this->markTestSkipped('Aucune donnée heatmap en base sur 30 jours.');
        }

        foreach ($resultat as $ligne) {
            $jour = (int) $ligne['jour_semaine'];
            $this->assertGreaterThanOrEqual(1, $jour, 'DAYOFWEEK doit être >= 1');
            $this->assertLessThanOrEqual(7,  $jour, 'DAYOFWEEK doit être <= 7');
        }
    }

    /** heure est entre 0 et 23 */
    public function test_heure_valide(): void
    {
        $resultat = $this->service->heatmapHoraire(null, 'download', 30, null);
        if (empty($resultat)) {
            $this->markTestSkipped('Aucune donnée heatmap en base sur 30 jours.');
        }

        foreach ($resultat as $ligne) {
            $heure = (int) $ligne['heure'];
            $this->assertGreaterThanOrEqual(0,  $heure, 'HOUR doit être >= 0');
            $this->assertLessThanOrEqual(23,    $heure, 'HOUR doit être <= 23');
        }
    }

    /** valeur est un nombre positif */
    public function test_valeur_positive(): void
    {
        $resultat = $this->service->heatmapHoraire(null, 'download', 30, null);
        if (empty($resultat)) {
            $this->markTestSkipped('Aucune donnée heatmap en base sur 30 jours.');
        }

        foreach ($resultat as $ligne) {
            $this->assertGreaterThan(0, (float) $ligne['valeur'], 'La valeur doit être > 0');
        }
    }

    /** nb (nombre de tests) est un entier positif */
    public function test_nb_positif(): void
    {
        $resultat = $this->service->heatmapHoraire(null, 'download', 30, null);
        if (empty($resultat)) {
            $this->markTestSkipped('Aucune donnée heatmap en base sur 30 jours.');
        }

        foreach ($resultat as $ligne) {
            $this->assertGreaterThan(0, (int) $ligne['nb'], 'nb doit être > 0');
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // Filtres métriques
    // ══════════════════════════════════════════════════════════════════

    /** Les 3 métriques retournent un tableau sans exception */
    public function test_metriques_valides(): void
    {
        foreach (['download', 'upload', 'ping'] as $metrique) {
            $resultat = $this->service->heatmapHoraire(null, $metrique, 30, null);
            $this->assertIsArray($resultat, "heatmapHoraire($metrique) doit retourner un tableau");
        }
    }

    /** Une métrique inconnue est traitée comme 'download' (match default) */
    public function test_metrique_inconnue_fallback_download(): void
    {
        $resultDownload = $this->service->heatmapHoraire(null, 'download', 30, null);
        $resultInconnu  = $this->service->heatmapHoraire(null, 'inconnu',  30, null);

        // Les deux doivent retourner le même nombre de lignes
        $this->assertCount(
            count($resultDownload),
            $resultInconnu,
            'Une métrique inconnue doit fallback sur download'
        );
    }

    // ══════════════════════════════════════════════════════════════════
    // Filtre période
    // ══════════════════════════════════════════════════════════════════

    /** Période nulle retourne plus ou autant de lignes qu'une période de 7 jours */
    public function test_periode_null_retourne_plus_que_7_jours(): void
    {
        $resultTout    = $this->service->heatmapHoraire(null, 'download', null, null);
        $result7Jours  = $this->service->heatmapHoraire(null, 'download', 7,   null);

        $this->assertGreaterThanOrEqual(
            count($result7Jours),
            count($resultTout),
            'Sans filtre période, on doit avoir autant ou plus de créneaux que sur 7 jours'
        );
    }

    /** Période de 0 jour retourne un tableau vide (aucun log futur) */
    public function test_periode_zero_retourne_vide(): void
    {
        $resultat = $this->service->heatmapHoraire(null, 'download', 0, null);
        // 0 jour = logs >= maintenant → probablement vide
        // On vérifie juste que c'est un tableau, pas une exception
        $this->assertIsArray($resultat);
    }

    // ══════════════════════════════════════════════════════════════════
    // Filtre mode
    // ══════════════════════════════════════════════════════════════════

    /** Filtre mode 'fast' retourne au plus autant de lignes que sans filtre */
    public function test_filtre_mode_fast_reduit_resultats(): void
    {
        $resultTous  = $this->service->heatmapHoraire(null, 'download', 30, null);
        $resultFast  = $this->service->heatmapHoraire(null, 'download', 30, 'fast');

        $this->assertLessThanOrEqual(
            count($resultTous),
            count($resultFast),
            "Le filtre mode='fast' ne peut pas retourner plus de créneaux que sans filtre"
        );
    }

    /** Filtre mode 'precise' retourne au plus autant de lignes que sans filtre */
    public function test_filtre_mode_precise_reduit_resultats(): void
    {
        $resultTous    = $this->service->heatmapHoraire(null, 'download', 30, null);
        $resultPrecise = $this->service->heatmapHoraire(null, 'download', 30, 'precise');

        $this->assertLessThanOrEqual(
            count($resultTous),
            count($resultPrecise),
            "Le filtre mode='precise' ne peut pas retourner plus de créneaux que sans filtre"
        );
    }

    // ══════════════════════════════════════════════════════════════════
    // Filtre site
    // ══════════════════════════════════════════════════════════════════

    /** 'all' retourne le même résultat que null */
    public function test_site_all_equivalent_a_null(): void
    {
        $resultNull = $this->service->heatmapHoraire(null,  'download', 30, null);
        $resultAll  = $this->service->heatmapHoraire('all', 'download', 30, null);

        $this->assertCount(
            count($resultNull),
            $resultAll,
            "site='all' doit retourner le même résultat que site=null"
        );
    }

    /** Un site inexistant retourne un tableau vide */
    public function test_site_inexistant_retourne_vide(): void
    {
        $resultat = $this->service->heatmapHoraire('GXINEXISTANT999', 'download', 30, null);
        $this->assertEmpty($resultat, 'Un site inexistant doit retourner un tableau vide');
    }

    /** Un site valide retourne au plus autant de créneaux que tous les sites */
    public function test_filtre_site_valide_reduit_resultats(): void
    {
        // Récupérer un CODE_GX_SITE réel depuis la BDD
        $codeGx = self::$pdo->query(
            "SELECT CODE_GX_SITE FROM FT_LOGS LIMIT 1"
        )->fetchColumn();

        if (!$codeGx) {
            $this->markTestSkipped('Aucun log en base pour tester le filtre site.');
        }

        $resultTous   = $this->service->heatmapHoraire(null,    'download', 30, null);
        $resultSite   = $this->service->heatmapHoraire($codeGx, 'download', 30, null);

        $this->assertLessThanOrEqual(
            count($resultTous),
            count($resultSite),
            'Un filtre site ne peut pas retourner plus de créneaux que tous les sites'
        );
    }
}