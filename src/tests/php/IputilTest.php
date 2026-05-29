<?php
// Tests unitaires pour backend/ip/getIP_util.php
// Couvre : normalizeCandidateIp, ipInNetwork, getClientIp

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/ip/getIP_util.php';

class IpUtilTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────
    // normalizeCandidateIp
    // ──────────────────────────────────────────────────────────────────

    public function testNormalizeIpValide(): void
    {
        $this->assertSame('10.30.194.5', normaliserCandidatIp('10.30.194.5'));
    }

    public function testNormalizeIpAvecEspaces(): void
    {
        $this->assertSame('10.30.194.5', normaliserCandidatIp('  10.30.194.5  '));
    }

    public function testNormalizeIpMultiValeur(): void
    {
        // X-Forwarded-For peut contenir plusieurs IPs séparées par des virgules
        $this->assertSame('10.30.194.5', normaliserCandidatIp('10.30.194.5, 10.30.194.6'));
    }

    public function testNormalizeIpInvalide(): void
    {
        $this->assertFalse(normaliserCandidatIp('not_an_ip'));
    }

    public function testNormalizeIpVide(): void
    {
        $this->assertFalse(normaliserCandidatIp(''));
    }

    public function testNormalizeIpAvecSeulementVirgule(): void
    {
        $this->assertFalse(normaliserCandidatIp(', 10.30.194.5'));
    }

    public function testNormalizeIPv6Valide(): void
    {
        $this->assertSame('::1', normaliserCandidatIp('::1', FILTER_FLAG_IPV6));
    }

    public function testNormalizeIPv4EnModeIPv6(): void
    {
        $this->assertFalse(normaliserCandidatIp('10.0.0.1', FILTER_FLAG_IPV6));
    }

    // ──────────────────────────────────────────────────────────────────
    // ipInNetwork — cas nominaux
    // ──────────────────────────────────────────────────────────────────

    public function testIpDansLeReseau(): void
    {
        $this->assertTrue(ipDansReseau('10.30.194.5', '10.30.194.0', 24));
    }

    public function testIpHorsReseau(): void
    {
        $this->assertFalse(ipDansReseau('10.30.195.1', '10.30.194.0', 24));
    }

    public function testIpEgaleAdresseReseau(): void
    {
        $this->assertTrue(ipDansReseau('10.30.194.0', '10.30.194.0', 24));
    }

    public function testIpBroadcast(): void
    {
        $this->assertTrue(ipDansReseau('10.30.194.255', '10.30.194.0', 24));
    }

    public function testMasque16(): void
    {
        $this->assertTrue(ipDansReseau('192.168.45.10', '192.168.0.0', 16));
    }

    public function testMasque16HorsReseau(): void
    {
        $this->assertFalse(ipDansReseau('192.169.0.1', '192.168.0.0', 16));
    }

    public function testMasque32(): void
    {
        $this->assertTrue(ipDansReseau('10.0.0.1', '10.0.0.1', 32));
    }

    public function testMasque32AutreIp(): void
    {
        $this->assertFalse(ipDansReseau('10.0.0.2', '10.0.0.1', 32));
    }

    public function testMasque8(): void
    {
        $this->assertTrue(ipDansReseau('10.200.50.1', '10.0.0.0', 8));
    }

    // ──────────────────────────────────────────────────────────────────
    // ipInNetwork — cas invalides
    // ──────────────────────────────────────────────────────────────────

    public function testIpInvalide(): void
    {
        $this->assertFalse(ipDansReseau('999.999.999.999', '10.30.194.0', 24));
    }

    public function testReseauInvalide(): void
    {
        $this->assertFalse(ipDansReseau('10.30.194.5', 'non_valide', 24));
    }

    public function testIpVide(): void
    {
        $this->assertFalse(ipDansReseau('', '10.30.194.0', 24));
    }

    public function testReseauVide(): void
    {
        $this->assertFalse(ipDansReseau('10.30.194.5', '', 24));
    }

    // ──────────────────────────────────────────────────────────────────
    // getClientIp — injection via $_SERVER
    // ──────────────────────────────────────────────────────────────────

    private function resetServerHeaders(): void
    {
        unset(
            $_SERVER['HTTP_CLIENT_IP'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_CF_CONNECTING_IPV6']
        );
    }

    public function testGetClientIpDepuisRemoteAddr(): void
    {
        $this->resetServerHeaders();
        $_SERVER['REMOTE_ADDR'] = '10.x.x.x';
        $this->assertSame('10.x.x.x', getClientIp());
    }

    public function testGetClientIpRemoteAddrAvecPrefixeFFFF(): void
    {
        $this->resetServerHeaders();
        $_SERVER['REMOTE_ADDR'] = '::ffff:10.x.x.x';
        $this->assertSame('10.x.x.x', getClientIp());
    }

    public function testGetClientIpDepuisXForwardedFor(): void
    {
        $this->resetServerHeaders();
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.50.1.200';
        $this->assertSame('10.50.1.200', getClientIp());
    }

    public function testGetClientIpXForwardedForMultiValeur(): void
    {
        $this->resetServerHeaders();
        $_SERVER['REMOTE_ADDR']          = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.50.1.200, 10.50.1.100';
        $this->assertSame('10.50.1.200', getClientIp());
    }

    public function testGetClientIpPrioriteCFConnecting(): void
    {
        $this->resetServerHeaders();
        $_SERVER['REMOTE_ADDR']             = '127.0.0.1';
        $_SERVER['HTTP_CF_CONNECTING_IPV6'] = '::1';
        $_SERVER['HTTP_X_FORWARDED_FOR']    = '10.50.1.200';
        $this->assertSame('::1', getClientIp());
    }

    public function testGetClientIpXForwardedForInvalide(): void
    {
        $this->resetServerHeaders();
        $_SERVER['REMOTE_ADDR']          = '10.x.x.x';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not_an_ip';
        $this->assertSame('10.x.x.x', getClientIp());
    }
}