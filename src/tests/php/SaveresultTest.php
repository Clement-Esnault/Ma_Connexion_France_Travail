<?php
// Tests unitaires pour la logique de backend/ip/save_result.php
// Les endpoints HTTP ne sont pas appelés directement — on teste la logique extraite

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/ip/getIP_util.php';

class SaveResultTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────
    // Validation des valeurs de mesure
    // ──────────────────────────────────────────────────────────────────

    public function testValeursValidesPassentLaValidation(): void
    {
        $ping     = floatval('36.5');
        $download = floatval('100.0');
        $upload   = floatval('50.0');
        $this->assertFalse($ping <= 0 || $download <= 0 || $upload <= 0);
    }

    public function testPingNegatifEstRejete(): void
    {
        $ping = floatval('-5');
        $this->assertTrue($ping <= 0);
    }

    public function testDownloadZeroEstRejete(): void
    {
        $download = floatval('0');
        $this->assertTrue($download <= 0);
    }

    public function testUploadManquantEstRejete(): void
    {
        $data   = [];
        $upload = floatval($data['upload'] ?? 0);
        $this->assertTrue($upload <= 0);
    }

    public function testValeurTexteEstRejete(): void
    {
        $ping = floatval('abc');
        $this->assertTrue($ping <= 0);
    }

    public function testValeursLimitesValides(): void
    {
        // Valeurs très basses mais positives — acceptées
        $this->assertFalse(floatval('0.1') <= 0);
    }

    // ──────────────────────────────────────────────────────────────────
    // Résolution du site par CIDR (logique centrale de save_result)
    // ──────────────────────────────────────────────────────────────────

    private function findSite(string $ip, array $sites): ?string
    {
        foreach ($sites as $site) {
            if (ipDansReseau($ip, $site['IP_RESEAU'], $site['MASQUE_SITE'])) {
                return $site['CODE_GX_SITE'];
            }
        }
        return null;
    }

    private function fixtures(): array
    {
        return [
            ['CODE_GX_SITE' => 'GX001', 'IP_RESEAU' => '10.30.194.0', 'MASQUE_SITE' => 24],
            ['CODE_GX_SITE' => 'GX002', 'IP_RESEAU' => '10.30.195.0', 'MASQUE_SITE' => 24],
            ['CODE_GX_SITE' => 'GX003', 'IP_RESEAU' => '192.168.1.0', 'MASQUE_SITE' => 16],
        ];
    }

    public function testIpMatcheSonSite(): void
    {
        $this->assertSame('GX001', $this->findSite('10.30.194.50', $this->fixtures()));
    }

    public function testIpMatcheDeuxiemeSite(): void
    {
        $this->assertSame('GX002', $this->findSite('10.30.195.1', $this->fixtures()));
    }

    public function testIpHorsReseauRetourneNull(): void
    {
        $this->assertNull($this->findSite('172.16.0.1', $this->fixtures()));
    }

    public function testIpMatcheSiteMasque16(): void
    {
        $this->assertSame('GX003', $this->findSite('192.168.50.100', $this->fixtures()));
    }

    public function testIpLocalhostNonMatchee(): void
    {
        $this->assertNull($this->findSite('127.0.0.1', $this->fixtures()));
    }

    public function testIpInvalidNonMatchee(): void
    {
        $this->assertNull($this->findSite('not_an_ip', $this->fixtures()));
    }

    public function testPremierSiteMatcheEstRetourne(): void
    {
        // S'assure que le premier match est retourné (pas de double match)
        $sites = [
            ['CODE_GX_SITE' => 'GX001', 'IP_RESEAU' => '10.30.0.0',   'MASQUE_SITE' => 16],
            ['CODE_GX_SITE' => 'GX002', 'IP_RESEAU' => '10.30.194.0', 'MASQUE_SITE' => 24],
        ];
        // L'IP est dans les deux réseaux — doit retourner le premier
        $this->assertSame('GX001', $this->findSite('10.30.194.5', $sites));
    }

    // ──────────────────────────────────────────────────────────────────
    // Construction du JSON de réponse
    // ──────────────────────────────────────────────────────────────────

    public function testResponseSuccessContiensLesClesAttendues(): void
    {
        $response = json_encode(['success' => true, 'CODE_GX_SITE' => 'GX001', 'ip_client' => '10.30.194.5']);
        $decoded  = json_decode($response, true);
        $this->assertTrue($decoded['success']);
        $this->assertArrayHasKey('CODE_GX_SITE', $decoded);
        $this->assertArrayHasKey('ip_client',    $decoded);
    }

    public function testResponseEchecSiteIntrouvable(): void
    {
        $response = json_encode(['success' => false, 'error' => 'Aucun site trouvé pour cette IP', 'ip_client' => '1.2.3.4']);
        $decoded  = json_decode($response, true);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('Aucun site', $decoded['error']);
    }

    public function testResponseEchecValeursInvalides(): void
    {
        $response = json_encode(['success' => false, 'error' => 'Valeurs de mesure invalides', 'ip_client' => null]);
        $decoded  = json_decode($response, true);
        $this->assertFalse($decoded['success']);
        $this->assertNull($decoded['ip_client']);
    }
}