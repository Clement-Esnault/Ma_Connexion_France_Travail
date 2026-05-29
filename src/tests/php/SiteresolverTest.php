<?php
/**
 * SiteResolverTest.php
 *
 * Tests unitaires pour SiteResolverService.
 * Utilise un stub PDO + CacheService pour ne pas toucher la base.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/ip/getIP_util.php';
require_once __DIR__ . '/../backend/services/CacheService.php';
require_once __DIR__ . '/../backend/services/SiteResolverService.php';

class SiteResolverTest extends TestCase
{
    /** @var array Fixtures CIDR communes à tous les tests */
    private array $sites = [
        ['CODE_GX_SITE' => 'GX001', 'NOM_SITE' => 'Caen SIR',   'IP_RESEAU' => '10.30.194.0', 'MASQUE_SITE' => 24],
        ['CODE_GX_SITE' => 'GX002', 'NOM_SITE' => 'Rouen SIR',  'IP_RESEAU' => '10.30.195.0', 'MASQUE_SITE' => 24],
        ['CODE_GX_SITE' => 'GX003', 'NOM_SITE' => 'Paris SIR',  'IP_RESEAU' => '192.168.1.0', 'MASQUE_SITE' => 16],
        ['CODE_GX_SITE' => 'GX004', 'NOM_SITE' => 'Lille SIR',  'IP_RESEAU' => '172.16.0.0',  'MASQUE_SITE' => 12],
    ];

    /** Crée un resolver dont le cache retourne directement les fixtures. */
    private function creerResolver(): SiteResolverService
    {
        // PDO mock — jamais appelé car le cache répond toujours
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('query');

        $cache = $this->createMock(CacheService::class);
        $cache->method('obtenir')->willReturn($this->sites);

        return new SiteResolverService($pdo, $cache);
    }

    // ── trouverCode ───────────────────────────────────────────────────

    public function testTrouverCodeIpDansReseau(): void
    {
        $this->assertSame('GX001', $this->creerResolver()->trouverCode('10.30.194.50'));
    }

    public function testTrouverCodeDeuxiemeSite(): void
    {
        $this->assertSame('GX002', $this->creerResolver()->trouverCode('10.30.195.1'));
    }

    public function testTrouverCodeMasque16(): void
    {
        $this->assertSame('GX003', $this->creerResolver()->trouverCode('192.168.50.100'));
    }

    public function testTrouverCodeMasque12(): void
    {
        $this->assertSame('GX004', $this->creerResolver()->trouverCode('172.20.0.5'));
    }

    public function testTrouverCodeIpHorsReseauRetourneNull(): void
    {
        $this->assertNull($this->creerResolver()->trouverCode('8.8.8.8'));
    }

    public function testTrouverCodeLocalhostRetourneNull(): void
    {
        $this->assertNull($this->creerResolver()->trouverCode('127.0.0.1'));
    }

    public function testTrouverCodeIpInvalidRetourneNull(): void
    {
        $this->assertNull($this->creerResolver()->trouverCode('not_an_ip'));
    }

    public function testTrouverCodeIpVideRetourneNull(): void
    {
        $this->assertNull($this->creerResolver()->trouverCode(''));
    }

    // ── trouverNom ────────────────────────────────────────────────────

    public function testTrouverNomRetourneLeNomDuSite(): void
    {
        $this->assertSame('Caen SIR', $this->creerResolver()->trouverNom('10.30.194.1'));
    }

    public function testTrouverNomIpInconnueRetourneNull(): void
    {
        $this->assertNull($this->creerResolver()->trouverNom('1.2.3.4'));
    }

    // ── Cache ─────────────────────────────────────────────────────────

    public function testLesCacheEstUtiliseSiPresent(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('query'); // BDD jamais interrogée

        $cache = $this->createMock(CacheService::class);
        $cache->expects($this->once())->method('obtenir')->willReturn($this->sites);

        $resolver = new SiteResolverService($pdo, $cache);
        // Deux appels — le cache n'est chargé qu'une fois (private chargerSites)
        $resolver->trouverCode('10.30.194.1');
        $resolver->trouverNom('10.30.194.1');
    }

    public function testLaBDDEstInterrogeSiCacheVide(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn($this->sites);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('query')->willReturn($stmt);

        $cache = $this->createMock(CacheService::class);
        $cache->method('obtenir')->willReturn(null);   // cache vide
        $cache->expects($this->once())->method('stocker'); // doit être stocké

        $resolver = new SiteResolverService($pdo, $cache);
        $this->assertSame('GX001', $resolver->trouverCode('10.30.194.1'));
    }
}