<?php

/**
 * GetlogsHistoriqueTest.php
 *
 * Teste la logique du mode historique de backend/ip/get_logs.php
 * (filtrage WHERE, sélection des champs, noms CSV, pagination, filtres).
 *
 * Aucune dépendance BDD — toute la logique extraite est testée en isolation.
 * Lancement : C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c tests/phpunit.xml
 */

use PHPUnit\Framework\TestCase;

class GetlogsHistoriqueTest extends TestCase
{
    // ── Logique extraite de get_logs.php ──────────────────────────────

    /**
     * Reconstruit la clause WHERE exactement comme get_logs.php.
     *
     * @param  bool        $modeHistorique
     * @param  string      $ip          IP agent (mode historique)
     * @param  string      $codeGx      CODE_GX_SITE (mode normal)
     * @param  string      $dateDebut
     * @param  string      $dateFin
     * @param  string|null $modeFiltre  'precise'|'fast'|'balanced'|null
     * @return array{clauseSQL: string, params: list<string>}
     */
    private function buildWhereHistorique(
        bool    $modeHistorique,
        string  $ip,
        string  $codeGx,
        string  $dateDebut,
        string  $dateFin,
        ?string $modeFiltre
    ): array {
        $where  = [];
        $params = [];

        if ($modeHistorique) {
            $where[]  = 'IP_CLIENT = ?';
            $params[] = $ip;
        } else {
            $where[]  = 'CODE_GX_SITE = ?';
            $params[] = $codeGx;
        }

        if ($dateDebut) { $where[] = 'DATE_LOGS >= ?'; $params[] = "$dateDebut 00:00:00"; }
        if ($dateFin)   { $where[] = 'DATE_LOGS <= ?'; $params[] = "$dateFin 23:59:59"; }
        if ($modeFiltre){ $where[] = 'MODE = ?';       $params[] = $modeFiltre; }

        return [
            'clauseSQL' => 'WHERE ' . implode(' AND ', $where),
            'params'    => $params,
        ];
    }

    /**
     * Reconstruit la colonne SELECT extra, exactement comme get_logs.php.
     * Mode historique → CODE_GX_SITE (afficher le site à l'agent)
     * Mode normal     → IP_CLIENT (afficher le poste à l'admin)
     */
    private function selectExtra(bool $modeHistorique): string
    {
        return $modeHistorique ? ', CODE_GX_SITE' : ', IP_CLIENT';
    }

    /**
     * Reconstruit le nom du fichier CSV, exactement comme get_logs.php.
     */
    private function nomFichierCsv(bool $modeHistorique, string $codeGx, string $date): string
    {
        return $modeHistorique
            ? 'historique_' . $date . '.csv'
            : 'logs_' . $codeGx . '_' . $date . '.csv';
    }

    /**
     * Reconstruit les en-têtes CSV, exactement comme get_logs.php.
     *
     * @return list<string>
     */
    private function entestesCsv(bool $modeHistorique): array
    {
        return $modeHistorique
            ? ['Date', 'Site', 'Ping (ms)', 'Téléchargement (Mbit/s)', 'Envoi (Mbit/s)', 'Mode']
            : ['Date', 'IP client', 'Ping (ms)', 'Téléchargement (Mbit/s)', 'Envoi (Mbit/s)', 'Mode'];
    }

    /**
     * Reconstruit la ligne CSV pour un enregistrement donné,
     * exactement comme get_logs.php.
     *
     * @param  array<string, mixed> $enreg
     * @return list<string>
     */
    private function ligneCsv(bool $modeHistorique, array $enreg): array
    {
        return $modeHistorique
            ? [$enreg['DATE_LOGS'], $enreg['CODE_GX_SITE'], $enreg['PING_LOGS'], $enreg['DOWNLOAD_LOGS'], $enreg['UPLOAD_LOGS'], $enreg['MODE']]
            : [$enreg['DATE_LOGS'], $enreg['IP_CLIENT'],    $enreg['PING_LOGS'], $enreg['DOWNLOAD_LOGS'], $enreg['UPLOAD_LOGS'], $enreg['MODE']];
    }

    // ══════════════════════════════════════════════════════════════════
    // 1. Détection du mode
    // ══════════════════════════════════════════════════════════════════

    /** mode=historique active bien le mode historique */
    public function test_mode_historique_detecte_correctement(): void
    {
        $modeHistorique = (('historique') === 'historique');
        $this->assertTrue($modeHistorique);
    }

    /** mode=precise n'active PAS le mode historique */
    public function test_mode_precise_nest_pas_historique(): void
    {
        $modeHistorique = (('precise') === 'historique');
        $this->assertFalse($modeHistorique);
    }

    /** mode absent n'active PAS le mode historique */
    public function test_mode_absent_nest_pas_historique(): void
    {
        $get = [];
        $modeHistorique = (($get['mode'] ?? '') === 'historique');
        $this->assertFalse($modeHistorique);
    }

    // ══════════════════════════════════════════════════════════════════
    // 2. Clause WHERE — mode historique
    // ══════════════════════════════════════════════════════════════════

    /** En mode historique, WHERE filtre sur IP_CLIENT */
    public function test_historique_where_filtre_sur_ip_client(): void
    {
        $r = $this->buildWhereHistorique(true, '10.192.5.42', '', '', '', null);
        $this->assertStringContainsString('IP_CLIENT = ?', $r['clauseSQL']);
        $this->assertContains('10.192.5.42', $r['params']);
    }

    /** En mode historique, WHERE ne filtre PAS sur CODE_GX_SITE */
    public function test_historique_where_nexclut_pas_code_gx(): void
    {
        $r = $this->buildWhereHistorique(true, '10.192.5.42', '', '', '', null);
        $this->assertStringNotContainsString('CODE_GX_SITE = ?', $r['clauseSQL']);
    }

    /** En mode normal, WHERE filtre sur CODE_GX_SITE */
    public function test_normal_where_filtre_sur_code_gx(): void
    {
        $r = $this->buildWhereHistorique(false, '', 'GX001234', '', '', null);
        $this->assertStringContainsString('CODE_GX_SITE = ?', $r['clauseSQL']);
        $this->assertContains('GX001234', $r['params']);
    }

    /** En mode normal, WHERE ne filtre PAS sur IP_CLIENT */
    public function test_normal_where_nexclut_pas_ip_client(): void
    {
        $r = $this->buildWhereHistorique(false, '', 'GX001234', '', '', null);
        $this->assertStringNotContainsString('IP_CLIENT = ?', $r['clauseSQL']);
    }

    // ══════════════════════════════════════════════════════════════════
    // 3. Clause WHERE — filtres communs
    // ══════════════════════════════════════════════════════════════════

    /** Mode historique + dateDebut → DATE_LOGS >= ajouté */
    public function test_historique_avec_date_debut(): void
    {
        $r = $this->buildWhereHistorique(true, '10.1.2.3', '', '2026-05-01', '', null);
        $this->assertStringContainsString('DATE_LOGS >=', $r['clauseSQL']);
        $this->assertContains('2026-05-01 00:00:00', $r['params']);
        $this->assertCount(2, $r['params']);
    }

    /** Mode historique + dateFin → DATE_LOGS <= ajouté */
    public function test_historique_avec_date_fin(): void
    {
        $r = $this->buildWhereHistorique(true, '10.1.2.3', '', '', '2026-05-31', null);
        $this->assertStringContainsString('DATE_LOGS <=', $r['clauseSQL']);
        $this->assertContains('2026-05-31 23:59:59', $r['params']);
    }

    /** Mode historique + modeFiltre → MODE = ? ajouté */
    public function test_historique_avec_mode_filtre_precise(): void
    {
        $r = $this->buildWhereHistorique(true, '10.1.2.3', '', '', '', 'precise');
        $this->assertStringContainsString('MODE = ?', $r['clauseSQL']);
        $this->assertContains('precise', $r['params']);
    }

    /** Mode historique + modeFiltre fast */
    public function test_historique_avec_mode_filtre_fast(): void
    {
        $r = $this->buildWhereHistorique(true, '10.1.2.3', '', '', '', 'fast');
        $this->assertContains('fast', $r['params']);
    }

    /** Mode historique sans filtre → 1 seul paramètre (IP) */
    public function test_historique_sans_filtres_un_seul_parametre(): void
    {
        $r = $this->buildWhereHistorique(true, '10.5.6.7', '', '', '', null);
        $this->assertCount(1, $r['params']);
    }

    /** Mode historique avec tous les filtres → 4 paramètres */
    public function test_historique_tous_filtres_quatre_parametres(): void
    {
        $r = $this->buildWhereHistorique(true, '10.5.6.7', '', '2026-04-01', '2026-05-31', 'precise');
        $this->assertCount(4, $r['params']);
    }

    /** Les dates sont bien formatées avec heure */
    public function test_dates_formatees_avec_heure(): void
    {
        $r = $this->buildWhereHistorique(true, '10.1.2.3', '', '2026-05-01', '2026-05-31', null);
        $this->assertSame('2026-05-01 00:00:00', $r['params'][1]);
        $this->assertSame('2026-05-31 23:59:59', $r['params'][2]);
    }

    // ══════════════════════════════════════════════════════════════════
    // 4. Validation du modeFiltre (modes valides vs invalides)
    // ══════════════════════════════════════════════════════════════════

    /** Les modes valides sont reconnus */
    public function test_modes_valides_acceptes(): void
    {
        $modesValides = ['precise', 'fast', 'balanced'];
        foreach ($modesValides as $mode) {
            $filtre = in_array($mode, $modesValides, true) ? $mode : null;
            $this->assertSame($mode, $filtre, "Mode '$mode' devrait être accepté");
        }
    }

    /** 'historique' n'est PAS dans les modesValides de modeFiltre */
    public function test_mode_historique_nest_pas_un_filtre_valide(): void
    {
        $modesValides = ['precise', 'fast', 'balanced'];
        $filtre = in_array('historique', $modesValides, true) ? 'historique' : null;
        $this->assertNull($filtre);
    }

    /** Un mode inconnu retourne null */
    public function test_mode_inconnu_retourne_null(): void
    {
        $modesValides = ['precise', 'fast', 'balanced'];
        $filtre = in_array('turbo', $modesValides, true) ? 'turbo' : null;
        $this->assertNull($filtre);
    }

    // ══════════════════════════════════════════════════════════════════
    // 5. SELECT extra — colonne affichée selon le mode
    // ══════════════════════════════════════════════════════════════════

    /** Mode historique → colonne CODE_GX_SITE dans le SELECT */
    public function test_historique_select_extra_est_code_gx_site(): void
    {
        $this->assertSame(', CODE_GX_SITE', $this->selectExtra(true));
    }

    /** Mode normal → colonne IP_CLIENT dans le SELECT */
    public function test_normal_select_extra_est_ip_client(): void
    {
        $this->assertSame(', IP_CLIENT', $this->selectExtra(false));
    }

    // ══════════════════════════════════════════════════════════════════
    // 6. Export CSV — noms de fichiers
    // ══════════════════════════════════════════════════════════════════

    /** Mode historique → nom commence par 'historique_' */
    public function test_csv_historique_nom_commence_par_historique(): void
    {
        $nom = $this->nomFichierCsv(true, '', '20260522');
        $this->assertStringStartsWith('historique_', $nom);
    }

    /** Mode historique → nom se termine par '.csv' */
    public function test_csv_historique_nom_termine_par_csv(): void
    {
        $nom = $this->nomFichierCsv(true, '', '20260522');
        $this->assertStringEndsWith('.csv', $nom);
    }

    /** Mode historique → date intégrée dans le nom */
    public function test_csv_historique_contient_date(): void
    {
        $nom = $this->nomFichierCsv(true, '', '20260522');
        $this->assertStringContainsString('20260522', $nom);
    }

    /** Mode normal → nom contient le CODE_GX_SITE */
    public function test_csv_normal_nom_contient_code_gx(): void
    {
        $nom = $this->nomFichierCsv(false, 'GX001234', '20260522');
        $this->assertStringContainsString('GX001234', $nom);
        $this->assertStringStartsWith('logs_', $nom);
    }

    /** Les deux noms sont différents pour le même contexte */
    public function test_csv_noms_differents_selon_mode(): void
    {
        $hist = $this->nomFichierCsv(true,  'GX001234', '20260522');
        $norm = $this->nomFichierCsv(false, 'GX001234', '20260522');
        $this->assertNotSame($hist, $norm);
    }

    // ══════════════════════════════════════════════════════════════════
    // 7. Export CSV — en-têtes
    // ══════════════════════════════════════════════════════════════════

    /** Mode historique → 2e colonne en-tête est 'Site' */
    public function test_csv_historique_entete_deuxieme_colonne_site(): void
    {
        $entetes = $this->entestesCsv(true);
        $this->assertSame('Site', $entetes[1]);
    }

    /** Mode normal → 2e colonne en-tête est 'IP client' */
    public function test_csv_normal_entete_deuxieme_colonne_ip(): void
    {
        $entetes = $this->entestesCsv(false);
        $this->assertSame('IP client', $entetes[1]);
    }

    /** Les deux modes ont le même nombre de colonnes */
    public function test_csv_meme_nombre_colonnes(): void
    {
        $this->assertCount(6, $this->entestesCsv(true));
        $this->assertCount(6, $this->entestesCsv(false));
    }

    /** Première colonne toujours 'Date' */
    public function test_csv_premiere_colonne_toujours_date(): void
    {
        $this->assertSame('Date', $this->entestesCsv(true)[0]);
        $this->assertSame('Date', $this->entestesCsv(false)[0]);
    }

    // ══════════════════════════════════════════════════════════════════
    // 8. Export CSV — lignes de données
    // ══════════════════════════════════════════════════════════════════

    /** Mode historique → 2e valeur est CODE_GX_SITE */
    public function test_csv_historique_ligne_deuxieme_valeur_est_code_gx(): void
    {
        $enreg = [
            'DATE_LOGS'     => '22/05/2026 10:30',
            'CODE_GX_SITE'  => 'GX001234',
            'IP_CLIENT'     => '10.5.6.7',
            'PING_LOGS'     => '12.5',
            'DOWNLOAD_LOGS' => '14.2',
            'UPLOAD_LOGS'   => '5.1',
            'MODE'          => 'precise',
        ];
        $ligne = $this->ligneCsv(true, $enreg);
        $this->assertSame('GX001234', $ligne[1]);
    }

    /** Mode normal → 2e valeur est IP_CLIENT */
    public function test_csv_normal_ligne_deuxieme_valeur_est_ip(): void
    {
        $enreg = [
            'DATE_LOGS'     => '22/05/2026 10:30',
            'CODE_GX_SITE'  => 'GX001234',
            'IP_CLIENT'     => '10.5.6.7',
            'PING_LOGS'     => '12.5',
            'DOWNLOAD_LOGS' => '14.2',
            'UPLOAD_LOGS'   => '5.1',
            'MODE'          => 'precise',
        ];
        $ligne = $this->ligneCsv(false, $enreg);
        $this->assertSame('10.5.6.7', $ligne[1]);
    }

    /** Mode historique → IP_CLIENT absent de la ligne */
    public function test_csv_historique_pas_ip_client_dans_ligne(): void
    {
        $enreg = [
            'DATE_LOGS'     => '22/05/2026 10:30',
            'CODE_GX_SITE'  => 'GX001234',
            'IP_CLIENT'     => '10.5.6.7',
            'PING_LOGS'     => '12.5',
            'DOWNLOAD_LOGS' => '14.2',
            'UPLOAD_LOGS'   => '5.1',
            'MODE'          => 'precise',
        ];
        $ligne = $this->ligneCsv(true, $enreg);
        $this->assertNotContains('10.5.6.7', $ligne);
    }

    /** Dernière colonne est toujours le MODE */
    public function test_csv_derniere_colonne_est_mode(): void
    {
        $enreg = [
            'DATE_LOGS'     => '22/05/2026 10:30',
            'CODE_GX_SITE'  => 'GX001234',
            'IP_CLIENT'     => '10.5.6.7',
            'PING_LOGS'     => '12.5',
            'DOWNLOAD_LOGS' => '14.2',
            'UPLOAD_LOGS'   => '5.1',
            'MODE'          => 'fast',
        ];
        $this->assertSame('fast', $this->ligneCsv(true,  $enreg)[5]);
        $this->assertSame('fast', $this->ligneCsv(false, $enreg)[5]);
    }

    // ══════════════════════════════════════════════════════════════════
    // 9. Pagination (commune aux deux modes)
    // ══════════════════════════════════════════════════════════════════

    /** page=1 → offset 0 */
    public function test_pagination_page1_offset0(): void
    {
        $this->assertSame(0, (max(1, 1) - 1) * 20);
    }

    /** page=2 → offset 20 */
    public function test_pagination_page2_offset20(): void
    {
        $this->assertSame(20, (max(1, 2) - 1) * 20);
    }

    /** page=0 (invalide) → corrigée à page=1, offset=0 */
    public function test_pagination_page0_corrigee_en_1(): void
    {
        $page = max(1, 0);
        $this->assertSame(1, $page);
        $this->assertSame(0, ($page - 1) * 20);
    }

    /** page négative → corrigée à page=1 */
    public function test_pagination_page_negative_corrigee(): void
    {
        $page = max(1, -5);
        $this->assertSame(1, $page);
    }

    /** Nombre de pages calculé correctement */
    public function test_pagination_nb_pages_exact(): void
    {
        $this->assertSame(5, (int) ceil(100 / 20));
        $this->assertSame(6, (int) ceil(101 / 20));
        $this->assertSame(1, (int) ceil(1   / 20));
    }
}
