-- Schema complet Ma Connexion -- genere le 29/05/2026 15:16:21
-- Pour recreer depuis zero :
--   1. phpMyAdmin > selectionner ft_speedtest > Operations > Supprimer
--   2. Executer ce script

CREATE DATABASE IF NOT EXISTS ft_speedtest
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ft_speedtest;

CREATE TABLE FT_INTERREGION (
    ID_INTERREGION  INT         NOT NULL AUTO_INCREMENT,
    NOM_INTERREGION VARCHAR(50) NOT NULL,
    PRIMARY KEY (ID_INTERREGION),
    UNIQUE KEY uk_nom_interregion (NOM_INTERREGION)
);

CREATE TABLE FT_REGION (
    ID_REGION      INT         NOT NULL AUTO_INCREMENT,
    NOM_REGION     VARCHAR(50) NOT NULL,
    ID_INTERREGION INT         NOT NULL,
    PRIMARY KEY (ID_REGION),
    UNIQUE KEY uk_nom_region (NOM_REGION),
    INDEX idx_region_interregion (ID_INTERREGION),
    FOREIGN KEY (ID_INTERREGION) REFERENCES FT_INTERREGION(ID_INTERREGION)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE FT_DEPARTEMENT (
    ID_DEPARTEMENT  INT         NOT NULL AUTO_INCREMENT,
    NUM_DEPARTEMENT VARCHAR(3)  NOT NULL,
    NOM_DEPARTEMENT VARCHAR(50) NOT NULL,
    ID_REGION       INT         NOT NULL,
    PRIMARY KEY (ID_DEPARTEMENT),
    UNIQUE KEY uk_num_dept (NUM_DEPARTEMENT),
    INDEX idx_dept_region (ID_REGION),
    FOREIGN KEY (ID_REGION) REFERENCES FT_REGION(ID_REGION)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE FT_SITE (
    CODE_GX_SITE   VARCHAR(10)      NOT NULL,
    NOM_SITE       VARCHAR(100)     NOT NULL,
    CODE_POSTAL    CHAR(5)          NOT NULL,
    ADRESSE        VARCHAR(150)     NULL,
    VILLE          VARCHAR(100)     NULL,
    LATITUDE       DECIMAL(9,6)     NULL,
    LONGITUDE      DECIMAL(9,6)     NULL,
    IP_SPECIALE    TINYINT(1)       NOT NULL DEFAULT 0,
    IP_RESEAU      VARCHAR(45)      NULL,
    MASQUE_SITE    TINYINT UNSIGNED NULL,
    ID_DEPARTEMENT INT              NOT NULL,
    PRIMARY KEY (CODE_GX_SITE),
    INDEX idx_site_departement (ID_DEPARTEMENT),
    INDEX idx_site_ip_speciale (IP_SPECIALE),
    FOREIGN KEY (ID_DEPARTEMENT) REFERENCES FT_DEPARTEMENT(ID_DEPARTEMENT)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE FT_LOGS (
    ID_LOGS       INT           NOT NULL AUTO_INCREMENT,
    PING_LOGS     DECIMAL(8,2)  NOT NULL,
    DOWNLOAD_LOGS DECIMAL(10,2) NOT NULL,
    UPLOAD_LOGS   DECIMAL(10,2) NOT NULL,
    MODE          VARCHAR(10)   NOT NULL DEFAULT 'precise',
    SESSION_ID    VARCHAR(64)   NULL,
    DATE_LOGS     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    IP_CLIENT     VARCHAR(45)   NOT NULL,
    CODE_GX_SITE  VARCHAR(10)   NOT NULL,
    PRIMARY KEY (ID_LOGS),
    INDEX idx_logs_site        (CODE_GX_SITE),
    INDEX idx_logs_date        (DATE_LOGS),
    INDEX idx_logs_site_date   (CODE_GX_SITE, DATE_LOGS),
    INDEX idx_logs_ip_client   (IP_CLIENT),
    INDEX idx_logs_mode        (MODE),
    INDEX idx_logs_session     (SESSION_ID),
    FOREIGN KEY (CODE_GX_SITE) REFERENCES FT_SITE(CODE_GX_SITE)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE FT_COMMENTAIRES (
    ID_COMMENTAIRES     INT          NOT NULL AUTO_INCREMENT,
    DATE_COMMENTAIRE    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONTENU_COMMENTAIRE VARCHAR(250),
    ID_LOGS             INT          NOT NULL,
    PRIMARY KEY (ID_COMMENTAIRES),
    INDEX idx_commentaires_logs (ID_LOGS),
    FOREIGN KEY (ID_LOGS) REFERENCES FT_LOGS(ID_LOGS)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE FT_COMPTES (
    ID_COMPTE    INT          NOT NULL AUTO_INCREMENT,
    ALIAS_COMPTE VARCHAR(200) NOT NULL,
    MDP_COMPTE   VARCHAR(200) NOT NULL,
    IS_ADMIN     BOOLEAN      NOT NULL DEFAULT FALSE,
    PRIMARY KEY (ID_COMPTE)
);

CREATE TABLE FT_QUEUE_STATUS (
    ID_STATUS  TINYINT     NOT NULL,
    NOM_STATUS VARCHAR(20) NOT NULL,
    PRIMARY KEY (ID_STATUS)
);

CREATE TABLE FT_QUEUE_LOCK (
    ID INT NOT NULL DEFAULT 1,
    PRIMARY KEY (ID)
);
INSERT INTO FT_QUEUE_LOCK VALUES (1);
INSERT INTO FT_QUEUE_STATUS VALUES (1, 'waiting'), (2, 'ready'), (3, 'done');

CREATE TABLE FT_QUEUE (
    ID_QUEUE   INT         NOT NULL AUTO_INCREMENT,
    TOKEN      VARCHAR(64) NOT NULL,
    ID_STATUS  TINYINT     NOT NULL DEFAULT 1,
    CREATED_AT DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    STARTED_AT DATETIME    NULL DEFAULT NULL,
    PRIMARY KEY (ID_QUEUE),
    UNIQUE KEY uk_token (TOKEN),
    INDEX idx_queue_status  (ID_STATUS),
    INDEX idx_queue_created (CREATED_AT),
    FOREIGN KEY (ID_STATUS) REFERENCES FT_QUEUE_STATUS(ID_STATUS)
);

CREATE TABLE FT_SEUILS (
    ID_SEUIL        INT          NOT NULL AUTO_INCREMENT,
    NOM_SEUIL       VARCHAR(20)  NOT NULL,
    VALEUR_BONNE    DECIMAL(8,2) NOT NULL,
    VALEUR_MAUVAISE DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (ID_SEUIL),
    UNIQUE KEY uk_nom_seuil (NOM_SEUIL)
);

INSERT INTO FT_SEUILS (NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE) VALUES
    ('ping',                    50,  90),
    ('download',                 5,   3),
    ('upload',                   5,   3),
    ('difference_seuil',        20,  20),
    ('difference_seuil_ping',   15,  15),
    ('difference_seuil_upload', 30,  30);

-- Seuils derogatoires par site — NULL = seuil global conserve
CREATE TABLE FT_SEUILS_SITE (
    CODE_GX_SITE         VARCHAR(10)  NOT NULL,
    DL_VALEUR_BONNE      DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global',
    DL_VALEUR_MAUVAISE   DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global',
    UL_VALEUR_BONNE      DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global',
    UL_VALEUR_MAUVAISE   DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global',
    PING_VALEUR_BONNE    DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global',
    PING_VALEUR_MAUVAISE DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global',
    RAISON               VARCHAR(255) DEFAULT NULL COMMENT 'Motif de la derogation',
    DATE_MAJ             DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    MAJ_PAR              INT          DEFAULT NULL COMMENT 'FK ID_COMPTE',
    PRIMARY KEY (CODE_GX_SITE),
    CONSTRAINT fk_seuils_site_code
        FOREIGN KEY (CODE_GX_SITE) REFERENCES FT_SITE(CODE_GX_SITE)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seuils_site_compte
        FOREIGN KEY (MAJ_PAR) REFERENCES FT_COMPTES(ID_COMPTE)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO FT_COMPTES (ALIAS_COMPTE, MDP_COMPTE, IS_ADMIN) VALUES
    ('admin', '$2y$10$REPLACE_WITH_YOUR_BCRYPT_HASH_FOR_ADMIN', true),
    ('tech',  '$2y$10$REPLACE_WITH_YOUR_BCRYPT_HASH_FOR_TECH', false);

CREATE TABLE FT_LOGS_CONNEXION (
    ID_LOG_CO    INT         NOT NULL AUTO_INCREMENT,
    ID_COMPTE    INT         NOT NULL,
    TYPE_ACCES   VARCHAR(10) NOT NULL,
    IP_CONNEXION VARCHAR(45) NOT NULL,
    DATE_CO      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    SUCCES       TINYINT(1)  NOT NULL DEFAULT 1,
    PRIMARY KEY (ID_LOG_CO),
    INDEX idx_logco_compte (ID_COMPTE),
    INDEX idx_logco_date   (DATE_CO),
    FOREIGN KEY (ID_COMPTE) REFERENCES FT_COMPTES(ID_COMPTE)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE FT_CONFIG_SPEEDTEST (
    CLE_CONFIG     VARCHAR(60)   NOT NULL,
    VALEUR_CONFIG  DECIMAL(10,3) NOT NULL,
    LIBELLE        VARCHAR(120)  NOT NULL,
    UNITE          VARCHAR(20)   NOT NULL DEFAULT '',
    DATE_MAJ       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (CLE_CONFIG)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO FT_CONFIG_SPEEDTEST (CLE_CONFIG, VALEUR_CONFIG, LIBELLE, UNITE) VALUES
    ('precise_ping_prechauffage',          3,    'Precis - Ping : requetes de prechauffage',  '-'),
    ('precise_ping_echantillons',           8,    'Precis - Ping : nombre de mesures',         '-'),
    ('precise_ping_delay',                 50,    'Precis - Ping : delai entre mesures',       'ms'),
    ('precise_download_dureeMsDownload',   4000,  'Precis - Download : duree de mesure',       'ms'),
    ('precise_download_parallel',          1,     'Precis - Download : requetes en parallele', '-'),
    ('precise_upload_dureeMsUpload',       4000,  'Precis - Upload : duree de mesure',         'ms'),
    ('precise_upload_parallel',            1,     'Precis - Upload : requetes en parallele',   '-'),
    ('precise_upload_tailleMoBlob',        12,    'Precis - Upload : taille blob envoye',      'Mo'),
    ('fast_ping_prechauffage',             1,     'Rapide - Ping : requetes de prechauffage',  '-'),
    ('fast_ping_echantillons',             5,     'Rapide - Ping : nombre de mesures',         '-'),
    ('fast_ping_delay',                    30,    'Rapide - Ping : delai entre mesures',       'ms'),
    ('fast_download_dureeMsDownload',      5000,  'Rapide - Download : duree de mesure',       'ms'),
    ('fast_download_parallel',             1,     'Rapide - Download : requetes en parallele', '-'),
    ('fast_upload_dureeMsUpload',          5000,  'Rapide - Upload : duree de mesure',         'ms'),
    ('fast_upload_parallel',               1,     'Rapide - Upload : requetes en parallele',   '-'),
    ('fast_upload_tailleMoBlob',           25,    'Rapide - Upload : taille blob envoye',      'Mo'),
    ('debit_max_mbitps',                   1000,  'Plafond de sanite - 0 = desactive',         'Mbit/s')
    ON DUPLICATE KEY UPDATE
        VALEUR_CONFIG = VALUES(VALEUR_CONFIG),
        LIBELLE       = VALUES(LIBELLE),
        UNITE         = VALUES(UNITE);

-- Historique des ajouts / modifications / suppressions de sites
CREATE TABLE FT_AUDIT_SITES (
    ID_AUDIT    INT                                       NOT NULL AUTO_INCREMENT,
    ID_COMPTE   INT                                       NOT NULL,
    ACTION      ENUM('AJOUT','MODIFICATION','SUPPRESSION') NOT NULL,
    ID_SITE     VARCHAR(10)                               NOT NULL,
    NOM_SITE    VARCHAR(255)                              NOT NULL,
    DATE_ACTION DATETIME                                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    IP_ACTION   VARCHAR(45)                               NOT NULL,
    DETAIL      TEXT                                      NULL,
    PRIMARY KEY (ID_AUDIT),
    INDEX idx_audit_compte (ID_COMPTE),
    INDEX idx_audit_site   (ID_SITE),
    INDEX idx_audit_date   (DATE_ACTION),
    INDEX idx_audit_action (ACTION),
    FOREIGN KEY (ID_COMPTE) REFERENCES FT_COMPTES(ID_COMPTE)
        ON DELETE RESTRICT ON UPDATE CASCADE
);
