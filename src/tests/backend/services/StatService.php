<?php

if (class_exists('StatService')) return;

/**
 * StatService
 * Encapsule toutes les requêtes SQL des statistiques agrégées.
 * Utilisé par backend/admin/stat.php (point d'entrée HTTP).
 *
 * Paramètre $mode :
 *   null         → tous les logs
 *   'precise'    → tests précis uniquement
 */
class StatService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Construit la clause WHERE pour filtrer par période.
     * @param int|null $jours  Nombre de jours à remonter (null = toute l'histoire)
     */
    private function wherePeriode(?int $jours): string
    {
        if ($jours === null || $jours <= 0) return '';
        return "AND l.DATE_LOGS >= NOW() - INTERVAL $jours DAY";
    }

    /**
     * Construit la clause WHERE pour filtrer par mode de test.
     * @param string|null $mode  null = tous, 'precise' uniquement depuis v1.10
     */
    private function whereMode(?string $mode): string
    {
        if ($mode === null) return '';
        return "AND l.MODE = '" . $mode . "'";
    }

    // ── Moyennes par site ─────────────────────────────────────────────
    public function parSite(?int $jours = null, ?string $mode = null): array
    {
        $clause = $this->wherePeriode($jours) . ' ' . $this->whereMode($mode);
        return $this->pdo->query("
            SELECT
                s.CODE_GX_SITE,
                s.NOM_SITE,
                s.ADRESSE,
                s.VILLE,
                s.CODE_POSTAL,
                s.LATITUDE,
                s.LONGITUDE,
                r.NOM_REGION,
                i.NOM_INTERREGION,
ROUND(AVG(l.PING_LOGS),        2) AS moy_ping,
ROUND(STDDEV(l.PING_LOGS),     2) AS ecart_type_ping,
ROUND(AVG(l.DOWNLOAD_LOGS),    2) AS moy_download,
ROUND(STDDEV(l.DOWNLOAD_LOGS), 2) AS ecart_type_download,
ROUND(AVG(l.UPLOAD_LOGS),      2) AS moy_upload,
ROUND(STDDEV(l.UPLOAD_LOGS),   2) AS ecart_type_upload,
COUNT(l.ID_LOGS)                  AS nb_tests,
MAX(l.DATE_LOGS)                  AS dernier_test
            FROM FT_LOGS l
            JOIN FT_SITE        s ON l.CODE_GX_SITE   = s.CODE_GX_SITE
            JOIN FT_DEPARTEMENT d ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
            JOIN FT_REGION      r ON d.ID_REGION      = r.ID_REGION
            JOIN FT_INTERREGION i ON r.ID_INTERREGION = i.ID_INTERREGION
            WHERE 1=1 $clause
            GROUP BY s.CODE_GX_SITE
            ORDER BY moy_download DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Moyennes par région ───────────────────────────────────────────
    /**
     * Retourne les moyennes de débit agrégées par région.
     *
     * @param  int|null    $jours  Fenêtre temporelle en jours (null = tout)
     * @param  string|null $mode   Filtre mode : 'precise' | null (v1.10 — mode fast supprimé)
     * @return array<int, array<string, mixed>>
     */
    public function parRegion(?int $jours = null, ?string $mode = null): array
    {
        $clause = $this->wherePeriode($jours) . ' ' . $this->whereMode($mode);
        return $this->pdo->query("
            SELECT
                r.NOM_REGION,
                i.NOM_INTERREGION,
ROUND(AVG(l.PING_LOGS),        2) AS moy_ping,
ROUND(STDDEV(l.PING_LOGS),     2) AS ecart_type_ping,
ROUND(AVG(l.DOWNLOAD_LOGS),    2) AS moy_download,
ROUND(STDDEV(l.DOWNLOAD_LOGS), 2) AS ecart_type_download,
ROUND(AVG(l.UPLOAD_LOGS),      2) AS moy_upload,
ROUND(STDDEV(l.UPLOAD_LOGS),   2) AS ecart_type_upload,
COUNT(l.ID_LOGS)                  AS nb_tests
            FROM FT_LOGS l
            JOIN FT_SITE        s ON l.CODE_GX_SITE   = s.CODE_GX_SITE
            JOIN FT_DEPARTEMENT d ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
            JOIN FT_REGION      r ON d.ID_REGION      = r.ID_REGION
            JOIN FT_INTERREGION i ON r.ID_INTERREGION = i.ID_INTERREGION
            WHERE 1=1 $clause
            GROUP BY r.ID_REGION
            ORDER BY r.NOM_REGION
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Moyennes par interrégion ──────────────────────────────────────
    public function parInterregion(?int $jours = null, ?string $mode = null): array
    {
        $clause = $this->wherePeriode($jours) . ' ' . $this->whereMode($mode);
        return $this->pdo->query("
            SELECT
                i.NOM_INTERREGION,
ROUND(AVG(l.PING_LOGS),        2) AS moy_ping,
ROUND(STDDEV(l.PING_LOGS),     2) AS ecart_type_ping,
ROUND(AVG(l.DOWNLOAD_LOGS),    2) AS moy_download,
ROUND(STDDEV(l.DOWNLOAD_LOGS), 2) AS ecart_type_download,
ROUND(AVG(l.UPLOAD_LOGS),      2) AS moy_upload,
ROUND(STDDEV(l.UPLOAD_LOGS),   2) AS ecart_type_upload,
COUNT(l.ID_LOGS)                  AS nb_tests
            FROM FT_LOGS l
            JOIN FT_SITE        s ON l.CODE_GX_SITE   = s.CODE_GX_SITE
            JOIN FT_DEPARTEMENT d ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
            JOIN FT_REGION      r ON d.ID_REGION      = r.ID_REGION
            JOIN FT_INTERREGION i ON r.ID_INTERREGION = i.ID_INTERREGION
            WHERE 1=1 $clause
            GROUP BY i.ID_INTERREGION
            ORDER BY i.NOM_INTERREGION
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Moyennes par département
    /**
     * Retourne les moyennes de débit agrégées par département.
     *
     * @param  int|null    $jours
     * @param  string|null $mode
     * @return array<int, array<string, mixed>>
     */ 
    public function parDepartement(?int $jours = null, ?string $mode = null): array
    {
        $clause = $this->wherePeriode($jours) . ' ' . $this->whereMode($mode);
        return $this->pdo->query("
            SELECT
                d.NOM_DEPARTEMENT,
                r.NOM_REGION,
                i.NOM_INTERREGION,
ROUND(AVG(l.PING_LOGS),        2) AS moy_ping,
ROUND(STDDEV(l.PING_LOGS),     2) AS ecart_type_ping,
ROUND(AVG(l.DOWNLOAD_LOGS),    2) AS moy_download,
ROUND(STDDEV(l.DOWNLOAD_LOGS), 2) AS ecart_type_download,
ROUND(AVG(l.UPLOAD_LOGS),      2) AS moy_upload,
ROUND(STDDEV(l.UPLOAD_LOGS),   2) AS ecart_type_upload,
COUNT(l.ID_LOGS)                  AS nb_tests
            FROM FT_LOGS l
            JOIN FT_SITE        s ON l.CODE_GX_SITE   = s.CODE_GX_SITE
            JOIN FT_DEPARTEMENT d ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
            JOIN FT_REGION      r ON d.ID_REGION      = r.ID_REGION
            JOIN FT_INTERREGION i ON r.ID_INTERREGION = i.ID_INTERREGION
            WHERE 1=1 $clause
            GROUP BY d.ID_DEPARTEMENT
            ORDER BY d.NOM_DEPARTEMENT
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Moyenne nationale ─────────────────────────────────────────────
    public function nationale(?int $jours = null, ?string $mode = null): array
    {
        $clause = $this->wherePeriode($jours) . ' ' . $this->whereMode($mode);
        return $this->pdo->query("
            SELECT
ROUND(AVG(l.PING_LOGS),        2) AS moy_ping,
ROUND(STDDEV(l.PING_LOGS),     2) AS ecart_type_ping,
ROUND(AVG(l.DOWNLOAD_LOGS),    2) AS moy_download,
ROUND(STDDEV(l.DOWNLOAD_LOGS), 2) AS ecart_type_download,
ROUND(AVG(l.UPLOAD_LOGS),      2) AS moy_upload,
ROUND(STDDEV(l.UPLOAD_LOGS),   2) AS ecart_type_upload,
COUNT(l.ID_LOGS)                  AS nb_tests
            FROM FT_LOGS l
            WHERE 1=1 $clause
        ")->fetch(PDO::FETCH_ASSOC);
    }

    // ── Évolution mensuelle ───────────────────────────────────────────
    public function evolution(?string $idSite = null, ?string $mode = null): array
    {
        $clauseMode = $this->whereMode($mode);
        $where      = 'WHERE 1=1 ' . $clauseMode;
        if ($idSite) $where .= ' AND l.CODE_GX_SITE = :site_id';

        $stmt = $this->pdo->prepare("
            SELECT
                s.CODE_GX_SITE,
                s.NOM_SITE,
                DATE_FORMAT(l.DATE_LOGS, '%Y-%m') AS mois,
                ROUND(AVG(l.PING_LOGS),     2)    AS moy_ping,
                ROUND(AVG(l.DOWNLOAD_LOGS), 2)    AS moy_download,
                ROUND(AVG(l.UPLOAD_LOGS),   2)    AS moy_upload,
                COUNT(l.ID_LOGS)                  AS nb_tests
            FROM FT_LOGS l
            JOIN FT_SITE s ON l.CODE_GX_SITE = s.CODE_GX_SITE
            $where
            GROUP BY s.CODE_GX_SITE, mois
            ORDER BY mois ASC, s.NOM_SITE
        ");
        if ($idSite) $stmt->bindParam(':site_id', $idSite);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //  Sites en alerte
    public function alertes(float $seuilDl = 10, float $seuilPing = 100, ?string $mode = null): array
    {
        $clauseMode = $this->whereMode($mode);
        $stmt = $this->pdo->prepare("
            SELECT
                s.CODE_GX_SITE,
                s.NOM_SITE,
                r.NOM_REGION,
                ROUND(AVG(l.DOWNLOAD_LOGS), 2) AS moy_download,
                ROUND(AVG(l.UPLOAD_LOGS),   2) AS moy_upload,
                ROUND(AVG(l.PING_LOGS),     2) AS moy_ping,
                COUNT(l.ID_LOGS)               AS nb_tests
            FROM FT_LOGS l
            JOIN FT_SITE        s ON l.CODE_GX_SITE   = s.CODE_GX_SITE
            JOIN FT_DEPARTEMENT d ON s.ID_DEPARTEMENT = d.ID_DEPARTEMENT
            JOIN FT_REGION      r ON d.ID_REGION      = r.ID_REGION
            WHERE 1=1 $clauseMode
            GROUP BY s.CODE_GX_SITE
            HAVING moy_download < :dl_seuil OR moy_ping > :ping_seuil
            ORDER BY moy_download ASC
        ");
        $stmt->bindParam(':dl_seuil',   $seuilDl,   PDO::PARAM_STR);
        $stmt->bindParam(':ping_seuil', $seuilPing, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Seuils de qualité 
    public function seuils(): array
    {
        return $this->pdo->query(
            "SELECT NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE FROM FT_SEUILS"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Comparaison précis vs rapide pour un site ─────────────────────
    // Retourne les moyennes côte à côte pour les deux modes
    // ── Comparaison précis vs rapide ──────────────────────────────────────
    /**
     * Retourne les statistiques agrégées par mode (v1.10 : mode unique 'precise').
     *
     * @param  string|null $idSite  Code GX du site (null = national).
     * @param  int|null    $jours   Fenêtre temporelle en jours (null = tout).
     * @return array<int, array<string, mixed>>
     */
    public function comparaisonModes(?string $idSite = null, ?int $jours = null): array
    {
        $clausePeriode = $this->wherePeriode($jours);
        $conditions    = ['1=1'];
        $parametres    = [];

        // Filtre période (fragment SQL sûr — entier validé dans wherePeriode)
        if ($clausePeriode) {
            // wherePeriode() retourne déjà un fragment AND sûr, on l'intègre directement
        }

        // Filtre site via requête préparée (remplace pdo->quote)
        if ($idSite !== null) {
            $conditions[] = 'l.CODE_GX_SITE = :site_id';
            $parametres[':site_id'] = $idSite;
        }

        $where = 'WHERE 1=1 ' . $clausePeriode;
        if ($idSite !== null) {
            $where .= ' AND l.CODE_GX_SITE = :site_id';
        }

        $requete = $this->pdo->prepare("
            SELECT
                l.MODE,
                ROUND(AVG(l.PING_LOGS),        2) AS moy_ping,
                ROUND(STDDEV(l.PING_LOGS),     2) AS ecart_type_ping,
                ROUND(AVG(l.DOWNLOAD_LOGS),    2) AS moy_download,
                ROUND(STDDEV(l.DOWNLOAD_LOGS), 2) AS ecart_type_download,
                ROUND(AVG(l.UPLOAD_LOGS),      2) AS moy_upload,
                ROUND(STDDEV(l.UPLOAD_LOGS),   2) AS ecart_type_upload,
                COUNT(l.ID_LOGS)                  AS nb_tests
            FROM FT_LOGS l
            $where
            GROUP BY l.MODE
            ORDER BY l.MODE ASC
        ");

        if ($idSite !== null) {
            $requete->bindParam(':site_id', $idSite);
        }

        $requete->execute();
        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }
   public function heatmapHoraire(?string $idSite, string $metrique, ?int $jours, ?string $mode): array
{
    $colonne = match($metrique) {
        'upload' => 'UPLOAD_LOGS',
        'ping'   => 'PING_LOGS',
        default  => 'DOWNLOAD_LOGS',
    };

    $conditions = ['1=1'];
    $params     = [];

    if ($jours !== null) {
        $conditions[] = 'l.DATE_LOGS >= NOW() - INTERVAL :jours DAY';
        $params[':jours'] = $jours;
    }
    if ($idSite !== null && $idSite !== 'all') {
        $conditions[] = 'l.CODE_GX_SITE = :site';
        $params[':site'] = $idSite;
    }
    if ($mode !== null) {
        $conditions[] = 'l.MODE = :mode';
        $params[':mode'] = $mode;
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    $sql = "
        SELECT
            DAYOFWEEK(l.DATE_LOGS)      AS jour_semaine,
            HOUR(l.DATE_LOGS)           AS heure,
            ROUND(AVG(l.{$colonne}), 2) AS valeur,
            COUNT(*)                    AS nb
        FROM FT_LOGS l
        {$where}
        GROUP BY jour_semaine, heure
        ORDER BY jour_semaine, heure
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}