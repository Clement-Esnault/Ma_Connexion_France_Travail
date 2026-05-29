<?php

if (class_exists('SiteResolverService')) return;

require_once __DIR__ . '/../ip/getIP_util.php';

/**
 * SiteResolverService
 *
 * Résout le site France Travail correspondant à une adresse IP cliente
 * par comparaison CIDR avec les plages réseau déclarées dans FT_SITE.
 *
 * Centralise la logique partagée entre :
 *   - backend/ip/save_result.php (résolution CODE_GX_SITE)
 *   - backend/ip/getIP.php       (résolution NOM_SITE)
 *
 * Usage :
 *   $resolver = new SiteResolverService($pdo, $cache);
 *   $code = $resolver->trouverCode('10.30.194.5');   // 'GX001' | null
 *   $nom  = $resolver->trouverNom('10.30.194.5');    // 'Caen SIR' | null
 */
class SiteResolverService
{
    private const TTL_CACHE = 300; // 5 minutes
    private const CLE_CACHE = 'sites_cidr';

    public function __construct(
        private PDO          $pdo,
        private CacheService $cache,
    ) {}

    /**
     * Retourne le CODE_GX_SITE correspondant à l'IP, ou null si introuvable.
     *
     * @param string $ip  Adresse IPv4 du client
     * @return string|null
     */
    public function trouverCode(string $ip): ?string
    {
        return $this->chercher($ip, 'CODE_GX_SITE');
    }

    /**
     * Retourne le NOM_SITE correspondant à l'IP, ou null si introuvable.
     *
     * @param string $ip  Adresse IPv4 du client
     * @return string|null
     */
    public function trouverNom(string $ip): ?string
    {
        return $this->chercher($ip, 'NOM_SITE');
    }

    /**
     * Recherche un site par IP dans la liste CIDR mise en cache.
     * @param string $ip     Adresse IP à résoudre
     * @param string $champ  Colonne à retourner ('CODE_GX_SITE' | 'NOM_SITE')
     */
    private function chercher(string $ip, string $champ): ?string
    {
        foreach ($this->chargerSites() as $site) {
            if (ipDansReseau($ip, $site['IP_RESEAU'], $site['MASQUE_SITE'])) {
                return $site[$champ] ?? null;
            }
        }
        return null;
    }

    /**
     * Charge la liste des sites depuis le cache ou la BDD.
     * @return array<int, array{CODE_GX_SITE: string, NOM_SITE: string, IP_RESEAU: string, MASQUE_SITE: int}>
     */
    private ?array $sitesEnMemoire = null;

private function chargerSites(): array
{
    if ($this->sitesEnMemoire !== null) {
        return $this->sitesEnMemoire;
    }
    $sites = $this->cache->obtenir(self::CLE_CACHE, self::TTL_CACHE);
    if ($sites === null) {
        $sites = $this->pdo
            ->query('SELECT CODE_GX_SITE, NOM_SITE, IP_RESEAU, MASQUE_SITE
                     FROM FT_SITE WHERE IP_RESEAU IS NOT NULL')
            ->fetchAll(PDO::FETCH_ASSOC);
        $this->cache->stocker(self::CLE_CACHE, $sites);
    }
    $this->sitesEnMemoire = $sites;
    return $this->sitesEnMemoire;
}
}