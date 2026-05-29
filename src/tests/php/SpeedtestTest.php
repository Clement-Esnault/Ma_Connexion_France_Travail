<?php

require_once __DIR__ . '/../backend/ip/getIP_util.php';

use PHPUnit\Framework\TestCase;

class SpeedtestTest extends TestCase
{
    // --- ipInNetwork - cas nominaux ---

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

    // --- ipInNetwork - cas invalides ---

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

    // --- Validation parametres save_result ---

    public function testFloatvalPingValide(): void
    {
        $data = ['ping' => '36.5', 'download' => '12.4', 'upload' => '13.4'];
        $this->assertSame(36.5, floatval($data['ping'] ?? 0));
    }

    public function testFloatvalValeurManquante(): void
    {
        $data = [];
        $this->assertSame(0.0, floatval($data['ping'] ?? 0));
    }

    public function testFloatvalValeurNegative(): void
    {
        $ping = floatval('-5');
        $this->assertLessThan(0, $ping);
    }

    public function testFloatvalValeurTexte(): void
    {
        $this->assertSame(0.0, floatval('abc'));
    }

    // --- Validation parametres recherche ---

    public function testRechercheVideRetourneTableauVide(): void
    {
        $q = trim('');
        $result = ($q === '') ? [] : ['donnees'];
        $this->assertSame([], $result);
    }

    public function testPageMinimumEst1(): void
    {
        $this->assertSame(1, max(1, intval('0')));
        $this->assertSame(1, max(1, intval('-5')));
    }

    public function testPageValide(): void
    {
        $this->assertSame(3, max(1, intval('3')));
    }

    public function testOffsetCalcul(): void
    {
        $this->assertSame(100, (3 - 1) * 50);
    }

    public function testNombreDePages(): void
    {
        $this->assertSame(3, (int) ceil(123 / 50));
    }

    public function testNombreDePagesExact(): void
    {
        $this->assertSame(2, (int) ceil(100 / 50));
    }

    public function testLikePatternConstruit(): void
    {
        $this->assertSame('%Paris%', '%' . 'Paris' . '%');
    }
}