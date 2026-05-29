-- Parametres du moteur speedtest -- genere le 29/05/2026 15:17:00
-- 17 entrees : modes precise (8) + fast (8) + debit_max_mbitps (1)
-- INSERT ... ON DUPLICATE KEY UPDATE : idempotent.

USE ft_speedtest;

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
