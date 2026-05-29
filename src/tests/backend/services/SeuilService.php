<?php

if (class_exists('SeuilService')) return;

/**
 * SeuilService — v1.9.8
 *
 * Centralise le chargement et le calcul de verdict des seuils de qualité réseau.
 *
 * Deux niveaux :
 *   - Seuils globaux (FT_SEUILS)
 *   - Seuils dérogatoires par site (FT_SEUILS_SITE) — NULL = seuil global
 *
 * Toutes les méthodes accédant à FT_SEUILS_SITE ont un try/catch :
 * si la table n'existe pas encore (migration non jouée), elles retombent
 * silencieusement sur les seuils globaux sans lever d'erreur 500.
 */
class SeuilService
{
    private const METRIQUES = ['ping', 'download', 'upload'];

    private const PREFIXES = [
        'ping'     => 'PING',
        'download' => 'DL',
        'upload'   => 'UL',
    ];

    public function __construct(private PDO $pdo) {}

    // ── Seuils globaux ────────────────────────────────────────────────

    /**
     * Charge les seuils globaux depuis FT_SEUILS.
     * @return array<string, array{bon: float, mauvais: float}>
     */
    public function charger(): array
    {
        $rows = $this->pdo
            ->query('SELECT NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE FROM FT_SEUILS')
            ->fetchAll(PDO::FETCH_ASSOC);
        return $this->reindexer($rows);
    }

    /**
     * Met à jour les trois seuils globaux en base.
     * @param array<string, array{bonne: float, mauvaise: float}> $seuils
     */
    public function mettreAJour(array $seuils): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE FT_SEUILS SET VALEUR_BONNE = ?, VALEUR_MAUVAISE = ? WHERE NOM_SEUIL = ?'
        );
        foreach (self::METRIQUES as $metrique) {
            if (!isset($seuils[$metrique])) continue;
            $stmt->execute([
                (float) $seuils[$metrique]['bonne'],
                (float) $seuils[$metrique]['mauvaise'],
                $metrique,
            ]);
        }
    }

    // ── Seuils dérogatoires par site ──────────────────────────────────

    /**
     * Charge les seuils pour un site donné.
     * Fusionne globaux + dérogations — NULL dans FT_SEUILS_SITE = seuil global conservé.
     * Si FT_SEUILS_SITE n'existe pas, retourne les seuils globaux sans erreur.
     *
     * @return array<string, array{bon: float, mauvais: float}>
     */
    public function chargerPourSite(string $codeGxSite): array
    {
        $globaux = $this->charger();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT DL_VALEUR_BONNE,   DL_VALEUR_MAUVAISE,
                        UL_VALEUR_BONNE,   UL_VALEUR_MAUVAISE,
                        PING_VALEUR_BONNE, PING_VALEUR_MAUVAISE
                 FROM FT_SEUILS_SITE
                 WHERE CODE_GX_SITE = ?'
            );
            $stmt->execute([$codeGxSite]);
            $derog = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[Ma Connexion] FT_SEUILS_SITE inaccessible (migration non jouée) : ' . $e->getMessage());
            return $globaux;
        }

        if (!$derog) return $globaux;

        $fusionnes = $globaux;
        foreach (self::METRIQUES as $metrique) {
            $prefix = self::PREFIXES[$metrique];
            if ($derog["{$prefix}_VALEUR_BONNE"]    !== null) {
                $fusionnes[$metrique]['bon']     = (float) $derog["{$prefix}_VALEUR_BONNE"];
            }
            if ($derog["{$prefix}_VALEUR_MAUVAISE"] !== null) {
                $fusionnes[$metrique]['mauvais'] = (float) $derog["{$prefix}_VALEUR_MAUVAISE"];
            }
        }
        return $fusionnes;
    }

    /**
     * Retourne la dérogation brute d'un site, ou null si absente / table inexistante.
     * @return array<string, mixed>|null
     */
    public function chargerDerogation(string $codeGxSite): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT CODE_GX_SITE,
                        DL_VALEUR_BONNE,   DL_VALEUR_MAUVAISE,
                        UL_VALEUR_BONNE,   UL_VALEUR_MAUVAISE,
                        PING_VALEUR_BONNE, PING_VALEUR_MAUVAISE,
                        RAISON, DATE_MAJ, MAJ_PAR
                 FROM FT_SEUILS_SITE
                 WHERE CODE_GX_SITE = ?'
            );
            $stmt->execute([$codeGxSite]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Enregistre ou met à jour la dérogation d'un site (UPSERT).
     * Toutes valeurs null → suppression de la ligne.
     * Valeur 0 traitée comme null (pas de seuil valide à zéro).
     *
     * @param array<string, array{bonne: float|null, mauvaise: float|null}> $derogations
     */
    public function enregistrerDerogation(
        string  $codeGxSite,
        array   $derogations,
        ?string $raison,
        ?int    $idCompte
    ): void {
        $toutNull = true;
        foreach (self::METRIQUES as $m) {
            if ($this->valeurOuNull($derogations[$m]['bonne']    ?? null) !== null
             || $this->valeurOuNull($derogations[$m]['mauvaise'] ?? null) !== null) {
                $toutNull = false;
                break;
            }
        }

        if ($toutNull) {
            $this->supprimerDerogation($codeGxSite);
            return;
        }

        try {
            // UPSERT compatible MySQL (prod) et SQLite (tests unitaires)
            $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                // SQLite : INSERT OR REPLACE (remplace la ligne entière si PK existe)
                $sql = "
                    INSERT OR REPLACE INTO FT_SEUILS_SITE
                        (CODE_GX_SITE,
                         DL_VALEUR_BONNE, DL_VALEUR_MAUVAISE,
                         UL_VALEUR_BONNE, UL_VALEUR_MAUVAISE,
                         PING_VALEUR_BONNE, PING_VALEUR_MAUVAISE,
                         RAISON, MAJ_PAR)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
            } else {
                // MySQL / MariaDB : ON DUPLICATE KEY UPDATE
                $sql = "
                    INSERT INTO FT_SEUILS_SITE
                        (CODE_GX_SITE,
                         DL_VALEUR_BONNE, DL_VALEUR_MAUVAISE,
                         UL_VALEUR_BONNE, UL_VALEUR_MAUVAISE,
                         PING_VALEUR_BONNE, PING_VALEUR_MAUVAISE,
                         RAISON, MAJ_PAR)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        DL_VALEUR_BONNE      = VALUES(DL_VALEUR_BONNE),
                        DL_VALEUR_MAUVAISE   = VALUES(DL_VALEUR_MAUVAISE),
                        UL_VALEUR_BONNE      = VALUES(UL_VALEUR_BONNE),
                        UL_VALEUR_MAUVAISE   = VALUES(UL_VALEUR_MAUVAISE),
                        PING_VALEUR_BONNE    = VALUES(PING_VALEUR_BONNE),
                        PING_VALEUR_MAUVAISE = VALUES(PING_VALEUR_MAUVAISE),
                        RAISON               = VALUES(RAISON),
                        MAJ_PAR              = VALUES(MAJ_PAR)
                ";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $codeGxSite,
                $this->valeurOuNull($derogations['download']['bonne']    ?? null),
                $this->valeurOuNull($derogations['download']['mauvaise'] ?? null),
                $this->valeurOuNull($derogations['upload']['bonne']      ?? null),
                $this->valeurOuNull($derogations['upload']['mauvaise']   ?? null),
                $this->valeurOuNull($derogations['ping']['bonne']        ?? null),
                $this->valeurOuNull($derogations['ping']['mauvaise']     ?? null),
                $raison,
                $idCompte,
            ]);
        } catch (PDOException $e) {
            error_log('[Ma Connexion] enregistrerDerogation — migration non jouée : ' . $e->getMessage());
        }
    }

    /**
     * Supprime la dérogation d'un site (retour aux seuils globaux).
     */
    public function supprimerDerogation(string $codeGxSite): void
    {
        try {
            $this->pdo->prepare('DELETE FROM FT_SEUILS_SITE WHERE CODE_GX_SITE = ?')
                      ->execute([$codeGxSite]);
        } catch (PDOException $e) {
            // Table inexistante — rien à supprimer
        }
    }

    // ── Verdict ───────────────────────────────────────────────────────

    /**
     * Calcule le verdict qualitatif pour une métrique et une valeur.
     * Compatible avec charger() et chargerPourSite() — même format de seuils.
     *
     * @param  string $metrique  'ping' | 'download' | 'upload'
     * @param  float  $valeur
     * @param  array<string, array{bon: float, mauvais: float}> $seuils
     * @return 'confort'|'fonctionnel'|'insuffisant'|'inconnu'
     */
    public function verdict(string $metrique, float $valeur, array $seuils): string
    {
        if (!isset($seuils[$metrique])) return 'inconnu';

        ['bon' => $bon, 'mauvais' => $mauvais] = $seuils[$metrique];

        if ($metrique === 'ping') {
            return match (true) {
                $valeur <= $bon     => 'confort',
                $valeur >= $mauvais => 'insuffisant',
                default             => 'fonctionnel',
            };
        }

        return match (true) {
            $valeur >= $bon     => 'confort',
            $valeur <= $mauvais => 'insuffisant',
            default             => 'fonctionnel',
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Réindexe les lignes SQL brutes en tableau associatif par métrique.
     * @param  array<int, array{NOM_SEUIL: string, VALEUR_BONNE: string, VALEUR_MAUVAISE: string}> $rows
     * @return array<string, array{bon: float, mauvais: float}>
     */
    public function reindexer(array $rows): array
    {
        $seuils = [];
        foreach ($rows as $row) {
            $seuils[$row['NOM_SEUIL']] = [
                'bon'     => (float) $row['VALEUR_BONNE'],
                'mauvais' => (float) $row['VALEUR_MAUVAISE'],
            ];
        }
        return $seuils;
    }

    /**
     * Convertit une valeur en float ou retourne null si vide / zéro / invalide.
     */
    private function valeurOuNull(mixed $val): ?float
    {
        if ($val === null || $val === '' || $val === false) return null;
        $f = (float) $val;
        return $f > 0 ? $f : null;
    }
}