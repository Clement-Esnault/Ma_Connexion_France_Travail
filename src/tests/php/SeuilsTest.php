<?php
/**
 * SeuilsTest.php
 *
 * Tests unitaires pour SeuilService.
 * Couvre : réindexation, calcul de verdict, mise à jour.
 *
 * Les méthodes privées reindexer() et verdict() sont testées via
 * l'interface publique de SeuilService sans dépendance à la BDD.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/services/SeuilService.php';

class SeuilsTest extends TestCase
{
    /** @var SeuilService */
    private SeuilService $service;

    /** Seuils de test fixes — indépendants de la BDD. */
    private array $seuils;

    protected function setUp(): void
    {
        // SeuilService::reindexer() est public — on peut l'instancier sans PDO
        // en utilisant un mock minimal pour les tests qui n'appellent pas charger()
        $pdo           = $this->createStub(PDO::class);
        $this->service = new SeuilService($pdo);

        $this->seuils = $this->service->reindexer([
            ['NOM_SEUIL' => 'download', 'VALEUR_BONNE' => '100', 'VALEUR_MAUVAISE' => '10'],
            ['NOM_SEUIL' => 'upload',   'VALEUR_BONNE' => '50',  'VALEUR_MAUVAISE' => '5'],
            ['NOM_SEUIL' => 'ping',     'VALEUR_BONNE' => '50',  'VALEUR_MAUVAISE' => '100'],
        ]);
    }

    // ── Réindexation ─────────────────────────────────────────────────

    public function testReindexationContiensLes3Metriques(): void
    {
        $this->assertArrayHasKey('download', $this->seuils);
        $this->assertArrayHasKey('upload',   $this->seuils);
        $this->assertArrayHasKey('ping',     $this->seuils);
    }

    public function testReindexationValeurs(): void
    {
        $this->assertSame(100.0, $this->seuils['download']['bon']);
        $this->assertSame(10.0,  $this->seuils['download']['mauvais']);
    }

    public function testReindexationUploadValeurs(): void
    {
        $this->assertSame(50.0, $this->seuils['upload']['bon']);
        $this->assertSame(5.0,  $this->seuils['upload']['mauvais']);
    }

    // ── Verdicts Download ─────────────────────────────────────────────

    public function testDownloadConfort(): void
    {
        $this->assertSame('confort', $this->service->verdict('download', 150.0, $this->seuils));
    }

    public function testDownloadConfortExact(): void
    {
        $this->assertSame('confort', $this->service->verdict('download', 100.0, $this->seuils));
    }

    public function testDownloadFonctionnel(): void
    {
        $this->assertSame('fonctionnel', $this->service->verdict('download', 50.0, $this->seuils));
    }

    public function testDownloadInsuffisant(): void
    {
        $this->assertSame('insuffisant', $this->service->verdict('download', 5.0, $this->seuils));
    }

    public function testDownloadInsuffisantExact(): void
    {
        $this->assertSame('insuffisant', $this->service->verdict('download', 10.0, $this->seuils));
    }

    // ── Verdicts Upload ───────────────────────────────────────────────

    public function testUploadConfort(): void
    {
        $this->assertSame('confort', $this->service->verdict('upload', 80.0, $this->seuils));
    }

    public function testUploadInsuffisant(): void
    {
        $this->assertSame('insuffisant', $this->service->verdict('upload', 2.0, $this->seuils));
    }

    public function testUploadFonctionnel(): void
    {
        $this->assertSame('fonctionnel', $this->service->verdict('upload', 25.0, $this->seuils));
    }

    // ── Verdicts Ping (inversé) ───────────────────────────────────────

    public function testPingConfort(): void
    {
        $this->assertSame('confort', $this->service->verdict('ping', 30.0, $this->seuils));
    }

    public function testPingConfortExact(): void
    {
        $this->assertSame('confort', $this->service->verdict('ping', 50.0, $this->seuils));
    }

    public function testPingFonctionnel(): void
    {
        $this->assertSame('fonctionnel', $this->service->verdict('ping', 75.0, $this->seuils));
    }

    public function testPingInsuffisant(): void
    {
        $this->assertSame('insuffisant', $this->service->verdict('ping', 120.0, $this->seuils));
    }

    public function testPingInsuffisantExact(): void
    {
        $this->assertSame('insuffisant', $this->service->verdict('ping', 100.0, $this->seuils));
    }

    // ── Cas limites ───────────────────────────────────────────────────

    public function testMetriqueInconnueRetourneInconnu(): void
    {
        $this->assertSame('inconnu', $this->service->verdict('jitter', 50.0, $this->seuils));
    }

    public function testValeurZeroPingInsuffisant(): void
    {
        // Ping 0 ms = confort (très rapide)
        $this->assertSame('confort', $this->service->verdict('ping', 0.0, $this->seuils));
    }

    public function testValeurZeroDownloadInsuffisant(): void
    {
        // DL 0 Mbit/s = insuffisant
        $this->assertSame('insuffisant', $this->service->verdict('download', 0.0, $this->seuils));
    }

    public function testSeuilsVidesRetourneInconnu(): void
    {
        $this->assertSame('inconnu', $this->service->verdict('download', 50.0, []));
    }
}