<?php

/**
 * SeuilsDerogationTest.php — v1.9.8
 *
 * Teste les nouvelles méthodes de SeuilService :
 *   - chargerPourSite() : fusion seuils globaux + dérogations
 *   - enregistrerDerogation() : UPSERT + suppression si tout null
 *   - supprimerDerogation()
 *   - valeurOuNull() (via enregistrerDerogation)
 *
 * Utilise SQLite en mémoire — aucune dépendance BDD réelle.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/services/SeuilService.php';

class SeuilsDerogationTest extends TestCase
{
    private PDO $pdo;
    private SeuilService $svc;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // ── Schéma minimal ────────────────────────────────────────────
        $this->pdo->exec("
            CREATE TABLE FT_SEUILS (
                NOM_SEUIL       TEXT PRIMARY KEY,
                VALEUR_BONNE    REAL NOT NULL,
                VALEUR_MAUVAISE REAL NOT NULL
            )
        ");
        $this->pdo->exec("
            CREATE TABLE FT_COMPTES (
                ID_COMPTE INTEGER PRIMARY KEY
            )
        ");
        $this->pdo->exec("
            CREATE TABLE FT_SITE (
                CODE_GX_SITE TEXT PRIMARY KEY
            )
        ");
        $this->pdo->exec("
            CREATE TABLE FT_SEUILS_SITE (
                CODE_GX_SITE         TEXT PRIMARY KEY,
                DL_VALEUR_BONNE      REAL,
                DL_VALEUR_MAUVAISE   REAL,
                UL_VALEUR_BONNE      REAL,
                UL_VALEUR_MAUVAISE   REAL,
                PING_VALEUR_BONNE    REAL,
                PING_VALEUR_MAUVAISE REAL,
                RAISON               TEXT,
                DATE_MAJ             TEXT DEFAULT CURRENT_TIMESTAMP,
                MAJ_PAR              INTEGER
            )
        ");

        // ── Seuils globaux ────────────────────────────────────────────
        $this->pdo->exec("
            INSERT INTO FT_SEUILS VALUES
                ('download', 10.0, 3.0),
                ('upload',    3.0, 0.5),
                ('ping',     50.0, 150.0)
        ");

        // ── Sites de test ─────────────────────────────────────────────
        $this->pdo->exec("
            INSERT INTO FT_SITE VALUES ('GX000001'), ('GX000002'), ('GX000003')
        ");

        $this->svc = new SeuilService($this->pdo);
    }

    // ══════════════════════════════════════════════════════════════════
    // charger() — seuils globaux inchangés
    // ══════════════════════════════════════════════════════════════════

    public function test_charger_retourne_trois_metriques(): void
    {
        $s = $this->svc->charger();
        $this->assertArrayHasKey('download', $s);
        $this->assertArrayHasKey('upload',   $s);
        $this->assertArrayHasKey('ping',     $s);
    }

    public function test_charger_valeurs_correctes(): void
    {
        $s = $this->svc->charger();
        $this->assertSame(10.0, $s['download']['bon']);
        $this->assertSame(3.0,  $s['download']['mauvais']);
        $this->assertSame(50.0, $s['ping']['bon']);
        $this->assertSame(150.0,$s['ping']['mauvais']);
    }

    // ══════════════════════════════════════════════════════════════════
    // chargerPourSite() — sans dérogation
    // ══════════════════════════════════════════════════════════════════

    public function test_charger_pour_site_sans_derogation_retourne_globaux(): void
    {
        $s = $this->svc->chargerPourSite('GX000001');
        $this->assertSame($this->svc->charger(), $s);
    }

    public function test_charger_pour_site_inconnu_retourne_globaux(): void
    {
        $s = $this->svc->chargerPourSite('GX999999');
        $this->assertSame($this->svc->charger(), $s);
    }

    // ══════════════════════════════════════════════════════════════════
    // chargerPourSite() — avec dérogation partielle
    // ══════════════════════════════════════════════════════════════════

    public function test_derogation_download_bon_ecrase_global(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, DL_VALEUR_BONNE)
            VALUES ('GX000001', 6.0)
        ");
        $s = $this->svc->chargerPourSite('GX000001');
        $this->assertSame(6.0, $s['download']['bon']);
        // mauvais non touché → global
        $this->assertSame(3.0, $s['download']['mauvais']);
    }

    public function test_derogation_download_mauvais_ecrase_global(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, DL_VALEUR_MAUVAISE)
            VALUES ('GX000001', 1.5)
        ");
        $s = $this->svc->chargerPourSite('GX000001');
        $this->assertSame(1.5,  $s['download']['mauvais']);
        $this->assertSame(10.0, $s['download']['bon']); // global conservé
    }

    public function test_derogation_ping_ecrase_global(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, PING_VALEUR_BONNE, PING_VALEUR_MAUVAISE)
            VALUES ('GX000001', 100.0, 250.0)
        ");
        $s = $this->svc->chargerPourSite('GX000001');
        $this->assertSame(100.0, $s['ping']['bon']);
        $this->assertSame(250.0, $s['ping']['mauvais']);
    }

    public function test_derogation_upload_ecrase_global(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, UL_VALEUR_BONNE, UL_VALEUR_MAUVAISE)
            VALUES ('GX000001', 1.5, 0.2)
        ");
        $s = $this->svc->chargerPourSite('GX000001');
        $this->assertSame(1.5, $s['upload']['bon']);
        $this->assertSame(0.2, $s['upload']['mauvais']);
    }

    public function test_derogation_nulle_conserve_global(): void
    {
        // DL_VALEUR_BONNE = NULL explicitement → seuil global conservé
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, DL_VALEUR_BONNE, DL_VALEUR_MAUVAISE)
            VALUES ('GX000001', NULL, 1.0)
        ");
        $s = $this->svc->chargerPourSite('GX000001');
        $this->assertSame(10.0, $s['download']['bon']);   // NULL → global
        $this->assertSame(1.0,  $s['download']['mauvais']); // non-null → dérogation
    }

    public function test_derogation_autre_site_non_appliquee(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, DL_VALEUR_BONNE)
            VALUES ('GX000001', 4.0)
        ");
        // GX000002 n'a pas de dérogation → globaux
        $s = $this->svc->chargerPourSite('GX000002');
        $this->assertSame(10.0, $s['download']['bon']);
    }

    public function test_derogation_complete_toutes_metriques(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE,
                 DL_VALEUR_BONNE, DL_VALEUR_MAUVAISE,
                 UL_VALEUR_BONNE, UL_VALEUR_MAUVAISE,
                 PING_VALEUR_BONNE, PING_VALEUR_MAUVAISE)
            VALUES ('GX000001', 5.0, 1.5, 1.0, 0.2, 100.0, 300.0)
        ");
        $s = $this->svc->chargerPourSite('GX000001');
        $this->assertSame(5.0,   $s['download']['bon']);
        $this->assertSame(1.5,   $s['download']['mauvais']);
        $this->assertSame(1.0,   $s['upload']['bon']);
        $this->assertSame(0.2,   $s['upload']['mauvais']);
        $this->assertSame(100.0, $s['ping']['bon']);
        $this->assertSame(300.0, $s['ping']['mauvais']);
    }

    // ══════════════════════════════════════════════════════════════════
    // enregistrerDerogation()
    // ══════════════════════════════════════════════════════════════════

    public function test_enregistrer_insere_une_ligne(): void
    {
        $this->svc->enregistrerDerogation('GX000001', [
            'download' => ['bonne' => 5.0, 'mauvaise' => 1.0],
            'upload'   => ['bonne' => null, 'mauvaise' => null],
            'ping'     => ['bonne' => null, 'mauvaise' => null],
        ], null, null);

        $row = $this->pdo
            ->query("SELECT * FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = 'GX000001'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame(5.0, (float) $row['DL_VALEUR_BONNE']);
        $this->assertSame(1.0, (float) $row['DL_VALEUR_MAUVAISE']);
    }

    public function test_enregistrer_met_a_jour_si_existant(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE (CODE_GX_SITE, DL_VALEUR_BONNE)
            VALUES ('GX000001', 5.0)
        ");
        $this->svc->enregistrerDerogation('GX000001', [
            'download' => ['bonne' => 7.0, 'mauvaise' => null],
            'upload'   => ['bonne' => null, 'mauvaise' => null],
            'ping'     => ['bonne' => null, 'mauvaise' => null],
        ], 'Mise à jour', null);

        $row = $this->pdo
            ->query("SELECT DL_VALEUR_BONNE FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = 'GX000001'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(7.0, (float) $row['DL_VALEUR_BONNE']);
    }

    public function test_enregistrer_stocke_raison(): void
    {
        $this->svc->enregistrerDerogation('GX000001', [
            'download' => ['bonne' => 5.0, 'mauvaise' => null],
            'upload'   => ['bonne' => null, 'mauvaise' => null],
            'ping'     => ['bonne' => null, 'mauvaise' => null],
        ], 'Liaison ADSL rurale', null);

        $row = $this->pdo
            ->query("SELECT RAISON FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = 'GX000001'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Liaison ADSL rurale', $row['RAISON']);
    }

    public function test_enregistrer_stocke_id_compte(): void
    {
        $this->pdo->exec("INSERT INTO FT_COMPTES VALUES (42)");
        $this->svc->enregistrerDerogation('GX000001', [
            'download' => ['bonne' => 5.0, 'mauvaise' => null],
            'upload'   => ['bonne' => null, 'mauvaise' => null],
            'ping'     => ['bonne' => null, 'mauvaise' => null],
        ], null, 42);

        $row = $this->pdo
            ->query("SELECT MAJ_PAR FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = 'GX000001'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(42, (int) $row['MAJ_PAR']);
    }

    public function test_enregistrer_tout_null_supprime_ligne(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE (CODE_GX_SITE, DL_VALEUR_BONNE)
            VALUES ('GX000001', 5.0)
        ");
        // Passer toutes les valeurs à null → suppression
        $this->svc->enregistrerDerogation('GX000001', [
            'download' => ['bonne' => null, 'mauvaise' => null],
            'upload'   => ['bonne' => null, 'mauvaise' => null],
            'ping'     => ['bonne' => null, 'mauvaise' => null],
        ], null, null);

        $count = $this->pdo
            ->query("SELECT COUNT(*) FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = 'GX000001'")
            ->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    public function test_enregistrer_valeur_zero_traitee_comme_null(): void
    {
        // valeurOuNull() : 0.0 n'est pas une valeur de seuil valide
        $this->svc->enregistrerDerogation('GX000001', [
            'download' => ['bonne' => 0.0, 'mauvaise' => 0.0],
            'upload'   => ['bonne' => 0.0, 'mauvaise' => 0.0],
            'ping'     => ['bonne' => 0.0, 'mauvaise' => 0.0],
        ], null, null);

        // Tout à 0 → traité comme null → suppression (ou pas d'insertion)
        $count = $this->pdo
            ->query("SELECT COUNT(*) FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = 'GX000001'")
            ->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    // ══════════════════════════════════════════════════════════════════
    // supprimerDerogation()
    // ══════════════════════════════════════════════════════════════════

    public function test_supprimer_enleve_la_ligne(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE (CODE_GX_SITE, DL_VALEUR_BONNE)
            VALUES ('GX000001', 5.0)
        ");
        $this->svc->supprimerDerogation('GX000001');
        $count = $this->pdo
            ->query("SELECT COUNT(*) FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = 'GX000001'")
            ->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    public function test_supprimer_site_sans_derogation_ne_plante_pas(): void
    {
        $this->svc->supprimerDerogation('GX000001'); // aucune ligne — ne doit pas throw
        $this->assertTrue(true);
    }

    public function test_supprimer_un_site_ne_touche_pas_les_autres(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE (CODE_GX_SITE, DL_VALEUR_BONNE) VALUES
                ('GX000001', 5.0),
                ('GX000002', 6.0)
        ");
        $this->svc->supprimerDerogation('GX000001');
        $count = $this->pdo
            ->query("SELECT COUNT(*) FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = 'GX000002'")
            ->fetchColumn();
        $this->assertSame(1, (int) $count);
    }

    // ══════════════════════════════════════════════════════════════════
    // chargerDerogation()
    // ══════════════════════════════════════════════════════════════════

    public function test_charger_derogation_retourne_null_si_absente(): void
    {
        $this->assertNull($this->svc->chargerDerogation('GX000001'));
    }

    public function test_charger_derogation_retourne_la_ligne(): void
    {
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, DL_VALEUR_BONNE, RAISON)
            VALUES ('GX000001', 5.0, 'ADSL')
        ");
        $d = $this->svc->chargerDerogation('GX000001');
        $this->assertNotNull($d);
        $this->assertSame('GX000001', $d['CODE_GX_SITE']);
        $this->assertSame(5.0, (float) $d['DL_VALEUR_BONNE']);
        $this->assertSame('ADSL', $d['RAISON']);
    }

    // ══════════════════════════════════════════════════════════════════
    // Impact sur verdict() — dérogation change le verdict
    // ══════════════════════════════════════════════════════════════════

    public function test_derogation_change_verdict_de_insuffisant_a_fonctionnel(): void
    {
        // Seuils globaux : download mauvais si <= 3 Mbit/s
        // Site a 4 Mbit/s → fonctionnel avec globaux
        // Avec dérogation (mauvais = 5), il passe insuffisant
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, DL_VALEUR_BONNE, DL_VALEUR_MAUVAISE)
            VALUES ('GX000001', 6.0, 5.0)
        ");

        $globaux = $this->svc->charger();
        $derog   = $this->svc->chargerPourSite('GX000001');

        $this->assertSame('fonctionnel',  $this->svc->verdict('download', 4.0, $globaux));
        $this->assertSame('insuffisant',  $this->svc->verdict('download', 4.0, $derog));
    }

    public function test_derogation_change_verdict_de_insuffisant_a_confort(): void
    {
        // Site en zone rurale : download de 4 Mbit/s
        // Avec seuils globaux (bon=10) → fonctionnel
        // Avec dérogation (bon=3) → confort (4 >= 3)
        $this->pdo->exec("
            INSERT INTO FT_SEUILS_SITE
                (CODE_GX_SITE, DL_VALEUR_BONNE, DL_VALEUR_MAUVAISE)
            VALUES ('GX000001', 3.0, 1.0)
        ");

        $globaux = $this->svc->charger();
        $derog   = $this->svc->chargerPourSite('GX000001');

        $this->assertSame('fonctionnel', $this->svc->verdict('download', 4.0, $globaux));
        $this->assertSame('confort',     $this->svc->verdict('download', 4.0, $derog));
    }
}
