<?php

/**
 * RapportHebdoTest.php
 *
 * Teste la logique métier de backend/admin/rapport_hebdo.php :
 *   - filtrage des sites insuffisants
 *   - construction du top dégradés / top bons
 *   - comptage des sites actifs (>= 3 tests)
 *   - calcul des infos semaine ISO
 *   - structure JSON de la réponse finale
 *   - enrichissement des verdicts sur régions et insuffisants
 *
 * Aucune dépendance BDD — toute la logique extraite est testée en isolation.
 * Lancement : C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c tests/phpunit.xml
 */

use PHPUnit\Framework\TestCase;

class RapportHebdoTest extends TestCase
{
    // ── Helpers reproduisant la logique de rapport_hebdo.php ─────────

    /**
     * Filtre les sites insuffisants à partir d'un tableau de sites
     * et d'un tableau de seuils, exactement comme rapport_hebdo.php.
     *
     * @param  array<int, array<string, mixed>> $parSite
     * @param  array<string, array{bon: float, mauvais: float}> $seuilsData
     * @return array<int, array<string, mixed>>
     */
    private function filtrerInsuffisants(array $parSite, array $seuilsData): array
    {
        return array_values(array_filter($parSite, function ($s) use ($seuilsData) {
            $vDl   = $this->verdict('download', (float) $s['moy_download'], $seuilsData);
            $vPing = $this->verdict('ping',     (float) $s['moy_ping'],     $seuilsData);
            return $vDl === 'insuffisant' || $vPing === 'insuffisant';
        }));
    }

    /**
     * Construit le top 5 dégradés (download le plus bas, >= 3 tests),
     * exactement comme rapport_hebdo.php.
     *
     * @param  array<int, array<string, mixed>> $parSite
     * @return array<int, array<string, mixed>>
     */
    private function topDegrades(array $parSite): array
    {
        $sitesAvecTests = array_filter($parSite, fn($s) => (int) $s['nb_tests'] >= 3);
        usort($sitesAvecTests, fn($a, $b) => $a['moy_download'] <=> $b['moy_download']);
        return array_slice(array_values($sitesAvecTests), 0, 5);
    }

    /**
     * Construit le top 5 meilleurs (download le plus haut, >= 3 tests),
     * exactement comme rapport_hebdo.php.
     *
     * @param  array<int, array<string, mixed>> $parSite
     * @return array<int, array<string, mixed>>
     */
    private function topBons(array $parSite): array
    {
        $sitesAvecTests = array_filter($parSite, fn($s) => (int) $s['nb_tests'] >= 3);
        usort($sitesAvecTests, fn($a, $b) => $a['moy_download'] <=> $b['moy_download']);
        return array_slice(array_reverse(array_values($sitesAvecTests)), 0, 5);
    }

    /**
     * Compte les sites actifs (>= 3 tests), exactement comme rapport_hebdo.php.
     *
     * @param  array<int, array<string, mixed>> $parSite
     * @return int
     */
    private function nbSitesActifs(array $parSite): int
    {
        return count(array_filter($parSite, fn($s) => (int) $s['nb_tests'] >= 3));
    }

    /**
     * Calcule les infos de semaine ISO à partir d'une date donnée,
     * exactement comme rapport_hebdo.php.
     *
     * @return array{numero: int, annee: int, debut: string, fin: string, genere: string}
     */
    private function infosSemaine(DateTimeImmutable $now): array
    {
        $debutS = $now->modify('monday this week');
        $finS   = $now->modify('sunday this week');
        return [
            'numero' => (int) $now->format('W'),
            'annee'  => (int) $now->format('o'),
            'debut'  => $debutS->format('d/m/Y'),
            'fin'    => $finS->format('d/m/Y'),
            'genere' => $now->format('d/m/Y à H:i'),
        ];
    }

    /**
     * Enrichit un tableau de régions/sites avec les verdicts,
     * exactement comme rapport_hebdo.php.
     *
     * @param  array<int, array<string, mixed>> $items
     * @param  array<string, array{bon: float, mauvais: float}> $seuilsData
     * @return array<int, array<string, mixed>>
     */
    private function enrichirVerdicts(array $items, array $seuilsData): array
    {
        foreach ($items as &$item) {
            $item['verdict_download'] = $this->verdict('download', (float) $item['moy_download'], $seuilsData);
            $item['verdict_ping']     = $this->verdict('ping',     (float) $item['moy_ping'],     $seuilsData);
            $item['verdict_upload']   = $this->verdict('upload',   (float) $item['moy_upload'],   $seuilsData);
        }
        unset($item);
        return $items;
    }

    /**
     * Recalcule un verdict — reproduit SeuilService::verdict().
     * ping : bon si <= seuil_bon, insuffisant si >= seuil_mauvais
     * download/upload : bon si >= seuil_bon, insuffisant si <= seuil_mauvais
     */
    private function verdict(string $metrique, float $valeur, array $seuils): string
    {
        if (!isset($seuils[$metrique])) return 'inconnu';
        ['bon' => $bon, 'mauvais' => $mauvais] = $seuils[$metrique];
        if ($metrique === 'ping') {
            if ($valeur <= $bon)    return 'confort';
            if ($valeur >= $mauvais) return 'insuffisant';
        } else {
            if ($valeur >= $bon)    return 'confort';
            if ($valeur <= $mauvais) return 'insuffisant';
        }
        return 'fonctionnel';
    }

    // ── Fixtures ──────────────────────────────────────────────────────

    /** Seuils typiques France Travail (dl bon=10, mauvais=3 ; ping bon=50, mauvais=150) */
    private function seuilsTypiques(): array
    {
        return [
            'download' => ['bon' => 10.0, 'mauvais' => 3.0],
            'upload'   => ['bon' => 3.0,  'mauvais' => 0.5],
            'ping'     => ['bon' => 50.0, 'mauvais' => 150.0],
        ];
    }

    /**
     * Génère N sites avec des débits et nb_tests configurables.
     *
     * @return list<array<string, mixed>>
     */
    private function fabriqueSites(array $specs): array
    {
        return array_map(fn($s) => array_merge([
            'CODE_GX_SITE'  => 'GX' . str_pad($s['id'], 6, '0', STR_PAD_LEFT),
            'NOM_SITE'      => 'Site ' . $s['id'],
            'NOM_REGION'    => 'Normandie',
            'moy_download'  => $s['dl'],
            'moy_upload'    => $s['ul'] ?? 2.0,
            'moy_ping'      => $s['ping'] ?? 30.0,
            'nb_tests'      => $s['nb'] ?? 5,
        ], $s), $specs);
    }

    // ══════════════════════════════════════════════════════════════════
    // 1. Filtrage des sites insuffisants
    // ══════════════════════════════════════════════════════════════════

    /** Aucun site insuffisant si tous les débits sont bons */
    public function test_insuffisants_vide_si_tous_bons(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 15.0, 'ping' => 20.0],
            ['id' => 2, 'dl' => 12.0, 'ping' => 35.0],
        ]);
        $result = $this->filtrerInsuffisants($sites, $this->seuilsTypiques());
        $this->assertEmpty($result);
    }

    /** Un site avec download insuffisant est retenu */
    public function test_insuffisants_detecte_download_faible(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 15.0, 'ping' => 20.0],
            ['id' => 2, 'dl' => 2.0,  'ping' => 30.0],  // dl <= mauvais=3
        ]);
        $result = $this->filtrerInsuffisants($sites, $this->seuilsTypiques());
        $this->assertCount(1, $result);
        $this->assertSame('GX000002', $result[0]['CODE_GX_SITE']);
    }

    /** Un site avec ping insuffisant est retenu */
    public function test_insuffisants_detecte_ping_eleve(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 15.0, 'ping' => 20.0],
            ['id' => 2, 'dl' => 8.0,  'ping' => 200.0],  // ping >= mauvais=150
        ]);
        $result = $this->filtrerInsuffisants($sites, $this->seuilsTypiques());
        $this->assertCount(1, $result);
        $this->assertSame('GX000002', $result[0]['CODE_GX_SITE']);
    }

    /** Un site insuffisant sur les deux métriques est compté une seule fois */
    public function test_insuffisants_double_probleme_compte_une_fois(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 2.0, 'ping' => 200.0],
        ]);
        $result = $this->filtrerInsuffisants($sites, $this->seuilsTypiques());
        $this->assertCount(1, $result);
    }

    /** Un site fonctionnel (entre bon et mauvais) n'est pas insuffisant */
    public function test_insuffisants_fonctionnel_non_inclus(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 6.0, 'ping' => 80.0],  // fonctionnel
        ]);
        $result = $this->filtrerInsuffisants($sites, $this->seuilsTypiques());
        $this->assertEmpty($result);
    }

    /** Plusieurs sites insuffisants tous inclus */
    public function test_insuffisants_plusieurs_sites(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 15.0, 'ping' => 20.0],
            ['id' => 2, 'dl' => 2.0,  'ping' => 30.0],
            ['id' => 3, 'dl' => 8.0,  'ping' => 200.0],
            ['id' => 4, 'dl' => 1.5,  'ping' => 180.0],
        ]);
        $result = $this->filtrerInsuffisants($sites, $this->seuilsTypiques());
        $this->assertCount(3, $result);
    }

    /** Le résultat est réindexé (array_values) */
    public function test_insuffisants_resultat_reindexe(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 15.0, 'ping' => 20.0],
            ['id' => 2, 'dl' => 2.0,  'ping' => 30.0],
        ]);
        $result = $this->filtrerInsuffisants($sites, $this->seuilsTypiques());
        $this->assertArrayHasKey(0, $result);
    }

    // ══════════════════════════════════════════════════════════════════
    // 2. Top dégradés
    // ══════════════════════════════════════════════════════════════════

    /** Top dégradés : le site avec le plus faible download est en premier */
    public function test_top_degrades_premier_est_le_plus_faible(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 14.0, 'nb' => 5],
            ['id' => 2, 'dl' => 3.0,  'nb' => 5],
            ['id' => 3, 'dl' => 8.0,  'nb' => 5],
        ]);
        $result = $this->topDegrades($sites);
        $this->assertSame('GX000002', $result[0]['CODE_GX_SITE']);
    }

    /** Top dégradés : plafonné à 5 résultats maximum */
    public function test_top_degrades_plafonne_a_5(): void
    {
        $sites = $this->fabriqueSites(array_map(
            fn($i) => ['id' => $i, 'dl' => (float)$i, 'nb' => 5],
            range(1, 10)
        ));
        $result = $this->topDegrades($sites);
        $this->assertCount(5, $result);
    }

    /** Top dégradés : exclut les sites avec moins de 3 tests */
    public function test_top_degrades_exclut_sites_peu_de_tests(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 1.0, 'nb' => 2],  // exclu (< 3)
            ['id' => 2, 'dl' => 5.0, 'nb' => 3],  // inclus
            ['id' => 3, 'dl' => 8.0, 'nb' => 4],  // inclus
        ]);
        $result = $this->topDegrades($sites);
        $this->assertCount(2, $result);
        $this->assertSame('GX000002', $result[0]['CODE_GX_SITE']);
    }

    /** Top dégradés : exactement 3 tests = inclus (limite >= 3) */
    public function test_top_degrades_exactement_3_tests_inclus(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 5.0, 'nb' => 3],
        ]);
        $result = $this->topDegrades($sites);
        $this->assertCount(1, $result);
    }

    /** Top dégradés : liste vide si aucun site avec >= 3 tests */
    public function test_top_degrades_vide_si_aucun_site_eligible(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 2.0, 'nb' => 1],
            ['id' => 2, 'dl' => 5.0, 'nb' => 2],
        ]);
        $result = $this->topDegrades($sites);
        $this->assertEmpty($result);
    }

    // ══════════════════════════════════════════════════════════════════
    // 3. Top bons
    // ══════════════════════════════════════════════════════════════════

    /** Top bons : le site avec le plus haut download est en premier */
    public function test_top_bons_premier_est_le_plus_eleve(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 14.0, 'nb' => 5],
            ['id' => 2, 'dl' => 3.0,  'nb' => 5],
            ['id' => 3, 'dl' => 20.0, 'nb' => 5],
        ]);
        $result = $this->topBons($sites);
        $this->assertSame('GX000003', $result[0]['CODE_GX_SITE']);
    }

    /** Top bons : plafonné à 5 résultats maximum */
    public function test_top_bons_plafonne_a_5(): void
    {
        $sites = $this->fabriqueSites(array_map(
            fn($i) => ['id' => $i, 'dl' => (float)$i, 'nb' => 5],
            range(1, 10)
        ));
        $result = $this->topBons($sites);
        $this->assertCount(5, $result);
    }

    /** Top bons : exclut les sites avec moins de 3 tests */
    public function test_top_bons_exclut_sites_peu_de_tests(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 20.0, 'nb' => 1],  // exclu
            ['id' => 2, 'dl' => 14.0, 'nb' => 4],  // inclus
        ]);
        $result = $this->topBons($sites);
        $this->assertCount(1, $result);
        $this->assertSame('GX000002', $result[0]['CODE_GX_SITE']);
    }

    /** Top bons et top dégradés ne se recoupent pas sur 5 sites distincts */
    public function test_top_bons_et_degrades_distincts(): void
    {
        $sites = $this->fabriqueSites(array_map(
            fn($i) => ['id' => $i, 'dl' => (float)($i * 2), 'nb' => 5],
            range(1, 10)
        ));
        $bons    = array_column($this->topBons($sites),    'CODE_GX_SITE');
        $degrades = array_column($this->topDegrades($sites), 'CODE_GX_SITE');
        $this->assertEmpty(array_intersect($bons, $degrades));
    }

    // ══════════════════════════════════════════════════════════════════
    // 4. Comptage des sites actifs
    // ══════════════════════════════════════════════════════════════════

    /** Compte uniquement les sites avec >= 3 tests */
    public function test_nb_sites_actifs_seuil_3_tests(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 5.0, 'nb' => 1],
            ['id' => 2, 'dl' => 5.0, 'nb' => 2],
            ['id' => 3, 'dl' => 5.0, 'nb' => 3],
            ['id' => 4, 'dl' => 5.0, 'nb' => 10],
        ]);
        $this->assertSame(2, $this->nbSitesActifs($sites));
    }

    /** 0 si aucun site n'a 3 tests */
    public function test_nb_sites_actifs_zero_si_aucun(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 5.0, 'nb' => 0],
            ['id' => 2, 'dl' => 5.0, 'nb' => 2],
        ]);
        $this->assertSame(0, $this->nbSitesActifs($sites));
    }

    /** Correspond à count(sitesAvecTests), pas count(parSite) */
    public function test_nb_sites_actifs_different_de_total_sites(): void
    {
        $sites = $this->fabriqueSites([
            ['id' => 1, 'dl' => 5.0, 'nb' => 5],
            ['id' => 2, 'dl' => 5.0, 'nb' => 1],  // exclus
            ['id' => 3, 'dl' => 5.0, 'nb' => 4],
        ]);
        $this->assertSame(2, $this->nbSitesActifs($sites));
        $this->assertNotSame(count($sites), $this->nbSitesActifs($sites));
    }

    // ══════════════════════════════════════════════════════════════════
    // 5. Infos semaine ISO
    // ══════════════════════════════════════════════════════════════════

    /** La semaine contient les clés attendues */
    public function test_semaine_contient_les_cles_attendues(): void
    {
        $semaine = $this->infosSemaine(new DateTimeImmutable('2026-05-22'));
        $this->assertArrayHasKey('numero', $semaine);
        $this->assertArrayHasKey('annee',  $semaine);
        $this->assertArrayHasKey('debut',  $semaine);
        $this->assertArrayHasKey('fin',    $semaine);
        $this->assertArrayHasKey('genere', $semaine);
    }

    /** Numéro de semaine est un entier positif entre 1 et 53 */
    public function test_semaine_numero_valide(): void
    {
        $semaine = $this->infosSemaine(new DateTimeImmutable('2026-05-22'));
        $this->assertIsInt($semaine['numero']);
        $this->assertGreaterThanOrEqual(1,  $semaine['numero']);
        $this->assertLessThanOrEqual(53,    $semaine['numero']);
    }

    /** Le 22 mai 2026 (vendredi) est en semaine 21 */
    public function test_semaine_numero_correct_pour_date_connue(): void
    {
        $semaine = $this->infosSemaine(new DateTimeImmutable('2026-05-22'));
        $this->assertSame(21, $semaine['numero']);
    }

    /** Le début de semaine est un lundi */
    public function test_semaine_debut_est_lundi(): void
    {
        $semaine = $this->infosSemaine(new DateTimeImmutable('2026-05-22'));
        $debut = DateTimeImmutable::createFromFormat('d/m/Y', $semaine['debut']);
        $this->assertSame('1', $debut->format('N'));  // N=1 = lundi
    }

    /** La fin de semaine est un dimanche */
    public function test_semaine_fin_est_dimanche(): void
    {
        $semaine = $this->infosSemaine(new DateTimeImmutable('2026-05-22'));
        $fin = DateTimeImmutable::createFromFormat('d/m/Y', $semaine['fin']);
        $this->assertSame('7', $fin->format('N'));  // N=7 = dimanche
    }

    /** Le format de date est jj/mm/aaaa */
    public function test_semaine_format_date_fr(): void
    {
        $semaine = $this->infosSemaine(new DateTimeImmutable('2026-05-22'));
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $semaine['debut']);
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $semaine['fin']);
    }

    /** debut est strictement avant fin */
    public function test_semaine_debut_avant_fin(): void
    {
        $semaine = $this->infosSemaine(new DateTimeImmutable('2026-05-22'));
        $debut = DateTimeImmutable::createFromFormat('d/m/Y', $semaine['debut']);
        $fin   = DateTimeImmutable::createFromFormat('d/m/Y', $semaine['fin']);
        $this->assertLessThan($fin->getTimestamp(), $debut->getTimestamp());
    }

    /** La semaine 21 de 2026 va du 18/05 au 24/05 */
    public function test_semaine_plage_correcte_s21_2026(): void
    {
        $semaine = $this->infosSemaine(new DateTimeImmutable('2026-05-22'));
        $this->assertSame('18/05/2026', $semaine['debut']);
        $this->assertSame('24/05/2026', $semaine['fin']);
    }

    // ══════════════════════════════════════════════════════════════════
    // 6. Enrichissement des verdicts
    // ══════════════════════════════════════════════════════════════════

    /** Les trois clés verdict sont ajoutées */
    public function test_enrichir_ajoute_trois_verdicts(): void
    {
        $items = [['moy_download' => 15.0, 'moy_ping' => 20.0, 'moy_upload' => 4.0]];
        $result = $this->enrichirVerdicts($items, $this->seuilsTypiques());
        $this->assertArrayHasKey('verdict_download', $result[0]);
        $this->assertArrayHasKey('verdict_ping',     $result[0]);
        $this->assertArrayHasKey('verdict_upload',   $result[0]);
    }

    /** Verdict download correct pour un site confort */
    public function test_enrichir_verdict_download_confort(): void
    {
        $items = [['moy_download' => 15.0, 'moy_ping' => 20.0, 'moy_upload' => 4.0]];
        $result = $this->enrichirVerdicts($items, $this->seuilsTypiques());
        $this->assertSame('confort', $result[0]['verdict_download']);
    }

    /** Verdict ping correct pour un site insuffisant */
    public function test_enrichir_verdict_ping_insuffisant(): void
    {
        $items = [['moy_download' => 15.0, 'moy_ping' => 200.0, 'moy_upload' => 4.0]];
        $result = $this->enrichirVerdicts($items, $this->seuilsTypiques());
        $this->assertSame('insuffisant', $result[0]['verdict_ping']);
    }

    /** Tous les items sont enrichis */
    public function test_enrichir_tous_items(): void
    {
        $items = [
            ['moy_download' => 15.0, 'moy_ping' => 20.0,  'moy_upload' => 4.0],
            ['moy_download' => 2.0,  'moy_ping' => 200.0, 'moy_upload' => 0.3],
        ];
        $result = $this->enrichirVerdicts($items, $this->seuilsTypiques());
        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertArrayHasKey('verdict_download', $item);
        }
    }

    // ══════════════════════════════════════════════════════════════════
    // 7. Structure JSON de la réponse
    // ══════════════════════════════════════════════════════════════════

    /** La réponse JSON contient toutes les clés attendues */
    public function test_structure_json_contient_les_cles_attendues(): void
    {
        $response = json_encode([
            'nationale'       => ['moy_download' => 12.5, 'nb_tests' => 100],
            'par_region'      => [],
            'insuffisants'    => [],
            'top_degrades'    => [],
            'top_bons'        => [],
            'nb_sites_actifs' => 5,
            'seuils'          => $this->seuilsTypiques(),
            'semaine'         => ['numero' => 21, 'annee' => 2026, 'debut' => '18/05/2026', 'fin' => '24/05/2026', 'genere' => '22/05/2026 à 10:00'],
        ]);

        $decoded = json_decode($response, true);
        $this->assertIsArray($decoded);

        $clesAttendues = ['nationale', 'par_region', 'insuffisants', 'top_degrades', 'top_bons', 'nb_sites_actifs', 'seuils', 'semaine'];
        foreach ($clesAttendues as $cle) {
            $this->assertArrayHasKey($cle, $decoded, "Clé manquante : $cle");
        }
    }

    /** nb_sites_actifs est un entier */
    public function test_nb_sites_actifs_est_entier_dans_json(): void
    {
        $response = json_encode(['nb_sites_actifs' => 7]);
        $decoded  = json_decode($response, true);
        $this->assertIsInt($decoded['nb_sites_actifs']);
    }

    /** top_degrades et top_bons sont des tableaux */
    public function test_top_sont_des_tableaux(): void
    {
        $response = json_encode(['top_degrades' => [], 'top_bons' => []]);
        $decoded  = json_decode($response, true);
        $this->assertIsArray($decoded['top_degrades']);
        $this->assertIsArray($decoded['top_bons']);
    }

    /** Le JSON est valide (pas d'erreur d'encodage) */
    public function test_json_encode_sans_erreur(): void
    {
        $data = [
            'nationale'       => [],
            'par_region'      => [],
            'insuffisants'    => [],
            'top_degrades'    => [],
            'top_bons'        => [],
            'nb_sites_actifs' => 0,
            'seuils'          => $this->seuilsTypiques(),
            'semaine'         => $this->infosSemaine(new DateTimeImmutable()),
        ];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($json);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }
}
