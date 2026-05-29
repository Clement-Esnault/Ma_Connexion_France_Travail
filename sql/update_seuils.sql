-- Seuils de qualite reseau -- genere le 29/05/2026 15:17:01
-- Idempotent : INSERT ... ON DUPLICATE KEY UPDATE

USE ft_speedtest;

INSERT INTO FT_SEUILS (NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE) VALUES
    ('ping',                    50,  90),
    ('download',                 5,   3),
    ('upload',                   5,   3),
    ('difference_seuil',        20,  20),
    ('difference_seuil_ping',   15,  15),
    ('difference_seuil_upload', 30,  30)
    ON DUPLICATE KEY UPDATE
        VALEUR_BONNE    = VALUES(VALEUR_BONNE),
        VALEUR_MAUVAISE = VALUES(VALEUR_MAUVAISE);
