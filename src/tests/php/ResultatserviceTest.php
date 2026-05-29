<?php
/**
 * ResultatServiceTest.php
 *
 * Tests unitaires pour ResultatService.
 * Couvre validation, normalisation session_id, et insertion (mock PDO).
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/services/ResultatService.php';

class ResultatServiceTest extends TestCase
{
    private string $sessionValide = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    private function service(): ResultatService
    {
        return new ResultatService($this->createMock(PDO::class));
    }

    // ── Validation ────────────────────────────────────────────────────

    public function testValeursValidesRetourneNull(): void
    {
        $this->assertNull(
            $this->service()->valider(36.5, 100.0, 50.0, 'precise', $this->sessionValide)
        );
    }

    public function testModeFastRefuse(): void
    {
        $this->assertNotNull(
            $this->service()->valider(10.0, 5.0, 3.0, 'fast', $this->sessionValide)
        );
    }

    public function testPingNegatifRejete(): void
    {
        $this->assertNotNull(
            $this->service()->valider(-1.0, 100.0, 50.0, 'precise', $this->sessionValide)
        );
    }

    public function testPingZeroRejete(): void
    {
        $this->assertNotNull(
            $this->service()->valider(0.0, 100.0, 50.0, 'precise', $this->sessionValide)
        );
    }

    public function testDownloadZeroRejete(): void
    {
        $this->assertNotNull(
            $this->service()->valider(30.0, 0.0, 50.0, 'precise', $this->sessionValide)
        );
    }

    public function testUploadNegatifRejete(): void
    {
        $this->assertNotNull(
            $this->service()->valider(30.0, 100.0, -5.0, 'precise', $this->sessionValide)
        );
    }

    public function testModeInvalideRejete(): void
    {
        $this->assertNotNull(
            $this->service()->valider(30.0, 100.0, 50.0, 'inexistant', $this->sessionValide)
        );
    }

    public function testModeVideRejete(): void
    {
        $this->assertNotNull(
            $this->service()->valider(30.0, 100.0, 50.0, '', $this->sessionValide)
        );
    }

    public function testSessionIdInvalideRejete(): void
    {
        $this->assertNotNull(
            $this->service()->valider(30.0, 100.0, 50.0, 'precise', 'pas-un-uuid')
        );
    }

    public function testSessionIdVideRejete(): void
    {
        $this->assertNotNull(
            $this->service()->valider(30.0, 100.0, 50.0, 'precise', '')
        );
    }

    // ── normaliserSessionId ───────────────────────────────────────────

    public function testNormalisationUuidValide(): void
    {
        $this->assertSame(
            $this->sessionValide,
            ResultatService::normaliserSessionId($this->sessionValide)
        );
    }

    public function testNormalisationSupprimeCaracteresInvalides(): void
    {
        $brut   = 'A1B2C3D4-E5F6-4A7B-8C9D-0E1F2A3B4C5D'; // majuscules
        $result = ResultatService::normaliserSessionId($brut);
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]{36}$/', $result);
    }

    public function testNormalisationTronqueA36Caracteres(): void
    {
        $long = str_repeat('a1b2c3d4-', 10);
        $this->assertSame(36, strlen(ResultatService::normaliserSessionId($long)));
    }

    public function testNormalisationChaineVide(): void
    {
        $this->assertSame('', ResultatService::normaliserSessionId(''));
    }

    // ── inserer ───────────────────────────────────────────────────────

    public function testInsererAppelleExecuteAvecLesBonnesValeurs(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with([36.5, 100.0, 50.0, 'precise', $this->sessionValide, '10.0.0.1', 'GX001']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $service = new ResultatService($pdo);
        $service->inserer(36.5, 100.0, 50.0, 'precise', $this->sessionValide, '10.0.0.1', 'GX001');
    }
}