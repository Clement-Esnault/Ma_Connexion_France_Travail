' =====================================================================
' MODULE : ExportSQL
' Export SQL Ma Connexion (France Travail Speed)
' Version : 1.10.0 — 2026-05-29
' =====================================================================
'
' OBJECTIF
' --------
' Ce module genere automatiquement les fichiers SQL necessaires pour
' alimenter la base de donnees de l application Ma Connexion
' a partir d un fichier Excel de reference des sites.
'
' FICHIERS GENERES
' ---------------------------------
' Ordre d execution obligatoire dans phpMyAdmin :
'
'   0. create_database.sql          -- cree la base et toutes les tables
'   1. clean.sql                    -- vide toutes les tables
'   2. insert_interregions.sql      -- insere les interregions (SDP, col D)
'   3. insert_regions.sql           -- insere les regions (Direction, col C)
'   4. insert_departements.sql      -- insere les departements (col AA)
'   5. insert_sites.sql             -- insere les sites (toutes colonnes)
'   6. indexes.sql                  -- cree les index sur FT_LOGS
'   7. insert_config_speedtest.sql  -- parametres moteur speedtest (17 entrees)
'   8. update_seuils.sql            -- seuils de qualite reseau
'   [migration] migration_audit.sql     -- a jouer UNE SEULE FOIS sur BDD existante
'   [migration] migration_seuils_site.sql -- a jouer UNE SEULE FOIS (seuils par site)
'
' COLONNES EXCEL UTILISEES
' -------------------------
'   A  (col  1) -- Code site GX       -> CODE_GX_SITE (prefixe "GX")
'   B  (col  2) -- Nom du site        -> NOM_SITE
'   C  (col  3) -- Direction          -> FT_REGION (region)
'   D  (col  4) -- SDP                -> FT_INTERREGION (interregion)
'   G  (col  7) -- Adresse principale -> ADRESSE (concatenee avec H)
'   H  (col  8) -- Adresse 2          -> complement ADRESSE
'   I  (col  9) -- Code postal        -> CODE_POSTAL
'   J  (col 10) -- Ville              -> VILLE
'   T  (col 20) -- IP reseau          -> IP_RESEAU + MASQUE_SITE
'   AA (col 27) -- Numero departement -> FK vers FT_DEPARTEMENT
'
' CONFIGURATION DU DOSSIER DE SORTIE
' -----------------------------------
' Le dossier de sortie est lu depuis l onglet "Config" cellule B1.
' Si vide ou invalide, une InputBox le demande et le memorise.
' Pour changer de dossier : vider la cellule B1 de l onglet Config.
' =====================================================================

Option Explicit

' ====================================================================
' UTILITAIRES
' ====================================================================

' ------------------------------------------------------------
' GetOutputFolder
' Retourne le dossier de sortie depuis Config!B1
' Le cree/demande si necessaire
' ------------------------------------------------------------
Function GetOutputFolder() As String

    Dim wsConfig As Worksheet
    Dim savePath As String

    On Error Resume Next
    Set wsConfig = ThisWorkbook.Sheets("Config")
    On Error GoTo 0

    If wsConfig Is Nothing Then
        Set wsConfig = ThisWorkbook.Sheets.Add
        wsConfig.Name = "Config"
        wsConfig.Cells(1, 1).Value = "Dossier de sortie SQL"
        wsConfig.Cells(1, 2).Value = ""
    End If

    savePath = Trim(CStr(wsConfig.Cells(1, 2).Value))

    Dim pathOk As Boolean
    pathOk = False
    If savePath <> "" Then
        On Error Resume Next
        If Dir(savePath, vbDirectory) <> "" Then pathOk = True
        On Error GoTo 0
    End If

    If Not pathOk Then
        savePath = InputBox( _
            "Entrez le chemin du dossier de sortie pour les fichiers SQL :" & vbCrLf & vbCrLf & _
            "(il sera memorise pour les prochaines fois)", _
            "Dossier de sortie", _
            Environ("USERPROFILE") & "\Documents")
        If savePath = "" Then
            GetOutputFolder = ""
            Exit Function
        End If
        wsConfig.Cells(1, 2).Value = savePath
    End If

    If Right(savePath, 1) = "\" Then savePath = Left(savePath, Len(savePath) - 1)
    GetOutputFolder = savePath

End Function


' ------------------------------------------------------------
' SaveUTF8
' Ecrit une chaine dans un fichier UTF-8 sans BOM
' ------------------------------------------------------------
Sub SaveUTF8(filePath As String, content As String)

    Dim streamTexte As Object
    Dim streamBrut1 As Object
    Dim streamBrut2 As Object

    Set streamTexte = CreateObject("ADODB.Stream")
    streamTexte.Type = 2
    streamTexte.Charset = "UTF-8"
    streamTexte.Open
    streamTexte.WriteText content

    Set streamBrut1 = CreateObject("ADODB.Stream")
    streamBrut1.Type = 1
    streamBrut1.Open
    streamTexte.Position = 0
    streamTexte.CopyTo streamBrut1
    streamTexte.Close

    Set streamBrut2 = CreateObject("ADODB.Stream")
    streamBrut2.Type = 1
    streamBrut2.Open
    streamBrut1.Position = 3
    streamBrut1.CopyTo streamBrut2
    streamBrut1.Close

    streamBrut2.SaveToFile filePath, 2
    streamBrut2.Close

End Sub


' ====================================================================
' EXPORT SCHEMA COMPLET
' ====================================================================

' ------------------------------------------------------------
' ExportCreateDatabase
' Genere create_database.sql : toutes les tables + donnees initiales
' v1.9.2 : ajout FT_AUDIT_SITES
' v1.9.8 : ajout FT_SEUILS_SITE
' ------------------------------------------------------------
Sub ExportCreateDatabase()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Dim s As String
    s = ""

    s = s & "-- Schema complet Ma Connexion -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "-- Pour recreer depuis zero :" & vbCrLf
    s = s & "--   1. phpMyAdmin > selectionner ft_speedtest > Operations > Supprimer" & vbCrLf
    s = s & "--   2. Executer ce script" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "CREATE DATABASE IF NOT EXISTS ft_speedtest" & vbCrLf
    s = s & "    CHARACTER SET utf8mb4" & vbCrLf
    s = s & "    COLLATE utf8mb4_unicode_ci;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "USE ft_speedtest;" & vbCrLf
    s = s & "" & vbCrLf

    ' Hierarchie geographique
    s = s & "CREATE TABLE FT_INTERREGION (" & vbCrLf
    s = s & "    ID_INTERREGION  INT         NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    NOM_INTERREGION VARCHAR(50) NOT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_INTERREGION)," & vbCrLf
    s = s & "    UNIQUE KEY uk_nom_interregion (NOM_INTERREGION)" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    s = s & "CREATE TABLE FT_REGION (" & vbCrLf
    s = s & "    ID_REGION      INT         NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    NOM_REGION     VARCHAR(50) NOT NULL," & vbCrLf
    s = s & "    ID_INTERREGION INT         NOT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_REGION)," & vbCrLf
    s = s & "    UNIQUE KEY uk_nom_region (NOM_REGION)," & vbCrLf
    s = s & "    INDEX idx_region_interregion (ID_INTERREGION)," & vbCrLf
    s = s & "    FOREIGN KEY (ID_INTERREGION) REFERENCES FT_INTERREGION(ID_INTERREGION)" & vbCrLf
    s = s & "        ON DELETE RESTRICT ON UPDATE CASCADE" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    s = s & "CREATE TABLE FT_DEPARTEMENT (" & vbCrLf
    s = s & "    ID_DEPARTEMENT  INT         NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    NUM_DEPARTEMENT VARCHAR(3)  NOT NULL," & vbCrLf
    s = s & "    NOM_DEPARTEMENT VARCHAR(50) NOT NULL," & vbCrLf
    s = s & "    ID_REGION       INT         NOT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_DEPARTEMENT)," & vbCrLf
    s = s & "    UNIQUE KEY uk_num_dept (NUM_DEPARTEMENT)," & vbCrLf
    s = s & "    INDEX idx_dept_region (ID_REGION)," & vbCrLf
    s = s & "    FOREIGN KEY (ID_REGION) REFERENCES FT_REGION(ID_REGION)" & vbCrLf
    s = s & "        ON DELETE RESTRICT ON UPDATE CASCADE" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    ' Sites
    s = s & "CREATE TABLE FT_SITE (" & vbCrLf
    s = s & "    CODE_GX_SITE   VARCHAR(10)      NOT NULL," & vbCrLf
    s = s & "    NOM_SITE       VARCHAR(100)     NOT NULL," & vbCrLf
    s = s & "    CODE_POSTAL    CHAR(5)          NOT NULL," & vbCrLf
    s = s & "    ADRESSE        VARCHAR(150)     NULL," & vbCrLf
    s = s & "    VILLE          VARCHAR(100)     NULL," & vbCrLf
    s = s & "    LATITUDE       DECIMAL(9,6)     NULL," & vbCrLf
    s = s & "    LONGITUDE      DECIMAL(9,6)     NULL," & vbCrLf
    s = s & "    IP_SPECIALE    TINYINT(1)       NOT NULL DEFAULT 0," & vbCrLf
    s = s & "    IP_RESEAU      VARCHAR(45)      NULL," & vbCrLf
    s = s & "    MASQUE_SITE    TINYINT UNSIGNED NULL," & vbCrLf
    s = s & "    ID_DEPARTEMENT INT              NOT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (CODE_GX_SITE)," & vbCrLf
    s = s & "    INDEX idx_site_departement (ID_DEPARTEMENT)," & vbCrLf
    s = s & "    INDEX idx_site_ip_speciale (IP_SPECIALE)," & vbCrLf
    s = s & "    FOREIGN KEY (ID_DEPARTEMENT) REFERENCES FT_DEPARTEMENT(ID_DEPARTEMENT)" & vbCrLf
    s = s & "        ON DELETE RESTRICT ON UPDATE CASCADE" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    ' Logs speedtest
    s = s & "CREATE TABLE FT_LOGS (" & vbCrLf
    s = s & "    ID_LOGS       INT           NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    PING_LOGS     DECIMAL(8,2)  NOT NULL," & vbCrLf
    s = s & "    DOWNLOAD_LOGS DECIMAL(10,2) NOT NULL," & vbCrLf
    s = s & "    UPLOAD_LOGS   DECIMAL(10,2) NOT NULL," & vbCrLf
    s = s & "    MODE          VARCHAR(10)   NOT NULL DEFAULT 'precise'," & vbCrLf
    s = s & "    SESSION_ID    VARCHAR(64)   NULL," & vbCrLf
    s = s & "    DATE_LOGS     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP," & vbCrLf
    s = s & "    IP_CLIENT     VARCHAR(45)   NOT NULL," & vbCrLf
    s = s & "    CODE_GX_SITE  VARCHAR(10)   NOT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_LOGS)," & vbCrLf
    s = s & "    INDEX idx_logs_site        (CODE_GX_SITE)," & vbCrLf
    s = s & "    INDEX idx_logs_date        (DATE_LOGS)," & vbCrLf
    s = s & "    INDEX idx_logs_site_date   (CODE_GX_SITE, DATE_LOGS)," & vbCrLf
    s = s & "    INDEX idx_logs_ip_client   (IP_CLIENT)," & vbCrLf
    s = s & "    INDEX idx_logs_mode        (MODE)," & vbCrLf
    s = s & "    INDEX idx_logs_session     (SESSION_ID)," & vbCrLf
    s = s & "    FOREIGN KEY (CODE_GX_SITE) REFERENCES FT_SITE(CODE_GX_SITE)" & vbCrLf
    s = s & "        ON DELETE RESTRICT ON UPDATE CASCADE" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    ' Commentaires
    s = s & "CREATE TABLE FT_COMMENTAIRES (" & vbCrLf
    s = s & "    ID_COMMENTAIRES     INT          NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    DATE_COMMENTAIRE    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP," & vbCrLf
    s = s & "    CONTENU_COMMENTAIRE VARCHAR(250)," & vbCrLf
    s = s & "    ID_LOGS             INT          NOT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_COMMENTAIRES)," & vbCrLf
    s = s & "    INDEX idx_commentaires_logs (ID_LOGS)," & vbCrLf
    s = s & "    FOREIGN KEY (ID_LOGS) REFERENCES FT_LOGS(ID_LOGS)" & vbCrLf
    s = s & "        ON DELETE CASCADE ON UPDATE CASCADE" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    ' Comptes
    s = s & "CREATE TABLE FT_COMPTES (" & vbCrLf
    s = s & "    ID_COMPTE    INT          NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    ALIAS_COMPTE VARCHAR(200) NOT NULL," & vbCrLf
    s = s & "    MDP_COMPTE   VARCHAR(200) NOT NULL," & vbCrLf
    s = s & "    IS_ADMIN     BOOLEAN      NOT NULL DEFAULT FALSE," & vbCrLf
    s = s & "    PRIMARY KEY (ID_COMPTE)" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    ' File d attente
    s = s & "CREATE TABLE FT_QUEUE_STATUS (" & vbCrLf
    s = s & "    ID_STATUS  TINYINT     NOT NULL," & vbCrLf
    s = s & "    NOM_STATUS VARCHAR(20) NOT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_STATUS)" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "CREATE TABLE FT_QUEUE_LOCK (" & vbCrLf
    s = s & "    ID INT NOT NULL DEFAULT 1," & vbCrLf
    s = s & "    PRIMARY KEY (ID)" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "INSERT INTO FT_QUEUE_LOCK VALUES (1);" & vbCrLf
    s = s & "INSERT INTO FT_QUEUE_STATUS VALUES (1, 'waiting'), (2, 'ready'), (3, 'done');" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "CREATE TABLE FT_QUEUE (" & vbCrLf
    s = s & "    ID_QUEUE   INT         NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    TOKEN      VARCHAR(64) NOT NULL," & vbCrLf
    s = s & "    ID_STATUS  TINYINT     NOT NULL DEFAULT 1," & vbCrLf
    s = s & "    CREATED_AT DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP," & vbCrLf
    s = s & "    STARTED_AT DATETIME    NULL DEFAULT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_QUEUE)," & vbCrLf
    s = s & "    UNIQUE KEY uk_token (TOKEN)," & vbCrLf
    s = s & "    INDEX idx_queue_status  (ID_STATUS)," & vbCrLf
    s = s & "    INDEX idx_queue_created (CREATED_AT)," & vbCrLf
    s = s & "    FOREIGN KEY (ID_STATUS) REFERENCES FT_QUEUE_STATUS(ID_STATUS)" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    ' Seuils globaux
    s = s & "CREATE TABLE FT_SEUILS (" & vbCrLf
    s = s & "    ID_SEUIL        INT          NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    NOM_SEUIL       VARCHAR(20)  NOT NULL," & vbCrLf
    s = s & "    VALEUR_BONNE    DECIMAL(8,2) NOT NULL," & vbCrLf
    s = s & "    VALEUR_MAUVAISE DECIMAL(8,2) NOT NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_SEUIL)," & vbCrLf
    s = s & "    UNIQUE KEY uk_nom_seuil (NOM_SEUIL)" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "INSERT INTO FT_SEUILS (NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE) VALUES" & vbCrLf
    s = s & "    ('ping',                    50,  90)," & vbCrLf
    s = s & "    ('download',                 5,   3)," & vbCrLf
    s = s & "    ('upload',                   5,   3)," & vbCrLf
    s = s & "    ('difference_seuil',        20,  20)," & vbCrLf
    s = s & "    ('difference_seuil_ping',   15,  15)," & vbCrLf
    s = s & "    ('difference_seuil_upload', 30,  30);" & vbCrLf
    s = s & "" & vbCrLf

    ' Seuils derogatoires par site (v1.9.8)
    s = s & "-- Seuils derogatoires par site — NULL = seuil global conserve" & vbCrLf
    s = s & "CREATE TABLE FT_SEUILS_SITE (" & vbCrLf
    s = s & "    CODE_GX_SITE         VARCHAR(10)  NOT NULL," & vbCrLf
    s = s & "    DL_VALEUR_BONNE      DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global'," & vbCrLf
    s = s & "    DL_VALEUR_MAUVAISE   DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global'," & vbCrLf
    s = s & "    UL_VALEUR_BONNE      DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global'," & vbCrLf
    s = s & "    UL_VALEUR_MAUVAISE   DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global'," & vbCrLf
    s = s & "    PING_VALEUR_BONNE    DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global'," & vbCrLf
    s = s & "    PING_VALEUR_MAUVAISE DECIMAL(8,2) DEFAULT NULL COMMENT 'NULL = seuil global'," & vbCrLf
    s = s & "    RAISON               VARCHAR(255) DEFAULT NULL COMMENT 'Motif de la derogation'," & vbCrLf
    s = s & "    DATE_MAJ             DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP," & vbCrLf
    s = s & "    MAJ_PAR              INT          DEFAULT NULL COMMENT 'FK ID_COMPTE'," & vbCrLf
    s = s & "    PRIMARY KEY (CODE_GX_SITE)," & vbCrLf
    s = s & "    CONSTRAINT fk_seuils_site_code" & vbCrLf
    s = s & "        FOREIGN KEY (CODE_GX_SITE) REFERENCES FT_SITE(CODE_GX_SITE)" & vbCrLf
    s = s & "        ON DELETE CASCADE ON UPDATE CASCADE," & vbCrLf
    s = s & "    CONSTRAINT fk_seuils_site_compte" & vbCrLf
    s = s & "        FOREIGN KEY (MAJ_PAR) REFERENCES FT_COMPTES(ID_COMPTE)" & vbCrLf
    s = s & "        ON DELETE SET NULL ON UPDATE CASCADE" & vbCrLf
    s = s & ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" & vbCrLf
    s = s & "" & vbCrLf

    ' Comptes par defaut
    s = s & "INSERT INTO FT_COMPTES (ALIAS_COMPTE, MDP_COMPTE, IS_ADMIN) VALUES" & vbCrLf
    s = s & "    ('admin', '$2y$10$REPLACE_WITH_YOUR_BCRYPT_HASH_FOR_ADMIN', true)," & vbCrLf
    s = s & "    ('tech',  '$2y$10$REPLACE_WITH_YOUR_BCRYPT_HASH_FOR_TECH', false);" & vbCrLf
    s = s & "" & vbCrLf

    ' Logs de connexion
    s = s & "CREATE TABLE FT_LOGS_CONNEXION (" & vbCrLf
    s = s & "    ID_LOG_CO    INT         NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    ID_COMPTE    INT         NOT NULL," & vbCrLf
    s = s & "    TYPE_ACCES   VARCHAR(10) NOT NULL," & vbCrLf
    s = s & "    IP_CONNEXION VARCHAR(45) NOT NULL," & vbCrLf
    s = s & "    DATE_CO      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP," & vbCrLf
    s = s & "    SUCCES       TINYINT(1)  NOT NULL DEFAULT 1," & vbCrLf
    s = s & "    PRIMARY KEY (ID_LOG_CO)," & vbCrLf
    s = s & "    INDEX idx_logco_compte (ID_COMPTE)," & vbCrLf
    s = s & "    INDEX idx_logco_date   (DATE_CO)," & vbCrLf
    s = s & "    FOREIGN KEY (ID_COMPTE) REFERENCES FT_COMPTES(ID_COMPTE)" & vbCrLf
    s = s & "        ON DELETE CASCADE ON UPDATE CASCADE" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf

    ' Config speedtest
    s = s & "CREATE TABLE FT_CONFIG_SPEEDTEST (" & vbCrLf
    s = s & "    CLE_CONFIG     VARCHAR(60)   NOT NULL," & vbCrLf
    s = s & "    VALEUR_CONFIG  DECIMAL(10,3) NOT NULL," & vbCrLf
    s = s & "    LIBELLE        VARCHAR(120)  NOT NULL," & vbCrLf
    s = s & "    UNITE          VARCHAR(20)   NOT NULL DEFAULT ''," & vbCrLf
    s = s & "    DATE_MAJ       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP," & vbCrLf
    s = s & "    PRIMARY KEY (CLE_CONFIG)" & vbCrLf
    s = s & ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & InsertConfigSpeedtestSQL()
    s = s & "" & vbCrLf

    ' Audit des modifications de sites (v1.9.2)
    s = s & "-- Historique des ajouts / modifications / suppressions de sites" & vbCrLf
    s = s & "CREATE TABLE FT_AUDIT_SITES (" & vbCrLf
    s = s & "    ID_AUDIT    INT                                       NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    ID_COMPTE   INT                                       NOT NULL," & vbCrLf
    s = s & "    ACTION      ENUM('AJOUT','MODIFICATION','SUPPRESSION') NOT NULL," & vbCrLf
    s = s & "    ID_SITE     VARCHAR(10)                               NOT NULL," & vbCrLf
    s = s & "    NOM_SITE    VARCHAR(255)                              NOT NULL," & vbCrLf
    s = s & "    DATE_ACTION DATETIME                                  NOT NULL DEFAULT CURRENT_TIMESTAMP," & vbCrLf
    s = s & "    IP_ACTION   VARCHAR(45)                               NOT NULL," & vbCrLf
    s = s & "    DETAIL      TEXT                                      NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_AUDIT)," & vbCrLf
    s = s & "    INDEX idx_audit_compte (ID_COMPTE)," & vbCrLf
    s = s & "    INDEX idx_audit_site   (ID_SITE)," & vbCrLf
    s = s & "    INDEX idx_audit_date   (DATE_ACTION)," & vbCrLf
    s = s & "    INDEX idx_audit_action (ACTION)," & vbCrLf
    s = s & "    FOREIGN KEY (ID_COMPTE) REFERENCES FT_COMPTES(ID_COMPTE)" & vbCrLf
    s = s & "        ON DELETE RESTRICT ON UPDATE CASCADE" & vbCrLf
    s = s & ");" & vbCrLf

    SaveUTF8 outDir & "\create_database.sql", s
    MsgBox "Schema exporte dans " & outDir & "\create_database.sql", vbInformation, "Export termine"

End Sub


' ====================================================================
' MIGRATION — BDD EXISTANTE
' ====================================================================

Sub ExportMigrationAuditSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Dim s As String
    s = ""
    s = s & "-- Migration v1.9.2 -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "-- A jouer UNE SEULE FOIS sur une BDD existante." & vbCrLf
    s = s & "-- Cree FT_AUDIT_SITES si absente." & vbCrLf
    s = s & "-- Corrige ID_SITE en VARCHAR(10) si la table existait deja avec INT." & vbCrLf
    s = s & "-- Ajoute les trois seuils difference_seuil_ping / _upload si absents." & vbCrLf
    s = s & "" & vbCrLf
    s = s & "USE ft_speedtest;" & vbCrLf
    s = s & "" & vbCrLf

    s = s & "CREATE TABLE IF NOT EXISTS FT_AUDIT_SITES (" & vbCrLf
    s = s & "    ID_AUDIT    INT                                       NOT NULL AUTO_INCREMENT," & vbCrLf
    s = s & "    ID_COMPTE   INT                                       NOT NULL," & vbCrLf
    s = s & "    ACTION      ENUM('AJOUT','MODIFICATION','SUPPRESSION') NOT NULL," & vbCrLf
    s = s & "    ID_SITE     VARCHAR(10)                               NOT NULL," & vbCrLf
    s = s & "    NOM_SITE    VARCHAR(255)                              NOT NULL," & vbCrLf
    s = s & "    DATE_ACTION DATETIME                                  NOT NULL DEFAULT CURRENT_TIMESTAMP," & vbCrLf
    s = s & "    IP_ACTION   VARCHAR(45)                               NOT NULL," & vbCrLf
    s = s & "    DETAIL      TEXT                                      NULL," & vbCrLf
    s = s & "    PRIMARY KEY (ID_AUDIT)," & vbCrLf
    s = s & "    INDEX idx_audit_compte (ID_COMPTE)," & vbCrLf
    s = s & "    INDEX idx_audit_site   (ID_SITE)," & vbCrLf
    s = s & "    INDEX idx_audit_date   (DATE_ACTION)," & vbCrLf
    s = s & "    INDEX idx_audit_action (ACTION)," & vbCrLf
    s = s & "    FOREIGN KEY (ID_COMPTE) REFERENCES FT_COMPTES(ID_COMPTE)" & vbCrLf
    s = s & "        ON DELETE RESTRICT ON UPDATE CASCADE" & vbCrLf
    s = s & ");" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "ALTER TABLE FT_AUDIT_SITES MODIFY ID_SITE VARCHAR(10) NOT NULL;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "INSERT IGNORE INTO FT_SEUILS (NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE) VALUES" & vbCrLf
    s = s & "    ('difference_seuil_ping',   15, 15)," & vbCrLf
    s = s & "    ('difference_seuil_upload', 30, 30);" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "ALTER TABLE FT_LOGS" & vbCrLf
    s = s & "    ADD COLUMN IF NOT EXISTS MODE       VARCHAR(10) NOT NULL DEFAULT 'precise' AFTER UPLOAD_LOGS," & vbCrLf
    s = s & "    ADD COLUMN IF NOT EXISTS SESSION_ID VARCHAR(64) NULL     AFTER MODE;" & vbCrLf

    SaveUTF8 outDir & "\migration_audit.sql", s
    MsgBox "Migration exportee dans " & outDir & "\migration_audit.sql", vbInformation, "Export termine"

End Sub


' ====================================================================
' EXPORTS DONNEES
' ====================================================================

Sub ExportAuditSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Dim s As String
    s = ""
    s = s & "-- Export des donnees FT_AUDIT_SITES -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "-- Ce fichier est genere manuellement via phpMyAdmin > Export > SQL" & vbCrLf
    s = s & "-- La macro VBA ne peut pas lire la BDD directement." & vbCrLf
    s = s & "-- Procedure :" & vbCrLf
    s = s & "--   1. phpMyAdmin > ft_speedtest > FT_AUDIT_SITES > Exporter" & vbCrLf
    s = s & "--   2. Format SQL, sans structure (donnees seulement)" & vbCrLf
    s = s & "--   3. Enregistrer sous export_audit.sql" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "USE ft_speedtest;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "-- Inserer ici le contenu genere par phpMyAdmin." & vbCrLf

    SaveUTF8 outDir & "\export_audit.sql", s
    MsgBox "Modele export audit genere dans " & outDir & "\export_audit.sql" & vbCrLf & _
           "Completer avec phpMyAdmin > FT_AUDIT_SITES > Exporter.", vbInformation, "Export termine"

End Sub


Sub ExportSeuilsSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Dim s As String
    s = ""
    s = s & "-- Seuils de qualite reseau -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "-- Idempotent : INSERT ... ON DUPLICATE KEY UPDATE" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "USE ft_speedtest;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "INSERT INTO FT_SEUILS (NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE) VALUES" & vbCrLf
    s = s & "    ('ping',                    50,  90)," & vbCrLf
    s = s & "    ('download',                 5,   3)," & vbCrLf
    s = s & "    ('upload',                   5,   3)," & vbCrLf
    s = s & "    ('difference_seuil',        20,  20)," & vbCrLf
    s = s & "    ('difference_seuil_ping',   15,  15)," & vbCrLf
    s = s & "    ('difference_seuil_upload', 30,  30)" & vbCrLf
    s = s & "    ON DUPLICATE KEY UPDATE" & vbCrLf
    s = s & "        VALEUR_BONNE    = VALUES(VALEUR_BONNE)," & vbCrLf
    s = s & "        VALEUR_MAUVAISE = VALUES(VALEUR_MAUVAISE);" & vbCrLf

    SaveUTF8 outDir & "\update_seuils.sql", s
    MsgBox "Seuils exportes dans " & outDir & "\update_seuils.sql", vbInformation, "Export termine"

End Sub


' ------------------------------------------------------------
' InsertConfigSpeedtestSQL
' Retourne le SQL d insertion des 17 parametres du moteur
' (modes precise x 8 + fast x 8 + debit_max_mbitps)
' Valeurs alignees sur FT_CONFIG_SPEEDTEST en production (v1.10.0)
' ------------------------------------------------------------
Function InsertConfigSpeedtestSQL() As String

    Dim s As String
    s = ""

    s = s & "INSERT INTO FT_CONFIG_SPEEDTEST (CLE_CONFIG, VALEUR_CONFIG, LIBELLE, UNITE) VALUES" & vbCrLf

    ' -- Mode precis (8 params) -----------------------------------------------
    s = s & "    ('precise_ping_prechauffage',          3,    'Precis - Ping : requetes de prechauffage',  '-')," & vbCrLf
    s = s & "    ('precise_ping_echantillons',           8,    'Precis - Ping : nombre de mesures',         '-')," & vbCrLf
    s = s & "    ('precise_ping_delay',                 50,    'Precis - Ping : delai entre mesures',       'ms')," & vbCrLf
    s = s & "    ('precise_download_dureeMsDownload',   4000,  'Precis - Download : duree de mesure',       'ms')," & vbCrLf
    s = s & "    ('precise_download_parallel',          1,     'Precis - Download : requetes en parallele', '-')," & vbCrLf
    s = s & "    ('precise_upload_dureeMsUpload',       4000,  'Precis - Upload : duree de mesure',         'ms')," & vbCrLf
    s = s & "    ('precise_upload_parallel',            1,     'Precis - Upload : requetes en parallele',   '-')," & vbCrLf
    s = s & "    ('precise_upload_tailleMoBlob',        12,    'Precis - Upload : taille blob envoye',      'Mo')," & vbCrLf

    ' -- Mode rapide (8 params) -----------------------------------------------
    s = s & "    ('fast_ping_prechauffage',             1,     'Rapide - Ping : requetes de prechauffage',  '-')," & vbCrLf
    s = s & "    ('fast_ping_echantillons',             5,     'Rapide - Ping : nombre de mesures',         '-')," & vbCrLf
    s = s & "    ('fast_ping_delay',                    30,    'Rapide - Ping : delai entre mesures',       'ms')," & vbCrLf
    s = s & "    ('fast_download_dureeMsDownload',      5000,  'Rapide - Download : duree de mesure',       'ms')," & vbCrLf
    s = s & "    ('fast_download_parallel',             1,     'Rapide - Download : requetes en parallele', '-')," & vbCrLf
    s = s & "    ('fast_upload_dureeMsUpload',          5000,  'Rapide - Upload : duree de mesure',         'ms')," & vbCrLf
    s = s & "    ('fast_upload_parallel',               1,     'Rapide - Upload : requetes en parallele',   '-')," & vbCrLf
    s = s & "    ('fast_upload_tailleMoBlob',           25,    'Rapide - Upload : taille blob envoye',      'Mo')," & vbCrLf

    ' -- Parametre global -----------------------------------------------------
    s = s & "    ('debit_max_mbitps',                   1000,  'Plafond de sanite - 0 = desactive',         'Mbit/s')" & vbCrLf
    s = s & "    ON DUPLICATE KEY UPDATE" & vbCrLf
    s = s & "        VALEUR_CONFIG = VALUES(VALEUR_CONFIG)," & vbCrLf
    s = s & "        LIBELLE       = VALUES(LIBELLE)," & vbCrLf
    s = s & "        UNITE         = VALUES(UNITE);" & vbCrLf

    InsertConfigSpeedtestSQL = s

End Function


Sub ExportConfigSpeedtest()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Dim s As String
    s = ""
    s = s & "-- Parametres du moteur speedtest -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "-- 17 entrees : modes precise (8) + fast (8) + debit_max_mbitps (1)" & vbCrLf
    s = s & "-- INSERT ... ON DUPLICATE KEY UPDATE : idempotent." & vbCrLf
    s = s & "" & vbCrLf
    s = s & "USE ft_speedtest;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & InsertConfigSpeedtestSQL()

    SaveUTF8 outDir & "\insert_config_speedtest.sql", s
    MsgBox "Config speedtest exportee dans " & outDir & "\insert_config_speedtest.sql", vbInformation, "Export termine"

End Sub


' ====================================================================
' EXPORTS DONNEES SITES (depuis Excel)
' ====================================================================

Sub ExportCleanSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Dim s As String
    s = ""
    s = s & "-- Suppression de toutes les tables -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "-- ATTENTION : supprime toutes les donnees." & vbCrLf
    s = s & "" & vbCrLf
    s = s & "SET FOREIGN_KEY_CHECKS = 0;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_AUDIT_SITES;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_SEUILS_SITE;" & vbCrLf      ' v1.9.8
    s = s & "DROP TABLE IF EXISTS FT_LOGS_CONNEXION;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_QUEUE;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_QUEUE_STATUS;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_QUEUE_LOCK;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_COMMENTAIRES;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_LOGS;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_SITE;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_DEPARTEMENT;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_REGION;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_INTERREGION;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_SEUILS;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_COMPTES;" & vbCrLf
    s = s & "DROP TABLE IF EXISTS FT_CONFIG_SPEEDTEST;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "SET FOREIGN_KEY_CHECKS = 1;" & vbCrLf

    SaveUTF8 outDir & "\clean.sql", s
    MsgBox "Clean exporte dans " & outDir & "\clean.sql", vbInformation, "Export termine"

End Sub


Sub ExportInterregionSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Const COL_SDP As Integer = 4

    Dim ws As Worksheet
    Set ws = ActiveSheet

    Dim dict As Object
    Set dict = CreateObject("Scripting.Dictionary")

    Dim lastRow As Long
    lastRow = ws.Cells(ws.Rows.Count, 1).End(xlUp).Row

    Dim s As String
    s = ""
    s = s & "-- Interregions (SDP) -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "" & vbCrLf

    Dim i      As Long
    Dim sdp    As String
    Dim sdpEsc As String
    For i = 2 To lastRow
        sdp = Trim(ws.Cells(i, COL_SDP).Value)
        If sdp <> "" And Not dict.Exists(sdp) Then
            dict.Add sdp, True
            sdpEsc = Replace(sdp, "'", "''")
            s = s & "INSERT INTO FT_INTERREGION (NOM_INTERREGION) VALUES ('" & sdpEsc & "')" & vbCrLf
            s = s & "    ON DUPLICATE KEY UPDATE NOM_INTERREGION = VALUES(NOM_INTERREGION);" & vbCrLf
        End If
    Next i

    s = s & "" & vbCrLf
    s = s & "INSERT INTO FT_INTERREGION (NOM_INTERREGION) VALUES ('Teletravail')" & vbCrLf
    s = s & "    ON DUPLICATE KEY UPDATE NOM_INTERREGION = VALUES(NOM_INTERREGION);" & vbCrLf

    SaveUTF8 outDir & "\insert_interregions.sql", s
    MsgBox "Interregions exportees dans " & outDir & "\insert_interregions.sql", vbInformation, "Export termine"

End Sub


Sub ExportRegionSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Const COL_DIR As Integer = 3
    Const COL_SDP As Integer = 4

    Dim ws As Worksheet
    Set ws = ActiveSheet

    Dim dict As Object
    Set dict = CreateObject("Scripting.Dictionary")

    Dim lastRow As Long
    lastRow = ws.Cells(ws.Rows.Count, 1).End(xlUp).Row

    Dim s As String
    s = ""
    s = s & "-- Regions (Directions) -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "" & vbCrLf

    Dim i      As Long
    Dim dirVal As String
    Dim sdpVal As String
    Dim dirEsc As String
    Dim sdpEsc As String
    For i = 2 To lastRow
        dirVal = Trim(ws.Cells(i, COL_DIR).Value)
        sdpVal = Trim(ws.Cells(i, COL_SDP).Value)
        If dirVal <> "" And sdpVal <> "" And Not dict.Exists(dirVal) Then
            dict.Add dirVal, True
            dirEsc = Replace(dirVal, "'", "''")
            sdpEsc = Replace(sdpVal, "'", "''")
            s = s & "INSERT INTO FT_REGION (NOM_REGION, ID_INTERREGION)" & vbCrLf
            s = s & "    SELECT '" & dirEsc & "', ID_INTERREGION FROM FT_INTERREGION WHERE NOM_INTERREGION = '" & sdpEsc & "'" & vbCrLf
            s = s & "    ON DUPLICATE KEY UPDATE NOM_REGION = VALUES(NOM_REGION);" & vbCrLf
        End If
    Next i

    s = s & "" & vbCrLf
    s = s & "INSERT INTO FT_REGION (NOM_REGION, ID_INTERREGION)" & vbCrLf
    s = s & "    SELECT 'Teletravail', ID_INTERREGION FROM FT_INTERREGION WHERE NOM_INTERREGION = 'Teletravail'" & vbCrLf
    s = s & "    ON DUPLICATE KEY UPDATE NOM_REGION = VALUES(NOM_REGION);" & vbCrLf

    SaveUTF8 outDir & "\insert_regions.sql", s
    MsgBox "Regions exportees dans " & outDir & "\insert_regions.sql", vbInformation, "Export termine"

End Sub


Sub ExportDepartementSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Const COL_DIR  As Integer = 3
    Const COL_DEPT As Integer = 27

    Dim ws As Worksheet
    Set ws = ActiveSheet

    Dim dict As Object
    Set dict = CreateObject("Scripting.Dictionary")

    Dim lastRow As Long
    lastRow = ws.Cells(ws.Rows.Count, 1).End(xlUp).Row

    Dim s As String
    s = ""
    s = s & "-- Departements -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "" & vbCrLf

    Dim i       As Long
    Dim numDept As String
    Dim dirVal  As String
    Dim nomDept As String
    Dim dirEsc  As String
    Dim numEsc  As String
    Dim nomEsc  As String
    For i = 2 To lastRow
        numDept = Trim(ws.Cells(i, COL_DEPT).Value)
        dirVal = Trim(ws.Cells(i, COL_DIR).Value)
        If numDept <> "" And dirVal <> "" And Not dict.Exists(numDept) Then
            dict.Add numDept, True
            nomDept = NomDepartement(numDept)
            dirEsc = Replace(dirVal, "'", "''")
            numEsc = Replace(numDept, "'", "''")
            nomEsc = Replace(nomDept, "'", "''")
            s = s & "INSERT INTO FT_DEPARTEMENT (NUM_DEPARTEMENT, NOM_DEPARTEMENT, ID_REGION)" & vbCrLf
            s = s & "    SELECT '" & numEsc & "', '" & nomEsc & "', ID_REGION FROM FT_REGION WHERE NOM_REGION = '" & dirEsc & "'" & vbCrLf
            s = s & "    ON DUPLICATE KEY UPDATE NOM_DEPARTEMENT = VALUES(NOM_DEPARTEMENT);" & vbCrLf
        End If
    Next i

    s = s & "" & vbCrLf
    s = s & "INSERT INTO FT_DEPARTEMENT (NUM_DEPARTEMENT, NOM_DEPARTEMENT, ID_REGION)" & vbCrLf
    s = s & "    SELECT 'TT', 'Teletravail', ID_REGION FROM FT_REGION WHERE NOM_REGION = 'Teletravail'" & vbCrLf
    s = s & "    ON DUPLICATE KEY UPDATE NOM_DEPARTEMENT = VALUES(NOM_DEPARTEMENT);" & vbCrLf

    SaveUTF8 outDir & "\insert_departements.sql", s
    MsgBox "Departements exportes dans " & outDir & "\insert_departements.sql", vbInformation, "Export termine"

End Sub


Sub ExportSiteSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Const COL_CODE  As Integer = 1
    Const COL_NOM   As Integer = 2
    Const COL_ADR1  As Integer = 7
    Const COL_ADR2  As Integer = 8
    Const COL_CP    As Integer = 9
    Const COL_VILLE As Integer = 10
    Const COL_IP    As Integer = 20
    Const COL_DEPT  As Integer = 27

    Dim ws As Worksheet
    Set ws = ActiveSheet

    Dim lastRow As Long
    lastRow = ws.Cells(ws.Rows.Count, 1).End(xlUp).Row

    Dim s       As String
    Dim skipped As Long
    s = ""
    skipped = 0

    s = s & "-- Sites -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "-- LATITUDE et LONGITUDE restent NULL -- a remplir via geocodage.php" & vbCrLf
    s = s & "" & vbCrLf

    Dim i          As Long
    Dim codeGX     As String
    Dim nomSite    As String
    Dim adr1       As String
    Dim adr2       As String
    Dim adresse    As String
    Dim cp         As String
    Dim ville      As String
    Dim ipRaw      As String
    Dim numDept    As String
    Dim ipReseau   As String
    Dim masque     As String
    Dim ipSpeciale As Integer
    Dim codeEsc    As String
    Dim nomEsc     As String
    Dim adresseEsc As String
    Dim villeEsc   As String
    Dim deptEsc    As String
    Dim adresseVal As String
    Dim villeVal   As String

    For i = 2 To lastRow
        codeGX = Trim(ws.Cells(i, COL_CODE).Value)
        nomSite = Trim(ws.Cells(i, COL_NOM).Value)
        adr1 = Trim(ws.Cells(i, COL_ADR1).Value)
        adr2 = Trim(ws.Cells(i, COL_ADR2).Value)
        cp = Trim(ws.Cells(i, COL_CP).Value)
        ville = Trim(ws.Cells(i, COL_VILLE).Value)
        ipRaw = Trim(ws.Cells(i, COL_IP).Value)
        numDept = Trim(ws.Cells(i, COL_DEPT).Value)

        If adr2 <> "" Then adresse = Trim(adr1 & " " & adr2) Else adresse = adr1

        If codeGX = "" Or numDept = "" Then
            s = s & "-- IGNORE ligne " & i & " : code=" & codeGX & " dept=" & numDept & vbCrLf
            skipped = skipped + 1
        Else
            If Left(UCase(codeGX), 2) <> "GX" Then codeGX = "GX" & codeGX
            cp = Format(Val(cp), "00000")

            ipReseau = "NULL"
            masque = "NULL"
            ipSpeciale = 0

            If ipRaw = "" Then
                ipSpeciale = 1
            ElseIf InStr(ipRaw, "/") > 0 Then
                Dim parts() As String
                parts = Split(ipRaw, "/")
                ipReseau = "'" & Trim(parts(0)) & "'"
                Dim masqueRaw As String
                masqueRaw = Trim(parts(1))
                Dim j     As Integer
                masque = ""
                For j = 1 To Len(masqueRaw)
                    If Mid(masqueRaw, j, 1) >= "0" And Mid(masqueRaw, j, 1) <= "9" Then
                        masque = masque & Mid(masqueRaw, j, 1)
                    Else
                        Exit For
                    End If
                Next j
                If masque = "" Then
                    ipSpeciale = 1
                    ipReseau = "NULL"
                    masque = "NULL"
                End If
            Else
                ipSpeciale = 1
            End If

            codeEsc = Replace(codeGX, "'", "''")
            nomEsc = Replace(nomSite, "'", "''")
            adresseEsc = Replace(adresse, "'", "''")
            villeEsc = Replace(ville, "'", "''")
            deptEsc = Replace(numDept, "'", "''")

            If adresseEsc <> "" Then adresseVal = "'" & adresseEsc & "'" Else adresseVal = "NULL"
            If villeEsc <> "" Then villeVal = "'" & villeEsc & "'" Else villeVal = "NULL"

            s = s & "INSERT INTO FT_SITE (CODE_GX_SITE, NOM_SITE, CODE_POSTAL, ADRESSE, VILLE," & vbCrLf
            s = s & "                    LATITUDE, LONGITUDE, IP_SPECIALE, IP_RESEAU, MASQUE_SITE, ID_DEPARTEMENT)" & vbCrLf
            s = s & "    SELECT '" & codeEsc & "', '" & nomEsc & "', '" & cp & "', " & adresseVal & ", " & villeVal & "," & vbCrLf
            s = s & "           NULL, NULL, " & ipSpeciale & ", " & ipReseau & ", " & masque & "," & vbCrLf
            s = s & "           ID_DEPARTEMENT FROM FT_DEPARTEMENT WHERE NUM_DEPARTEMENT = '" & deptEsc & "'" & vbCrLf
            s = s & "    ON DUPLICATE KEY UPDATE" & vbCrLf
            s = s & "        NOM_SITE    = VALUES(NOM_SITE)," & vbCrLf
            s = s & "        CODE_POSTAL = VALUES(CODE_POSTAL)," & vbCrLf
            s = s & "        ADRESSE     = VALUES(ADRESSE)," & vbCrLf
            s = s & "        VILLE       = VALUES(VILLE)," & vbCrLf
            s = s & "        IP_SPECIALE = VALUES(IP_SPECIALE)," & vbCrLf
            s = s & "        IP_RESEAU   = VALUES(IP_RESEAU)," & vbCrLf
            s = s & "        MASQUE_SITE = VALUES(MASQUE_SITE);" & vbCrLf
        End If
    Next i

    s = s & "" & vbCrLf
    s = s & "-- Cas special teletravail" & vbCrLf
    s = s & "INSERT INTO FT_SITE (CODE_GX_SITE, NOM_SITE, CODE_POSTAL, ADRESSE, VILLE," & vbCrLf
    s = s & "                    LATITUDE, LONGITUDE, IP_SPECIALE, IP_RESEAU, MASQUE_SITE, ID_DEPARTEMENT)" & vbCrLf
    s = s & "    SELECT 'GXTELETRAVAIL', 'Teletravail', '00000', NULL, NULL," & vbCrLf
    s = s & "           NULL, NULL, 0, '172.0.0.0', 8, ID_DEPARTEMENT" & vbCrLf
    s = s & "    FROM FT_DEPARTEMENT WHERE NUM_DEPARTEMENT = 'TT'" & vbCrLf
    s = s & "    ON DUPLICATE KEY UPDATE NOM_SITE = VALUES(NOM_SITE);" & vbCrLf

    SaveUTF8 outDir & "\insert_sites.sql", s
    MsgBox "Sites exportes dans " & outDir & "\insert_sites.sql (" & skipped & " lignes ignorees)", _
           vbInformation, "Export termine"

End Sub


Sub ExportIndexSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Dim s As String
    s = ""
    s = s & "-- Index sur FT_LOGS -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "" & vbCrLf
    s = s & "USE ft_speedtest;" & vbCrLf
    s = s & "" & vbCrLf
    s = s & "CREATE INDEX IF NOT EXISTS idx_logs_site      ON FT_LOGS (CODE_GX_SITE);" & vbCrLf
    s = s & "CREATE INDEX IF NOT EXISTS idx_logs_date      ON FT_LOGS (DATE_LOGS);" & vbCrLf
    s = s & "CREATE INDEX IF NOT EXISTS idx_logs_site_date ON FT_LOGS (CODE_GX_SITE, DATE_LOGS);" & vbCrLf
    s = s & "CREATE INDEX IF NOT EXISTS idx_logs_ip_client ON FT_LOGS (IP_CLIENT);" & vbCrLf
    s = s & "CREATE INDEX IF NOT EXISTS idx_logs_mode      ON FT_LOGS (MODE);" & vbCrLf
    s = s & "CREATE INDEX IF NOT EXISTS idx_logs_session   ON FT_LOGS (SESSION_ID);" & vbCrLf

    SaveUTF8 outDir & "\indexes.sql", s
    MsgBox "Index exportes dans " & outDir & "\indexes.sql", vbInformation, "Export termine"

End Sub


Sub UpdateSiteSQL()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    Const COL_CODE  As Integer = 1
    Const COL_NOM   As Integer = 2
    Const COL_ADR1  As Integer = 7
    Const COL_ADR2  As Integer = 8
    Const COL_CP    As Integer = 9
    Const COL_VILLE As Integer = 10
    Const COL_IP    As Integer = 20
    Const COL_DEPT  As Integer = 27

    Dim ws As Worksheet
    Set ws = ActiveSheet

    Dim lastRow As Long
    lastRow = ws.Cells(ws.Rows.Count, 1).End(xlUp).Row

    Dim s       As String
    Dim updated As Long
    Dim skipped As Long
    s = ""
    updated = 0
    skipped = 0

    s = s & "-- Mise a jour des sites existants -- genere le " & Format(Now, "dd/mm/yyyy hh:mm:ss") & vbCrLf
    s = s & "-- Seuls les sites dont le CODE_GX_SITE existe deja en base sont mis a jour." & vbCrLf
    s = s & "-- LATITUDE et LONGITUDE ne sont pas modifiees (geocodage preserve)." & vbCrLf
    s = s & "" & vbCrLf
    s = s & "USE ft_speedtest;" & vbCrLf
    s = s & "" & vbCrLf

    Dim i          As Long
    Dim codeGX     As String
    Dim nomSite    As String
    Dim adr1       As String
    Dim adr2       As String
    Dim adresse    As String
    Dim cp         As String
    Dim ville      As String
    Dim ipRaw      As String
    Dim numDept    As String
    Dim ipReseau   As String
    Dim masque     As String
    Dim ipSpeciale As Integer
    Dim codeEsc    As String
    Dim nomEsc     As String
    Dim adresseEsc As String
    Dim villeEsc   As String
    Dim adresseVal As String
    Dim villeVal   As String
    Dim deptUpdate As String

    For i = 2 To lastRow
        codeGX = Trim(ws.Cells(i, COL_CODE).Value)
        nomSite = Trim(ws.Cells(i, COL_NOM).Value)
        adr1 = Trim(ws.Cells(i, COL_ADR1).Value)
        adr2 = Trim(ws.Cells(i, COL_ADR2).Value)
        cp = Trim(ws.Cells(i, COL_CP).Value)
        ville = Trim(ws.Cells(i, COL_VILLE).Value)
        ipRaw = Trim(ws.Cells(i, COL_IP).Value)
        numDept = Trim(ws.Cells(i, COL_DEPT).Value)

        If codeGX = "" Then
            s = s & "-- IGNORE ligne " & i & " : pas de CODE_GX_SITE" & vbCrLf
            skipped = skipped + 1
        Else
            If Left(UCase(codeGX), 2) <> "GX" Then codeGX = "GX" & codeGX
            If adr2 <> "" Then adresse = Trim(adr1 & " " & adr2) Else adresse = adr1
            cp = Format(Val(cp), "00000")

            ipReseau = "NULL"
            masque = "NULL"
            ipSpeciale = 0

            If ipRaw = "" Then
                ipSpeciale = 1
            ElseIf InStr(ipRaw, "/") > 0 Then
                Dim parts() As String
                parts = Split(ipRaw, "/")
                ipReseau = "'" & Trim(parts(0)) & "'"
                Dim masqueRaw As String
                masqueRaw = Trim(parts(1))
                Dim j     As Integer
                masque = ""
                For j = 1 To Len(masqueRaw)
                    If Mid(masqueRaw, j, 1) >= "0" And Mid(masqueRaw, j, 1) <= "9" Then
                        masque = masque & Mid(masqueRaw, j, 1)
                    Else
                        Exit For
                    End If
                Next j
                If masque = "" Then
                    ipSpeciale = 1
                    ipReseau = "NULL"
                    masque = "NULL"
                End If
            Else
                ipSpeciale = 1
            End If

            codeEsc = Replace(codeGX, "'", "''")
            nomEsc = Replace(nomSite, "'", "''")
            adresseEsc = Replace(adresse, "'", "''")
            villeEsc = Replace(ville, "'", "''")

            If adresseEsc <> "" Then adresseVal = "'" & adresseEsc & "'" Else adresseVal = "NULL"
            If villeEsc <> "" Then villeVal = "'" & villeEsc & "'" Else villeVal = "NULL"

            If numDept <> "" Then
                Dim deptEsc As String
                deptEsc = Replace(numDept, "'", "''")
                deptUpdate = "," & vbCrLf & _
                    "        ID_DEPARTEMENT = (SELECT ID_DEPARTEMENT FROM FT_DEPARTEMENT WHERE NUM_DEPARTEMENT = '" & deptEsc & "')"
            Else
                deptUpdate = ""
            End If

            s = s & "UPDATE FT_SITE SET" & vbCrLf
            s = s & "        NOM_SITE    = '" & nomEsc & "'," & vbCrLf
            s = s & "        CODE_POSTAL = '" & cp & "'," & vbCrLf
            s = s & "        ADRESSE     = " & adresseVal & "," & vbCrLf
            s = s & "        VILLE       = " & villeVal & "," & vbCrLf
            s = s & "        IP_SPECIALE = " & ipSpeciale & "," & vbCrLf
            s = s & "        IP_RESEAU   = " & ipReseau & "," & vbCrLf
            s = s & "        MASQUE_SITE = " & masque & deptUpdate & vbCrLf
            s = s & "    WHERE CODE_GX_SITE = '" & codeEsc & "';" & vbCrLf
            s = s & "" & vbCrLf
            updated = updated + 1
        End If
    Next i

    SaveUTF8 outDir & "\update_sites.sql", s
    MsgBox "Mise a jour exportee dans " & outDir & "\update_sites.sql" & vbCrLf & _
           updated & " site(s), " & skipped & " ligne(s) ignoree(s).", vbInformation, "Export termine"

End Sub


' ====================================================================
' EXPORT COMPLET
' ====================================================================

Sub ExportTout()

    Dim outDir As String
    outDir = GetOutputFolder()
    If outDir = "" Then MsgBox "Operation annulee.", vbInformation: Exit Sub

    ExportCreateDatabase
    ExportCleanSQL
    ExportInterregionSQL
    ExportRegionSQL
    ExportDepartementSQL
    ExportSiteSQL
    ExportIndexSQL
    ExportConfigSpeedtest
    ExportSeuilsSQL
    UpdateSiteSQL
    ExportMigrationAuditSQL

    MsgBox "Export complet termine dans " & outDir & "\" & vbCrLf & vbCrLf & _
           "Ordre d execution dans phpMyAdmin :" & vbCrLf & _
           "  0. create_database.sql" & vbCrLf & _
           "  1. clean.sql (si reimport complet)" & vbCrLf & _
           "  2. insert_interregions.sql" & vbCrLf & _
           "  3. insert_regions.sql" & vbCrLf & _
           "  4. insert_departements.sql" & vbCrLf & _
           "  5. insert_sites.sql" & vbCrLf & _
           "  6. indexes.sql" & vbCrLf & _
           "  7. insert_config_speedtest.sql" & vbCrLf & _
           "  8. update_seuils.sql" & vbCrLf & _
           "  [migration uniquement] migration_audit.sql", _
           vbInformation, "ExportTout"

End Sub


' ====================================================================
' REFERENTIEL DEPARTEMENTS
' ====================================================================

Function NomDepartement(num As String) As String
    Select Case UCase(Trim(num))
        Case "01":  NomDepartement = "Ain"
        Case "02":  NomDepartement = "Aisne"
        Case "03":  NomDepartement = "Allier"
        Case "04":  NomDepartement = "Alpes-de-Haute-Provence"
        Case "05":  NomDepartement = "Hautes-Alpes"
        Case "06":  NomDepartement = "Alpes-Maritimes"
        Case "07":  NomDepartement = "Ardeche"
        Case "08":  NomDepartement = "Ardennes"
        Case "09":  NomDepartement = "Ariege"
        Case "10":  NomDepartement = "Aube"
        Case "11":  NomDepartement = "Aude"
        Case "12":  NomDepartement = "Aveyron"
        Case "13":  NomDepartement = "Bouches-du-Rhone"
        Case "14":  NomDepartement = "Calvados"
        Case "15":  NomDepartement = "Cantal"
        Case "16":  NomDepartement = "Charente"
        Case "17":  NomDepartement = "Charente-Maritime"
        Case "18":  NomDepartement = "Cher"
        Case "19":  NomDepartement = "Correze"
        Case "2A":  NomDepartement = "Corse-du-Sud"
        Case "2B":  NomDepartement = "Haute-Corse"
        Case "21":  NomDepartement = "Cote-d Or"
        Case "22":  NomDepartement = "Cotes-d Armor"
        Case "23":  NomDepartement = "Creuse"
        Case "24":  NomDepartement = "Dordogne"
        Case "25":  NomDepartement = "Doubs"
        Case "26":  NomDepartement = "Drome"
        Case "27":  NomDepartement = "Eure"
        Case "28":  NomDepartement = "Eure-et-Loir"
        Case "29":  NomDepartement = "Finistere"
        Case "30":  NomDepartement = "Gard"
        Case "31":  NomDepartement = "Haute-Garonne"
        Case "32":  NomDepartement = "Gers"
        Case "33":  NomDepartement = "Gironde"
        Case "34":  NomDepartement = "Herault"
        Case "35":  NomDepartement = "Ille-et-Vilaine"
        Case "36":  NomDepartement = "Indre"
        Case "37":  NomDepartement = "Indre-et-Loire"
        Case "38":  NomDepartement = "Isere"
        Case "39":  NomDepartement = "Jura"
        Case "40":  NomDepartement = "Landes"
        Case "41":  NomDepartement = "Loir-et-Cher"
        Case "42":  NomDepartement = "Loire"
        Case "43":  NomDepartement = "Haute-Loire"
        Case "44":  NomDepartement = "Loire-Atlantique"
        Case "45":  NomDepartement = "Loiret"
        Case "46":  NomDepartement = "Lot"
        Case "47":  NomDepartement = "Lot-et-Garonne"
        Case "48":  NomDepartement = "Lozere"
        Case "49":  NomDepartement = "Maine-et-Loire"
        Case "50":  NomDepartement = "Manche"
        Case "51":  NomDepartement = "Marne"
        Case "52":  NomDepartement = "Haute-Marne"
        Case "53":  NomDepartement = "Mayenne"
        Case "54":  NomDepartement = "Meurthe-et-Moselle"
        Case "55":  NomDepartement = "Meuse"
        Case "56":  NomDepartement = "Morbihan"
        Case "57":  NomDepartement = "Moselle"
        Case "58":  NomDepartement = "Nievre"
        Case "59":  NomDepartement = "Nord"
        Case "60":  NomDepartement = "Oise"
        Case "61":  NomDepartement = "Orne"
        Case "62":  NomDepartement = "Pas-de-Calais"
        Case "63":  NomDepartement = "Puy-de-Dome"
        Case "64":  NomDepartement = "Pyrenees-Atlantiques"
        Case "65":  NomDepartement = "Hautes-Pyrenees"
        Case "66":  NomDepartement = "Pyrenees-Orientales"
        Case "67":  NomDepartement = "Bas-Rhin"
        Case "68":  NomDepartement = "Haut-Rhin"
        Case "69":  NomDepartement = "Rhone"
        Case "70":  NomDepartement = "Haute-Saone"
        Case "71":  NomDepartement = "Saone-et-Loire"
        Case "72":  NomDepartement = "Sarthe"
        Case "73":  NomDepartement = "Savoie"
        Case "74":  NomDepartement = "Haute-Savoie"
        Case "75":  NomDepartement = "Paris"
        Case "76":  NomDepartement = "Seine-Maritime"
        Case "77":  NomDepartement = "Seine-et-Marne"
        Case "78":  NomDepartement = "Yvelines"
        Case "79":  NomDepartement = "Deux-Sevres"
        Case "80":  NomDepartement = "Somme"
        Case "81":  NomDepartement = "Tarn"
        Case "82":  NomDepartement = "Tarn-et-Garonne"
        Case "83":  NomDepartement = "Var"
        Case "84":  NomDepartement = "Vaucluse"
        Case "85":  NomDepartement = "Vendee"
        Case "86":  NomDepartement = "Vienne"
        Case "87":  NomDepartement = "Haute-Vienne"
        Case "88":  NomDepartement = "Vosges"
        Case "89":  NomDepartement = "Yonne"
        Case "90":  NomDepartement = "Territoire de Belfort"
        Case "91":  NomDepartement = "Essonne"
        Case "92":  NomDepartement = "Hauts-de-Seine"
        Case "93":  NomDepartement = "Seine-Saint-Denis"
        Case "94":  NomDepartement = "Val-de-Marne"
        Case "95":  NomDepartement = "Val-d Oise"
        Case "971": NomDepartement = "Guadeloupe"
        Case "972": NomDepartement = "Martinique"
        Case "973": NomDepartement = "Guyane"
        Case "974": NomDepartement = "La Reunion"
        Case "976": NomDepartement = "Mayotte"
        Case "TT":  NomDepartement = "Teletravail"
        Case Else:  NomDepartement = "Departement " & num
    End Select
End Function


' ====================================================================
' MODULE : UpdateIP
' Genere update_ip.sql a partir d un export reseau (plages CIDR)
' ====================================================================

Sub GenerateUpdateIP()

    Dim wsSource   As Worksheet
    Dim wsConfig   As Worksheet
    Dim lastRow    As Long
    Dim i          As Long

    Dim addrPrefix As String
    Dim prefix     As String
    Dim ipReseau   As String
    Dim nameCell   As String
    Dim codeGX     As String
    Dim prefixInt  As Integer

    Dim sqlLines   As String
    Dim lineCount  As Long
    Dim outputPath As String
    Dim configPath As String
    Dim fileNum    As Integer

    ' -- Dictionnaires : codeGX -> meilleur masque / IP associee --------------
    Dim maxEntries As Long
    maxEntries = 5000
    Dim dictCode()  As String:  ReDim dictCode(1 To maxEntries)
    Dim dictIP()    As String:  ReDim dictIP(1 To maxEntries)
    Dim dictMask()  As Integer: ReDim dictMask(1 To maxEntries)
    Dim dictCount   As Long
    dictCount = 0

    Set wsSource = ActiveSheet

    ' -- Feuille Config -------------------------------------------------------
    On Error Resume Next
    Set wsConfig = ThisWorkbook.Sheets("Config")
    On Error GoTo 0

    If wsConfig Is Nothing Then
        Set wsConfig = ThisWorkbook.Sheets.Add
        wsConfig.Name = "Config"
        wsConfig.Cells(1, 1).Value = "Dossier de sortie SQL"
        wsConfig.Cells(1, 2).Value = ""
    End If

    configPath = Trim(CStr(wsConfig.Cells(1, 2).Value))

    ' -- Verifier si le chemin est valide, sinon demander ---------------------
    Dim pathOk As Boolean
    pathOk = False
    If configPath <> "" Then
        On Error Resume Next
        If Dir(configPath, vbDirectory) <> "" Then pathOk = True
        On Error GoTo 0
    End If

    If Not pathOk Then
        configPath = InputBox("Entrez le chemin du dossier de sortie pour update_ip.sql :" & vbCrLf & vbCrLf & _
                              "(il sera memorise pour les prochaines fois)", _
                              "Dossier de sortie", Environ("USERPROFILE") & "\Documents")
        If configPath = "" Then
            MsgBox "Operation annulee.", vbInformation
            Exit Sub
        End If
        wsConfig.Cells(1, 2).Value = configPath
    End If

    If Right(configPath, 1) = "\" Then configPath = Left(configPath, Len(configPath) - 1)
    outputPath = configPath & "\update_ip.sql"

    ' -- Lecture des donnees --------------------------------------------------
    lastRow = wsSource.Cells(wsSource.Rows.Count, "A").End(xlUp).Row

    If lastRow < 2 Then
        MsgBox "Aucune donnee trouvee (a partir de la ligne 2).", vbExclamation
        Exit Sub
    End If

    ' =========================================================================
    ' PASSE 1 : collecter toutes les lignes valides.
    ' Pour chaque code GX, on ne retient que la plage avec le masque le plus
    ' petit (= reseau le plus large), ce qui maximise la couverture IP.
    ' Exemple : GX005131 a 10.161.0.0/21 et 10.161.18.0/24
    '           -> on garde 10.161.0.0/21 (masque 21 < 24)
    ' =========================================================================
    Dim ignoreCount As Long
    ignoreCount = 0

    For i = 2 To lastRow

        addrPrefix = Trim(CStr(wsSource.Cells(i, 1).Value))
        prefix = Trim(CStr(wsSource.Cells(i, 2).Value))
        nameCell = Trim(CStr(wsSource.Cells(i, 5).Value))

        If addrPrefix = "" Or nameCell = "" Then GoTo NextRow

        ' Extraire l IP seule depuis "10.16.32.0/24"
        If InStr(addrPrefix, "/") > 0 Then
            ipReseau = Left(addrPrefix, InStr(addrPrefix, "/") - 1)
        Else
            ipReseau = addrPrefix
        End If

        ' Valider le prefix CIDR
        If prefix = "" Then GoTo NextRow
        prefixInt = 0
        On Error Resume Next
        prefixInt = CInt(prefix)
        On Error GoTo 0
        If prefixInt < 0 Or prefixInt > 32 Then GoTo NextRow

        ' Extraire et normaliser le code GX
        If InStr(nameCell, "-") > 0 Then
            codeGX = Left(nameCell, InStr(nameCell, "-") - 1)
        Else
            codeGX = nameCell
        End If
        If Left(UCase(codeGX), 2) <> "GX" Then GoTo NextRow
        codeGX = NormalizeCodeGX(codeGX)

        ' Chercher si ce code GX existe deja dans le dictionnaire
        Dim found As Boolean
        Dim idx   As Long
        found = False
        For idx = 1 To dictCount
            If dictCode(idx) = codeGX Then
                found = True
                Exit For
            End If
        Next idx

        If found Then
            ' Ce code GX est deja present : on garde le masque le plus petit
            If prefixInt < dictMask(idx) Then
                dictIP(idx) = ipReseau
                dictMask(idx) = prefixInt
            End If
            ignoreCount = ignoreCount + 1
        Else
            ' Nouveau code GX
            dictCount = dictCount + 1
            If dictCount > maxEntries Then
                MsgBox "Trop d'entrees (>" & maxEntries & "). Augmentez maxEntries.", vbCritical
                Exit Sub
            End If
            dictCode(dictCount) = codeGX
            dictIP(dictCount) = ipReseau
            dictMask(dictCount) = prefixInt
        End If

NextRow:
    Next i

    If dictCount = 0 Then
        MsgBox "Aucune ligne valide avec un code GX trouvee.", vbExclamation
        Exit Sub
    End If

    ' =========================================================================
    ' PASSE 2 : generer le SQL a partir du dictionnaire deduplique
    ' =========================================================================
    sqlLines = "-- update_ip.sql" & vbCrLf
    sqlLines = sqlLines & "-- Genere automatiquement par UpdateIP_FranceDebit" & vbCrLf
    sqlLines = sqlLines & "-- Col A : Address+prefix  |  Col B : Prefix CIDR  |  Col E : Name (code GX)" & vbCrLf
    sqlLines = sqlLines & "-- Doublons : masque le plus petit retenu par code GX" & vbCrLf
    sqlLines = sqlLines & "-- " & Now() & vbCrLf & vbCrLf
    lineCount = 0

    For idx = 1 To dictCount
        sqlLines = sqlLines & _
            "UPDATE FT_SITE" & vbCrLf & _
            "SET    IP_RESEAU   = '" & EscapeSQL(dictIP(idx)) & "'," & vbCrLf & _
            "       MASQUE_SITE = " & dictMask(idx) & vbCrLf & _
            "WHERE  CODE_GX_SITE = '" & EscapeSQL(dictCode(idx)) & "';" & vbCrLf & vbCrLf
        lineCount = lineCount + 1
    Next idx

    ' -- Ecriture fichier -----------------------------------------------------
    On Error GoTo ErrEcriture
    fileNum = FreeFile
    Open outputPath For Output As #fileNum
    Print #fileNum, sqlLines
    Close #fileNum
    On Error GoTo 0

    Dim msg As String
    msg = lineCount & " UPDATE(s) generes"
    If ignoreCount > 0 Then
        msg = msg & " (" & ignoreCount & " doublon(s) ignore(s), masque le plus petit retenu)"
    End If
    MsgBox msg & "." & vbCrLf & vbCrLf & "Fichier : " & outputPath, vbInformation, "UpdateIP_FranceDebit"
    Exit Sub

ErrEcriture:
    On Error GoTo 0
    wsConfig.Cells(1, 2).Value = ""
    MsgBox "Impossible d'ecrire le fichier :" & vbCrLf & outputPath & vbCrLf & vbCrLf & _
           "Erreur " & Err.Number & " : " & Err.Description & vbCrLf & vbCrLf & _
           "Le chemin a ete efface, il vous sera redemande au prochain lancement.", _
           vbCritical, "Erreur ecriture"

End Sub


' =============================================================================
' NormalizeCodeGX
' Normalise un code GX pour correspondre au format de la base de donnees.
' La base stocke les codes sur 6 chiffres apres "GX" : GX000000..GX999999
' =============================================================================
Private Function NormalizeCodeGX(rawCode As String) As String

    Dim cleaned As String
    Dim prefix  As String
    Dim digits  As String
    Dim c       As String
    Dim j       As Integer

    cleaned = Trim(rawCode)
    If InStr(cleaned, " ") > 0 Then
        cleaned = Left(cleaned, InStr(cleaned, " ") - 1)
    End If

    prefix = ""
    digits = ""
    For j = 1 To Len(cleaned)
        c = Mid(cleaned, j, 1)
        If c >= "0" And c <= "9" Then
            digits = digits & c
        ElseIf digits = "" Then
            prefix = prefix & c
        End If
    Next j

    Do While Len(digits) < 6
        digits = "0" & digits
    Loop

    NormalizeCodeGX = UCase(prefix) & digits

End Function


' =============================================================================
' EscapeSQL
' Echappe les apostrophes pour injection SQL securisee
' =============================================================================
Private Function EscapeSQL(val As String) As String
    EscapeSQL = Replace(val, "'", "''")
End Function
