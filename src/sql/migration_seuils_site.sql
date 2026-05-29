-- migration_seuils_site.sql — v1.9.8
-- Ajoute les seuils dérogatoires par site (FT_SEUILS_SITE).
-- À jouer UNE SEULE FOIS sur la base ft_speedtest existante.
-- Sûr à rejouer : CREATE TABLE IF NOT EXISTS + INSERT IGNORE.

-- ── Table des seuils dérogatoires ────────────────────────────────────
-- Un site absent de cette table utilise les seuils globaux (FT_SEUILS).
-- NULL sur une métrique = utiliser le seuil global pour cette métrique.

CREATE TABLE IF NOT EXISTS FT_SEUILS_SITE (
    CODE_GX_SITE        VARCHAR(10)    NOT NULL,
    -- Téléchargement
    DL_VALEUR_BONNE     DECIMAL(8, 2)  DEFAULT NULL COMMENT 'NULL = seuil global',
    DL_VALEUR_MAUVAISE  DECIMAL(8, 2)  DEFAULT NULL COMMENT 'NULL = seuil global',
    -- Envoi
    UL_VALEUR_BONNE     DECIMAL(8, 2)  DEFAULT NULL COMMENT 'NULL = seuil global',
    UL_VALEUR_MAUVAISE  DECIMAL(8, 2)  DEFAULT NULL COMMENT 'NULL = seuil global',
    -- Ping
    PING_VALEUR_BONNE   DECIMAL(8, 2)  DEFAULT NULL COMMENT 'NULL = seuil global',
    PING_VALEUR_MAUVAISE DECIMAL(8, 2) DEFAULT NULL COMMENT 'NULL = seuil global',
    -- Métadonnées
    RAISON              VARCHAR(255)   DEFAULT NULL COMMENT 'Motif de la dérogation',
    DATE_MAJ            DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    MAJ_PAR             INT            DEFAULT NULL COMMENT 'FK ID_COMPTE',

    PRIMARY KEY (CODE_GX_SITE),
    CONSTRAINT fk_seuils_site_code
        FOREIGN KEY (CODE_GX_SITE) REFERENCES FT_SITE(CODE_GX_SITE)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seuils_site_compte
        FOREIGN KEY (MAJ_PAR) REFERENCES FT_COMPTES(ID_COMPTE)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Seuils de qualité dérogatoires par site. NULL = seuil global.';

-- ── Mise à jour .htaccess ─────────────────────────────────────────────
-- (rappel : ajouter get_seuils_site.php à la whitelist si créé séparément)
-- Dans ce projet le seuil est renvoyé directement dans get_site.php.
