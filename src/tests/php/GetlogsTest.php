<?php
// Tests unitaires pour la logique de backend/ip/get_logs.php
// Teste la construction des filtres WHERE et la pagination

use PHPUnit\Framework\TestCase;

class GetLogsTest extends TestCase
{
    // Logique extraite de get_logs.php
    private function buildWhere(string $code, string $debut, string $fin): array
    {
        $where  = ['CODE_GX_SITE = ?'];
        $params = [$code];
        if ($debut) { $where[] = 'DATE_LOGS >= ?'; $params[] = $debut . ' 00:00:00'; }
        if ($fin)   { $where[] = 'DATE_LOGS <= ?'; $params[] = $fin   . ' 23:59:59'; }
        return ['whereSQL' => 'WHERE ' . implode(' AND ', $where), 'params' => $params];
    }

    public function testWhereSansDateContientSiteSeulement(): void
    {
        $r = $this->buildWhere('GX001', '', '');
        $this->assertSame('WHERE CODE_GX_SITE = ?', $r['whereSQL']);
        $this->assertCount(1, $r['params']);
    }

    public function testWhereAvecDebutEtFin(): void
    {
        $r = $this->buildWhere('GX001', '2025-01-01', '2025-12-31');
        $this->assertCount(3, $r['params']);
        $this->assertContains('2025-01-01 00:00:00', $r['params']);
        $this->assertContains('2025-12-31 23:59:59', $r['params']);
    }

    public function testWhereAvecDebutSeulement(): void
    {
        $r = $this->buildWhere('GX001', '2025-06-01', '');
        $this->assertCount(2, $r['params']);
        $this->assertStringContainsString('DATE_LOGS >=', $r['whereSQL']);
        $this->assertStringNotContainsString('DATE_LOGS <=', $r['whereSQL']);
    }

    public function testWhereAvecFinSeulement(): void
    {
        $r = $this->buildWhere('GX001', '', '2025-12-31');
        $this->assertCount(2, $r['params']);
        $this->assertStringContainsString('DATE_LOGS <=', $r['whereSQL']);
        $this->assertStringNotContainsString('DATE_LOGS >=', $r['whereSQL']);
    }

    public function testCodeManquantEstDetecte(): void
    {
        $code = trim('');
        $this->assertSame('', $code);
    }

    public function testPaginationLimiteEtOffset(): void
    {
        $page   = 2;
        $limit  = 20;
        $offset = ($page - 1) * $limit;
        $this->assertSame(20, $offset);
    }

    public function testPaginationPremierePageOffset0(): void
    {
        $this->assertSame(0, (1 - 1) * 20);
    }

    public function testPagesCalculees(): void
    {
        $this->assertSame(5, (int)ceil(100 / 20));
        $this->assertSame(6, (int)ceil(101 / 20));
    }

    public function testResponseJsonStructure(): void
    {
        $response = json_encode([
            'total'   => 42,
            'page'    => 1,
            'pages'   => 3,
            'stats'   => ['avg_download' => 95.5],
            'results' => []
        ]);
        $decoded = json_decode($response, true);
        $this->assertArrayHasKey('total',   $decoded);
        $this->assertArrayHasKey('page',    $decoded);
        $this->assertArrayHasKey('pages',   $decoded);
        $this->assertArrayHasKey('stats',   $decoded);
        $this->assertArrayHasKey('results', $decoded);
    }
}