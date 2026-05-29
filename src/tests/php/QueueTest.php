<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/services/QueueService.php';

/**
 * Tests d'intégration pour QueueService.
 *
 * Utilise la base ft_speedtest réelle.
 * - setUp()              : vide FT_QUEUE avant chaque test
 * - tearDownAfterClass() : vide FT_QUEUE après toute la suite
 *
 * Lancement : C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c phpunit.xml
 */
class QueueTest extends TestCase
{
    private static PDO $pdo;
    private QueueService $q;

    // ── Connexion unique pour toute la suite ──────────────────────────
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../backend/config.php';
        // $pdo est créé par config.php
        self::$pdo = $pdo;
    }

    // ── Nettoyage avant chaque test ───────────────────────────────────
    protected function setUp(): void
    {
        $this->q = new QueueService(self::$pdo);
        $this->q->viderTable();
    }

    // ── Nettoyage final après toute la suite ──────────────────────────
    public static function tearDownAfterClass(): void
    {
        self::$pdo->query("DELETE FROM FT_QUEUE");
    }

    // ══════════════════════════════════════════════════════════════════
    // JOIN
    // ══════════════════════════════════════════════════════════════════

    /** Premier client → ready = true immédiatement */
    public function test_join_premier_client_est_ready(): void
    {
        $result = $this->q->rejoindre();

        $this->assertTrue($result['ready']);
        $this->assertNotEmpty($result['token']);
    }

    /** Deuxième client → ready = false */
    public function test_join_deuxieme_client_est_waiting(): void
    {
        $this->q->rejoindre();
        $result = $this->q->rejoindre();

        $this->assertFalse($result['ready']);
    }

    /** Token READY → STARTED_AT non null */
    public function test_join_ready_a_started_at(): void
    {
        $result = $this->q->rejoindre();
        $row    = $this->q->getLigne($result['token']);

        $this->assertNotNull($row['STARTED_AT']);
    }

    /** Token WAITING → STARTED_AT null */
    public function test_join_waiting_a_started_at_null(): void
    {
        $this->q->rejoindre();
        $result = $this->q->rejoindre();
        $row    = $this->q->getLigne($result['token']);

        $this->assertNull($row['STARTED_AT']);
    }

    /** Trois clients simultanés → 1 READY, 2 WAITING */
    public function test_join_trois_clients_un_seul_ready(): void
    {
        $this->q->rejoindre();
        $this->q->rejoindre();
        $this->q->rejoindre();

        $this->assertEquals(1, $this->q->compterParStatut(STATUS_READY));
        $this->assertEquals(2, $this->q->compterParStatut(STATUS_WAITING));
    }

    // ══════════════════════════════════════════════════════════════════
    // STATUS
    // ══════════════════════════════════════════════════════════════════

    /** Token READY → status retourne ready = true */
    public function test_status_ready_retourne_ready(): void
    {
        $join   = $this->q->rejoindre();
        $status = $this->q->statut($join['token']);

        $this->assertTrue($status['ready']);
    }

    /** Token WAITING avec READY présent → ready = false */
    public function test_status_waiting_non_ready_si_ready_present(): void
    {
        $this->q->rejoindre();
        $w      = $this->q->rejoindre();
        $status = $this->q->statut($w['token']);

        $this->assertFalse($status['ready']);
    }

    /** Token inexistant → retourne error */
    public function test_status_token_invalide_retourne_erreur(): void
    {
        $status = $this->q->statut('token_inexistant_000');

        $this->assertArrayHasKey('error', $status);
    }

    /** Premier WAITING → position 1 */
    public function test_status_position_premier_waiting(): void
    {
        $this->q->rejoindre();
        $w      = $this->q->rejoindre();
        $status = $this->q->statut($w['token']);

        $this->assertEquals(1, $status['position']);
    }

    /** Deuxième WAITING → position 2 */
    public function test_status_position_deuxieme_waiting(): void
    {
        $this->q->rejoindre();
        $this->q->rejoindre();
        $w      = $this->q->rejoindre();
        $status = $this->q->statut($w['token']);

        $this->assertEquals(2, $status['position']);
    }

    /** Quand le READY disparaît, le premier WAITING est promu */
    public function test_status_waiting_promu_quand_plus_de_ready(): void
    {
        $ready   = $this->q->rejoindre();
        $waiting = $this->q->rejoindre();

        // Suppression directe du READY sans appeler done()
        self::$pdo->prepare("DELETE FROM FT_QUEUE WHERE TOKEN = ?")
            ->execute([$ready['token']]);

        $status = $this->q->statut($waiting['token']);

        $this->assertTrue($status['ready']);
    }

    /** Seul le premier WAITING est promu, pas le second */
    public function test_status_seul_le_premier_waiting_est_promu(): void
    {
        $ready    = $this->q->rejoindre();
        $waiting1 = $this->q->rejoindre();
        $waiting2 = $this->q->rejoindre();

        self::$pdo->prepare("DELETE FROM FT_QUEUE WHERE TOKEN = ?")
            ->execute([$ready['token']]);

        $this->q->statut($waiting1['token']); // promotion de waiting1

        $status2 = $this->q->statut($waiting2['token']);
        $this->assertFalse($status2['ready']);
    }

    // ══════════════════════════════════════════════════════════════════
    // DONE
    // ══════════════════════════════════════════════════════════════════

    /** done() sur READY → passe en STATUS_DONE */
    public function test_done_ready_passe_en_done(): void
    {
        $join = $this->q->rejoindre();
        $this->q->terminer($join['token']);
        $row = $this->q->getLigne($join['token']);

        $this->assertEquals(STATUS_DONE, (int)$row['ID_STATUS']);
    }

    /** done() sur READY → STARTED_AT remis à null */
    public function test_done_ready_remet_started_at_null(): void
    {
        $join = $this->q->rejoindre();
        $this->q->terminer($join['token']);
        $row = $this->q->getLigne($join['token']);

        $this->assertNull($row['STARTED_AT']);
    }

    /** done() promeut le premier WAITING en READY */
    public function test_done_promeut_premier_waiting(): void
    {
        $ready   = $this->q->rejoindre();
        $waiting = $this->q->rejoindre();

        $this->q->terminer($ready['token']);

        $row = $this->q->getLigne($waiting['token']);
        $this->assertEquals(STATUS_READY, (int)$row['ID_STATUS']);
        $this->assertNotNull($row['STARTED_AT']);
    }

    /** done() promeut le premier WAITING et pas le second */
    public function test_done_promeut_uniquement_le_premier_waiting(): void
    {
        $ready    = $this->q->rejoindre();
        $waiting1 = $this->q->rejoindre();
        $waiting2 = $this->q->rejoindre();

        $this->q->terminer($ready['token']);

        $row1 = $this->q->getLigne($waiting1['token']);
        $row2 = $this->q->getLigne($waiting2['token']);

        $this->assertEquals(STATUS_READY,   (int)$row1['ID_STATUS']);
        $this->assertEquals(STATUS_WAITING, (int)$row2['ID_STATUS']);
    }

    /** done() sur WAITING → supprime la ligne (fermeture d'onglet) */
    public function test_done_waiting_supprime_la_ligne(): void
    {
        $this->q->rejoindre();
        $waiting = $this->q->rejoindre();

        $this->q->terminer($waiting['token']);

        $this->assertNull($this->q->getLigne($waiting['token']));
    }

    /** done() sur WAITING ne touche pas le READY */
    public function test_done_waiting_ne_touche_pas_le_ready(): void
    {
        $ready   = $this->q->rejoindre();
        $waiting = $this->q->rejoindre();

        $this->q->terminer($waiting['token']);

        $row = $this->q->getLigne($ready['token']);
        $this->assertEquals(STATUS_READY, (int)$row['ID_STATUS']);
    }

    /** done() retourne success = true */
    public function test_done_retourne_success(): void
    {
        $join   = $this->q->rejoindre();
        $result = $this->q->terminer($join['token']);

        $this->assertTrue($result['success']);
    }

    // ══════════════════════════════════════════════════════════════════
    // CLEANUP
    // ══════════════════════════════════════════════════════════════════

    /** cleanup() supprime les lignes DONE */
    public function test_cleanup_supprime_done(): void
    {
        $join = $this->q->rejoindre();
        $this->q->terminer($join['token']);

        // Forcer CREATED_AT dans le passé pour dépasser le seuil de 30s
        self::$pdo->prepare("
            UPDATE FT_QUEUE SET CREATED_AT = NOW() - INTERVAL 31 SECOND WHERE TOKEN = ?
        ")->execute([$join['token']]);

        $this->q->nettoyer();

        $this->assertEquals(0, $this->q->compterParStatut(STATUS_DONE));
    }
    /** cleanup() supprime les READY dont STARTED_AT > 90s */
    public function test_cleanup_supprime_ready_bloque(): void
    {
        $join = $this->q->rejoindre();

        // Forcer STARTED_AT à une valeur ancienne
        self::$pdo->prepare("
            UPDATE FT_QUEUE SET STARTED_AT = NOW() - INTERVAL 91 SECOND WHERE TOKEN = ?
        ")->execute([$join['token']]);

        $this->q->nettoyer();

        $this->assertEquals(0, $this->q->compterParStatut(STATUS_READY));
    }

    /** cleanup() ne supprime pas un READY récent */
    public function test_cleanup_garde_ready_recent(): void
    {
        $this->q->rejoindre();

        $this->q->nettoyer();

        $this->assertEquals(1, $this->q->compterParStatut(STATUS_READY));
    }

    /** cleanup() supprime les WAITING de plus de 10 minutes */
    public function test_cleanup_supprime_waiting_trop_vieux(): void
    {
        $this->q->rejoindre();
        $waiting = $this->q->rejoindre();

        self::$pdo->prepare("
            UPDATE FT_QUEUE SET CREATED_AT = NOW() - INTERVAL 11 MINUTE WHERE TOKEN = ?
        ")->execute([$waiting['token']]);

        $this->q->nettoyer();

        $this->assertNull($this->q->getLigne($waiting['token']));
    }

    // ══════════════════════════════════════════════════════════════════
    // SCÉNARIO COMPLET
    // ══════════════════════════════════════════════════════════════════

    /**
     * A → READY, B → WAITING, C → WAITING
     * done(A) → B devient READY, C reste WAITING
     * done(B) → C devient READY
     * done(C) → file vide (STATUS_DONE × 3, nettoyés par cleanup)
     */
    public function test_scenario_trois_clients_en_sequence(): void
    {
        $a = $this->q->rejoindre();
        $b = $this->q->rejoindre();
        $c = $this->q->rejoindre();

        // État initial
        $this->assertTrue($a['ready']);
        $this->assertFalse($b['ready']);
        $this->assertFalse($c['ready']);

        // A termine → B promu
        $this->q->terminer($a['token']);
        $this->assertEquals(STATUS_DONE,    (int)$this->q->getLigne($a['token'])['ID_STATUS']);
        $this->assertEquals(STATUS_READY,   (int)$this->q->getLigne($b['token'])['ID_STATUS']);
        $this->assertEquals(STATUS_WAITING, (int)$this->q->getLigne($c['token'])['ID_STATUS']);

        // B termine → C promu
        $this->q->terminer($b['token']);
        $this->assertEquals(STATUS_DONE,  (int)$this->q->getLigne($b['token'])['ID_STATUS']);
        $this->assertEquals(STATUS_READY, (int)$this->q->getLigne($c['token'])['ID_STATUS']);

        // C termine → tout en DONE
        $this->q->terminer($c['token']);
        $this->assertEquals(STATUS_DONE, (int)$this->q->getLigne($c['token'])['ID_STATUS']);
        $this->assertEquals(0, $this->q->compterParStatut(STATUS_READY));
        $this->assertEquals(0, $this->q->compterParStatut(STATUS_WAITING));
    }

    /**
     * Scénario fermeture d'onglet :
     * A → READY, B → WAITING
     * B ferme l'onglet (done sur WAITING) → B supprimé, A toujours READY
     * done(A) → file vide
     */
    public function test_scenario_fermeture_onglet_waiting(): void
    {
        $a = $this->q->rejoindre();
        $b = $this->q->rejoindre();

        // B ferme l'onglet
        $this->q->terminer($b['token']);
        $this->assertNull($this->q->getLigne($b['token']));
        $this->assertEquals(STATUS_READY, (int)$this->q->getLigne($a['token'])['ID_STATUS']);

        // A termine normalement
        $this->q->terminer($a['token']);
        $this->assertEquals(STATUS_DONE, (int)$this->q->getLigne($a['token'])['ID_STATUS']);
        $this->assertEquals(0, $this->q->compterParStatut(STATUS_WAITING));
    }
}