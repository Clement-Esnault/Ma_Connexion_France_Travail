<?php

if (class_exists('CacheService')) return;

/**
 * CacheService
 * Cache fichier JSON pour les requêtes SQL lourdes (stats agrégées).
 * Les fichiers sont stockés dans cache/ à la racine du projet.
 * TTL par défaut : 5 minutes (configurable par appel).
 *
 * Usage :
 *   $cache = new CacheService();
 *   $data  = $cache->obtenir('par_site');
 *   if ($data === null) {
 *       $data = $service->parSite();
 *       $cache->stocker('par_site', $data);
 *   }
 */
class CacheService
{
    private string $repCache;

    public function __construct()
    {
        // Dossier cache/ à la racine du projet (deux niveaux au-dessus de backend/services/)
        $this->repCache = __DIR__ . '/../../cache/';

        if (!is_dir($this->repCache)) {
            mkdir($this->repCache, 0755, true);
        }
    }

    /**
     * Récupère une entrée du cache.
     * Retourne null si absente ou expirée.
     *
     * @param string $cle  Identifiant du cache (ex: 'par_site')
     * @param int    $dureeCache  Durée de vie en secondes (défaut : 300 = 5 min)
     */
    public function obtenir(string $cle, int $dureeCache = 300): mixed
    {
        $fichier = $this->cheminFichier($cle);

        if (!file_exists($fichier)) return null;

        // Expiration par date de modification du fichier
        if (time() - filemtime($fichier) > $dureeCache) {
            unlink($fichier);
            return null;
        }

        $contenu = file_get_contents($fichier);
        if ($contenu === false) return null;

        $data = json_decode($contenu, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            unlink($fichier); // Supprimer le fichier corrompu
            return null;
        }
        return $data;
    }

    /**
     * Stocke une valeur dans le cache.
     *
     * @param string $cle   Identifiant du cache
     * @param mixed  $data  Données à mettre en cache (sera sérialisé en JSON)
     */
    public function stocker(string $cle, mixed $donnees): void
    {
        file_put_contents($this->cheminFichier($cle), json_encode($donnees), LOCK_EX);
    }

    /**
     * Invalide une entrée du cache.
     *
     * @param string $cle  Identifiant à supprimer
     */
    public function invalider(string $cle): void
    {
        $fichier = $this->cheminFichier($cle);
        if (file_exists($fichier)) unlink($fichier);
    }

    /**
     * Vide tout le cache (utile après modification des seuils ou des sites).
     */
    public function vider(): void
    {
        foreach (glob($this->repCache . '*.json') as $fichier) {
            unlink($fichier);
        }
    }

    // Chemin complet du fichier de cache pour une clé donnée
    private function cheminFichier(string $cle): string
    {
        // Sanitize la clé pour éviter toute traversée de chemin
        return $this->repCache . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $cle) . '.json';
    }
}