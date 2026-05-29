-- Migration v1.9.2 -- genere le 29/05/2026 15:17:04
-- A jouer UNE SEULE FOIS sur une BDD existante.
-- Cree FT_AUDIT_SITES si absente.
-- Corrige ID_SITE en VARCHAR(10) si la table existait deja avec INT.
-- Ajoute les trois seuils difference_seuil_ping / _upload si absents.

USE ft_speedtest;

CREATE TABLE IF NOT EXISTS FT_AUDIT_SITES (
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

ALTER TABLE FT_AUDIT_SITES MODIFY ID_SITE VARCHAR(10) NOT NULL;

INSERT IGNORE INTO FT_SEUILS (NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE) VALUES
    ('difference_seuil_ping',   15, 15),
    ('difference_seuil_upload', 30, 30);

ALTER TABLE FT_LOGS
    ADD COLUMN IF NOT EXISTS MODE       VARCHAR(10) NOT NULL DEFAULT 'precise' AFTER UPLOAD_LOGS,
    ADD COLUMN IF NOT EXISTS SESSION_ID VARCHAR(64) NULL     AFTER MODE;
