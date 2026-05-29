<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/services/CacheService.php';

/**
 * Tests unitaires pour CacheService.
 *
 * Utilise un dossier temporaire isolé — aucune dépendance à la base.
 * Chaque test repart d'un cache vide.
 *
 * Lancement : C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c tests/phpunit.xml --filter CacheServiceTest
 */
class CacheTest extends TestCase
{
    private CacheService $cache;
    private string $repCache;

    protected function setUp(): void
    {
        $this->cache    = new CacheService();
        // Récupère le dossier cache/ utilisé par CacheService via réflexion
        $ref            = new ReflectionProperty(CacheService::class, 'repCache');
        $ref->setAccessible(true);
        $this->repCache = $ref->getValue($this->cache);

        // Vider le cache avant chaque test
        $this->cache->vider();
    }

    protected function tearDown(): void
    {
        $this->cache->vider();
    }

    // ══════════════════════════════════════════════════════════════════
    // get() — clé inexistante
    // ══════════════════════════════════════════════════════════════════

    /** Une clé inexistante retourne null */
    public function test_get_cle_inexistante_retourne_null(): void
    {
        $result = $this->cache->obtenir('cle_qui_nexiste_pas');
        $this->assertNull($result);
    }

    // ══════════════════════════════════════════════════════════════════
    // set() + get()
    // ══════════════════════════════════════════════════════════════════

    /** Une valeur stockée est récupérable */
    public function test_set_puis_get_retourne_la_valeur(): void
    {
        $this->cache->stocker('test_key', ['foo' => 'bar']);
        $result = $this->cache->obtenir('test_key');
        $this->assertEquals(['foo' => 'bar'], $result);
    }

    /** Un tableau vide est stocké et récupéré correctement */
    public function test_set_tableau_vide(): void
    {
        $this->cache->stocker('vide', []);
        $result = $this->cache->obtenir('vide');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** Un tableau imbriqué est sérialisé/désérialisé correctement */
    public function test_set_tableau_imbrique(): void
    {
        $data = [
            ['CODE_GX_SITE' => 'DX001', 'moy_download' => 5.2],
            ['CODE_GX_SITE' => 'DX002', 'moy_download' => 3.1],
        ];
        $this->cache->stocker('sites', $data);
        $result = $this->cache->obtenir('sites');
        $this->assertEquals($data, $result);
    }

    /** set() écrase une entrée existante */
    public function test_set_ecrase_valeur_existante(): void
    {
        $this->cache->stocker('key', ['v' => 1]);
        $this->cache->stocker('key', ['v' => 2]);
        $result = $this->cache->obtenir('key');
        $this->assertEquals(['v' => 2], $result);
    }

    // ══════════════════════════════════════════════════════════════════
    // TTL
    // ══════════════════════════════════════════════════════════════════

    /** Une entrée dans le TTL est retournée */
    public function test_get_dans_ttl_retourne_la_valeur(): void
    {
        $this->cache->stocker('ttl_key', ['ok' => true]);
        $result = $this->cache->obtenir('ttl_key', 300);
        $this->assertNotNull($result);
        $this->assertEquals(['ok' => true], $result);
    }

    /** Une entrée expirée retourne null et supprime le fichier */
    public function test_get_ttl_expire_retourne_null(): void
    {
        $this->cache->stocker('expired_key', ['data' => 'old']);

        // Rétrodater le fichier de 10 minutes pour simuler une expiration
        $file = $this->repCache . 'expired_key.json';
        touch($file, time() - 600);

        // TTL = 300 (5 min) → le fichier vieux de 10 min est expiré
        $result = $this->cache->obtenir('expired_key', 300);
        $this->assertNull($result);

        // Le fichier doit avoir été supprimé
        $this->assertFileDoesNotExist($file);
    }

    // ══════════════════════════════════════════════════════════════════
    // invalidate()
    // ══════════════════════════════════════════════════════════════════

    /** invalidate() supprime une entrée existante */
    public function test_invalidate_supprime_entree_existante(): void
    {
        $this->cache->stocker('to_delete', ['x' => 1]);
        $this->cache->invalider('to_delete');
        $result = $this->cache->obtenir('to_delete');
        $this->assertNull($result);
    }

    /** invalidate() sur une clé inexistante ne lève pas d'erreur */
    public function test_invalidate_cle_inexistante_sans_erreur(): void
    {
        $this->expectNotToPerformAssertions();
        $this->cache->invalider('cle_inexistante_xyz');
    }

    // ══════════════════════════════════════════════════════════════════
    // flush()
    // ══════════════════════════════════════════════════════════════════

    /** flush() vide toutes les entrées */
    public function test_flush_vide_tout_le_cache(): void
    {
        $this->cache->stocker('a', ['a' => 1]);
        $this->cache->stocker('b', ['b' => 2]);
        $this->cache->stocker('c', ['c' => 3]);

        $this->cache->vider();

        $this->assertNull($this->cache->obtenir('a'));
        $this->assertNull($this->cache->obtenir('b'));
        $this->assertNull($this->cache->obtenir('c'));
    }

    /** flush() sur un cache vide ne lève pas d'erreur */
    public function test_flush_cache_vide_sans_erreur(): void
    {
        $this->expectNotToPerformAssertions();
        $this->cache->vider();
        $this->cache->vider();
    }

    // ══════════════════════════════════════════════════════════════════
    // Sanitization des clés
    // ══════════════════════════════════════════════════════════════════

    /** Les caractères spéciaux dans la clé sont sanitizés — pas de traversée de chemin */
    public function test_cle_avec_caracteres_speciaux_est_sanitizee(): void
    {
        // Une clé avec "../" ne doit pas créer de fichier hors du dossier cache
        $this->cache->stocker('../hack', ['evil' => true]);
        $result = $this->cache->obtenir('../hack');

        // La valeur doit être stockée/récupérée (la clé est sanitizée, pas rejetée)
        $this->assertEquals(['evil' => true], $result);

        // Vérifier que le fichier est bien dans le dossier cache (pas ailleurs)
        $files = glob($this->repCache . '*.json');
        foreach ($files as $file) {
            $this->assertStringStartsWith(
                realpath($this->repCache),
                realpath($file),
                'Fichier créé hors du dossier cache !'
            );
        }
    }

    /** Deux clés différentes ne se mélangent pas */
    public function test_deux_cles_distinctes_sont_independantes(): void
    {
        $this->cache->stocker('cle_a', ['val' => 'A']);
        $this->cache->stocker('cle_b', ['val' => 'B']);

        $this->assertEquals(['val' => 'A'], $this->cache->obtenir('cle_a'));
        $this->assertEquals(['val' => 'B'], $this->cache->obtenir('cle_b'));
    }

    // ══════════════════════════════════════════════════════════════════
    // Fichier corrompu
    // ══════════════════════════════════════════════════════════════════

    /** Un fichier JSON corrompu retourne null */
    public function test_fichier_corrompu_retourne_null(): void
    {
        $file = $this->repCache . 'corrompu.json';
        file_put_contents($file, 'CECI_NEST_PAS_DU_JSON{{{');

        $result = $this->cache->obtenir('corrompu', 300);
        $this->assertNull($result);
    }
}