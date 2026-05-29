<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/services/AuditService.php';

/**
 * Tests unitaires pour AuditService.
 *
 * Utilise SQLite en mémoire — aucune dépendance à MySQL.
 * Crée FT_AUDIT_SITES et FT_COMPTES (FK) avant chaque test.
 *
 * Lancement :
 *   C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c tests/phpunit.xml --filter AuditServiceTest
 */
class AuditTest extends TestCase
{
    private PDO          $pdo;
    private AuditService $service;

    // ── Fixtures ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Table FT_COMPTES (FK requise par FT_AUDIT_SITES)
        $this->pdo->exec("
            CREATE TABLE FT_COMPTES (
                ID_COMPTE    INTEGER PRIMARY KEY AUTOINCREMENT,
                ALIAS_COMPTE TEXT    NOT NULL,
                MDP_COMPTE   TEXT    NOT NULL,
                IS_ADMIN     INTEGER NOT NULL DEFAULT 0
            )
        ");
        $this->pdo->exec("
            INSERT INTO FT_COMPTES (ALIAS_COMPTE, MDP_COMPTE, IS_ADMIN)
            VALUES ('tech', 'hash', 0), ('admin', 'hash', 1)
        ");

        // Table FT_AUDIT_SITES
        $this->pdo->exec("
            CREATE TABLE FT_AUDIT_SITES (
                ID_AUDIT    INTEGER PRIMARY KEY AUTOINCREMENT,
                ID_COMPTE   INTEGER NOT NULL,
                ACTION      TEXT    NOT NULL,
                ID_SITE     TEXT    NOT NULL,
                NOM_SITE    TEXT    NOT NULL,
                DATE_ACTION TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                IP_ACTION   TEXT    NOT NULL,
                DETAIL      TEXT    NULL,
                FOREIGN KEY (ID_COMPTE) REFERENCES FT_COMPTES(ID_COMPTE)
            )
        ");

        $this->service = new AuditService($this->pdo);
    }

    // ── Helper ────────────────────────────────────────────────────────

    /** Retourne toutes les entrées d'audit */
    private function toutesLesEntrees(): array
    {
        return $this->pdo->query("SELECT * FROM FT_AUDIT_SITES ORDER BY ID_AUDIT")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Retourne la première entrée d'audit */
    private function premiereEntree(): array
    {
        return $this->toutesLesEntrees()[0];
    }

    // ══════════════════════════════════════════════════════════════════
    // AJOUT — enregistrement de base
    // ══════════════════════════════════════════════════════════════════

    /** Un AJOUT insère bien une ligne dans FT_AUDIT_SITES */
    public function test_ajout_insere_une_ligne(): void
    {
        $this->service->enregistrer('AJOUT', 1, 'GX001234', 'Site Test', '10.0.0.1',
            apres: ['NOM_SITE' => 'Site Test', 'IP_RESEAU' => '10.1.0.0']
        );

        $this->assertCount(1, $this->toutesLesEntrees());
    }

    /** Un AJOUT stocke les bons champs métier */
    public function test_ajout_stocke_champs_corrects(): void
    {
        $this->service->enregistrer('AJOUT', 1, 'GX001234', 'Site Test', '10.0.0.1',
            apres: ['NOM_SITE' => 'Site Test']
        );

        $e = $this->premiereEntree();
        $this->assertSame('AJOUT',      $e['ACTION']);
        $this->assertEquals(1, $e['ID_COMPTE']);
        $this->assertSame('GX001234',   $e['ID_SITE']);
        $this->assertSame('Site Test',  $e['NOM_SITE']);
        $this->assertSame('10.0.0.1',   $e['IP_ACTION']);
    }

    /** Un AJOUT encode le détail JSON avec la clé 'ajout' */
    public function test_ajout_detail_json_contient_cle_ajout(): void
    {
        $apres = ['NOM_SITE' => 'Site Test', 'IP_RESEAU' => '10.1.0.0', 'MASQUE_SITE' => 24];
        $this->service->enregistrer('AJOUT', 1, 'GX001234', 'Site Test', '10.0.0.1', apres: $apres);

        $detail = json_decode($this->premiereEntree()['DETAIL'], true);
        $this->assertArrayHasKey('ajout', $detail);
        $this->assertSame('Site Test', $detail['ajout']['NOM_SITE']);
    }

    /** Un AJOUT sans $apres stocke DETAIL = NULL */
    public function test_ajout_sans_apres_detail_null(): void
    {
        $this->service->enregistrer('AJOUT', 1, 'GX001234', 'Site Test', '10.0.0.1');

        $this->assertNull($this->premiereEntree()['DETAIL']);
    }

    // ══════════════════════════════════════════════════════════════════
    // SUPPRESSION — enregistrement de base
    // ══════════════════════════════════════════════════════════════════

    /** Une SUPPRESSION insère bien une ligne */
    public function test_suppression_insere_une_ligne(): void
    {
        $this->service->enregistrer('SUPPRESSION', 2, 'GX005678', 'Vieux Site', '10.0.0.2',
            avant: ['NOM_SITE' => 'Vieux Site']
        );

        $this->assertCount(1, $this->toutesLesEntrees());
        $this->assertSame('SUPPRESSION', $this->premiereEntree()['ACTION']);
    }

    /** Une SUPPRESSION encode le détail JSON avec la clé 'supprime' */
    public function test_suppression_detail_json_contient_cle_supprime(): void
    {
        $avant = ['NOM_SITE' => 'Vieux Site', 'IP_RESEAU' => '10.2.0.0'];
        $this->service->enregistrer('SUPPRESSION', 2, 'GX005678', 'Vieux Site', '10.0.0.2', avant: $avant);

        $detail = json_decode($this->premiereEntree()['DETAIL'], true);
        $this->assertArrayHasKey('supprime', $detail);
        $this->assertSame('Vieux Site', $detail['supprime']['NOM_SITE']);
    }

    /** Une SUPPRESSION sans $avant stocke DETAIL = NULL */
    public function test_suppression_sans_avant_detail_null(): void
    {
        $this->service->enregistrer('SUPPRESSION', 2, 'GX005678', 'Vieux Site', '10.0.0.2');

        $this->assertNull($this->premiereEntree()['DETAIL']);
    }

    // ══════════════════════════════════════════════════════════════════
    // MODIFICATION — diff avant/après
    // ══════════════════════════════════════════════════════════════════

    /** Une MODIFICATION avec des champs différents stocke un diff non null */
    public function test_modification_diff_non_null_si_changements(): void
    {
        $avant = ['NOM_SITE' => 'Ancien', 'IP_RESEAU' => '10.0.0.0', 'MASQUE_SITE' => 24];
        $apres = ['NOM_SITE' => 'Nouveau', 'IP_RESEAU' => '10.0.0.0', 'MASQUE_SITE' => 24];

        $this->service->enregistrer('MODIFICATION', 1, 'GX001234', 'Nouveau', '10.0.0.1',
            avant: $avant, apres: $apres
        );

        $this->assertNotNull($this->premiereEntree()['DETAIL']);
    }

    /** Le diff ne contient que les champs réellement modifiés */
    public function test_modification_diff_ne_contient_que_champs_changes(): void
    {
        $avant = ['NOM_SITE' => 'Ancien', 'IP_RESEAU' => '10.0.0.0', 'MASQUE_SITE' => 24];
        $apres = ['NOM_SITE' => 'Nouveau', 'IP_RESEAU' => '10.0.0.0', 'MASQUE_SITE' => 24];

        $this->service->enregistrer('MODIFICATION', 1, 'GX001234', 'Nouveau', '10.0.0.1',
            avant: $avant, apres: $apres
        );

        $detail = json_decode($this->premiereEntree()['DETAIL'], true);
        $this->assertArrayHasKey('NOM_SITE',     $detail);
        $this->assertArrayNotHasKey('IP_RESEAU',  $detail);
        $this->assertArrayNotHasKey('MASQUE_SITE', $detail);
    }

    /** Le diff stocke les valeurs avant et après pour chaque champ modifié */
    public function test_modification_diff_stocke_avant_et_apres(): void
    {
        $avant = ['NOM_SITE' => 'Ancien', 'MASQUE_SITE' => 24];
        $apres = ['NOM_SITE' => 'Nouveau', 'MASQUE_SITE' => 16];

        $this->service->enregistrer('MODIFICATION', 1, 'GX001234', 'Nouveau', '10.0.0.1',
            avant: $avant, apres: $apres
        );

        $detail = json_decode($this->premiereEntree()['DETAIL'], true);
        $this->assertSame('Ancien',  $detail['NOM_SITE']['avant']);
        $this->assertSame('Nouveau', $detail['NOM_SITE']['apres']);
        $this->assertSame(24,        $detail['MASQUE_SITE']['avant']);
        $this->assertSame(16,        $detail['MASQUE_SITE']['apres']);
    }

    /** Aucun changement → DETAIL = NULL (diff vide non stocké) */
    public function test_modification_sans_changement_detail_null(): void
    {
        $etat = ['NOM_SITE' => 'Identique', 'IP_RESEAU' => '10.0.0.0'];

        $this->service->enregistrer('MODIFICATION', 1, 'GX001234', 'Identique', '10.0.0.1',
            avant: $etat, apres: $etat
        );

        $this->assertNull($this->premiereEntree()['DETAIL']);
    }

    /** Les floats avec zéros trailing ne génèrent pas de faux diff (47.2365 = 47.236500) */
    public function test_modification_float_trailing_zeros_pas_de_faux_diff(): void
    {
        $avant = ['LATITUDE' => '47.236500'];
        $apres = ['LATITUDE' => '47.2365'];

        $this->service->enregistrer('MODIFICATION', 1, 'GX001234', 'Site', '10.0.0.1',
            avant: $avant, apres: $apres
        );

        // Aucune différence réelle → DETAIL NULL
        $this->assertNull($this->premiereEntree()['DETAIL']);
    }

    /** Une MODIFICATION sans $avant ni $apres stocke DETAIL = NULL */
    public function test_modification_sans_avant_apres_detail_null(): void
    {
        $this->service->enregistrer('MODIFICATION', 1, 'GX001234', 'Site', '10.0.0.1');

        $this->assertNull($this->premiereEntree()['DETAIL']);
    }

    // ══════════════════════════════════════════════════════════════════
    // Appels multiples
    // ══════════════════════════════════════════════════════════════════

    /** Plusieurs appels successifs créent plusieurs lignes indépendantes */
    public function test_plusieurs_appels_creent_plusieurs_lignes(): void
    {
        $this->service->enregistrer('AJOUT',        1, 'GX001', 'Site 1', '10.0.0.1');
        $this->service->enregistrer('MODIFICATION', 1, 'GX001', 'Site 1', '10.0.0.1');
        $this->service->enregistrer('SUPPRESSION',  2, 'GX001', 'Site 1', '10.0.0.2');

        $this->assertCount(3, $this->toutesLesEntrees());
    }

    /** L'ordre des actions est préservé dans FT_AUDIT_SITES */
    public function test_ordre_actions_preserve(): void
    {
        $this->service->enregistrer('AJOUT',        1, 'GX001', 'Site', '10.0.0.1');
        $this->service->enregistrer('MODIFICATION', 1, 'GX001', 'Site', '10.0.0.1');
        $this->service->enregistrer('SUPPRESSION',  1, 'GX001', 'Site', '10.0.0.1');

        $entrees = $this->toutesLesEntrees();
        $this->assertSame('AJOUT',        $entrees[0]['ACTION']);
        $this->assertSame('MODIFICATION', $entrees[1]['ACTION']);
        $this->assertSame('SUPPRESSION',  $entrees[2]['ACTION']);
    }

    // ══════════════════════════════════════════════════════════════════
    // Cas limites
    // ══════════════════════════════════════════════════════════════════

    /** Le CODE_GX_SITE est bien stocké en VARCHAR (pas converti en int) */
    public function test_code_gx_stocke_en_varchar(): void
    {
        $this->service->enregistrer('AJOUT', 1, 'GX006543', 'Site', '10.0.0.1');

        $this->assertSame('GX006543', $this->premiereEntree()['ID_SITE']);
    }

    /** L'IP action est stockée telle quelle */
    public function test_ip_action_stockee_correctement(): void
    {
        $this->service->enregistrer('AJOUT', 1, 'GX001234', 'Site', '192.168.1.42');

        $this->assertSame('192.168.1.42', $this->premiereEntree()['IP_ACTION']);
    }

    /** L'ID compte est bien stocké */
    public function test_id_compte_stocke_correctement(): void
    {
        $this->service->enregistrer('AJOUT', 2, 'GX001234', 'Site', '10.0.0.1');

        $this->assertEquals(2, $this->premiereEntree()['ID_COMPTE']);
    }

    /** Le NOM_SITE avec caractères spéciaux est stocké correctement */
    public function test_nom_site_avec_caracteres_speciaux(): void
    {
        $nom = "Site d'Île-de-France & Cie";
        $this->service->enregistrer('AJOUT', 1, 'GX001234', $nom, '10.0.0.1');

        $this->assertSame($nom, $this->premiereEntree()['NOM_SITE']);
    }

    /** Un diff avec valeur null avant est correctement encodé */
    public function test_modification_diff_avec_valeur_null_avant(): void
    {
        $avant = ['IP_RESEAU' => null,       'MASQUE_SITE' => null];
        $apres = ['IP_RESEAU' => '10.0.0.0', 'MASQUE_SITE' => 24];

        $this->service->enregistrer('MODIFICATION', 1, 'GX001234', 'Site', '10.0.0.1',
            avant: $avant, apres: $apres
        );

        $detail = json_decode($this->premiereEntree()['DETAIL'], true);
        $this->assertArrayHasKey('IP_RESEAU',   $detail);
        $this->assertArrayHasKey('MASQUE_SITE', $detail);
        $this->assertNull($detail['IP_RESEAU']['avant']);
        $this->assertSame('10.0.0.0', $detail['IP_RESEAU']['apres']);
    }
}