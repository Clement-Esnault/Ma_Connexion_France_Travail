# Ma Connexion — Diagrammes de flux v1.10.0

> Coller chaque bloc de code sur [mermaid.live](https://mermaid.live) pour visualiser.

---

## Diagramme 1 — Flux principal : test de débit (v1.9 — ReadableStream durée fixe)

```mermaid
flowchart TD
    USER([Utilisateur])

    subgraph PUBLIC["Page publique"]
        INDEX[index.php\nBouton unique]
        SPDJS[speedtest.js v1.9\nReadableStream duree fixe]
        IDXJS[index.js\nFile · verdicts · sauvegarde]
    end

    subgraph SPEEDTEST["Moteur speedtest.js v1.9"]
        PING[mesurerPing\nmediane N requetes]
        DL[mesurerTelechargement\nReadableStream dureeMsDownload ms]
        UL[mesurerEnvoi\nPOST blobs dureeMsUpload ms]
        CFG[chargerConfigSpeedtest\nAJAX BDD au demarrage]
    end

    subgraph BACK_IP["Backend IP"]
        GETIP[getIP.php\nDetection IP + site]
        QUEUE[queue.php\nFile d'attente]
        EMPTY[empty.php\nPing]
        GARB[garbage.php\nStream download]
        UPMEAS[upload_measure.php\nLit body · retourne bytes]
        SAVRES[save_result.php\nEnregistre le test]
        GETCFG[get_config_speedtest.php\ndureeMsDownload/Upload]
        SEUILS[get_seuils.php\nSeuils qualite]
    end

    subgraph SERVICES["Services"]
        QSVC[QueueService]
        CSVC[CacheService\ncache JSON 5 min]
        IPUTIL[SiteResolverService\nipDansReseau · garde masque 0-32]
        SEUIL_SVC[SeuilService]
    end

    subgraph BDD["Base de donnees"]
        FTLOGS[(FT_LOGS)]
        FTSITE[(FT_SITE\nMASQUE_SITE TINYINT 0-32)]
        FTQUEUE[(FT_QUEUE)]
        FTSEUILS[(FT_SEUILS)]
        FTCFG[(FT_CONFIG_SPEEDTEST\n24 params v1.9)]
    end

    USER --> INDEX
    INDEX -- "1. chargerConfig" --> CFG
    CFG -. fetch .-> GETCFG
    GETCFG -- SELECT --> FTCFG

    INDEX -- "2. Test unique ~12s" --> SPDJS

    SPDJS --> PING & DL & UL
    PING -. fetch .-> EMPTY
    DL -. "ReadableStream\narret apres dureeMsDownload" .-> GARB
    UL -. "POST blob\ndureeMsUpload ms" .-> UPMEAS

    SPDJS --> IDXJS
    IDXJS -. fetch IP .-> GETIP
    IDXJS -. fetch queue .-> QUEUE
    IDXJS -. fetch POST .-> SAVRES
    IDXJS -. fetch seuils .-> SEUILS

    QUEUE --> QSVC --> FTQUEUE
    GETIP --> CSVC & IPUTIL --> FTSITE
    SAVRES --> CSVC & IPUTIL
    SAVRES -- INSERT --> FTLOGS
    SEUILS --> SEUIL_SVC --> FTSEUILS
```

---

## Diagramme 2 — Authentification et sécurité

```mermaid
flowchart TD
    USER([Utilisateur])

    subgraph LOGIN_PAGE["Page de connexion"]
        LOGIN[login.php]
    end

    subgraph AUTH_BACK["Backend auth"]
        LUNIF[login_unified.php\nbcrypt · journalisation\nredirect_after_login]
        LOGOUT[logout.php\nDetruit session]
    end

    subgraph INCLUDES["Includes partages"]
        SESSION[session.php\nSession · CSRF · 30 min]
        AUTH[auth.php\nrequireLogin · sauvegarde REQUEST_URI\nsession_regenerate_id]
    end

    subgraph DEST["Destinations"]
        TECH([Espace technicien])
        ADMIN([Espace admin])
        ORIGINE([Page d'origine\nredirect_after_login])
        ERREUR[erreur.php\n403 / 404]
    end

    subgraph BDD["Base de donnees"]
        FTCOMP[(FT_COMPTES\nbcrypt · IS_ADMIN)]
        FTLOGCO[(FT_LOGS_CONNEXION\nIP · date · succes)]
    end

    USER --> LOGIN
    LOGIN --> LUNIF & LOGOUT & SESSION

    LUNIF -- SELECT --> FTCOMP
    LUNIF -- INSERT --> FTLOGCO
    LUNIF -- redirect_after_login --> ORIGINE
    LUNIF -- IS_ADMIN=false --> TECH
    LUNIF -- IS_ADMIN=true --> ADMIN
    LUNIF -. erreur .-> LOGIN

    LOGOUT -. redirect .-> LOGIN
    SESSION -. session expiree .-> LOGIN

    AUTH --> SESSION
    AUTH -. non autorise .-> ERREUR
    AUTH -- sauvegarde URI --> SESSION

    TECH --> AUTH
    ADMIN --> AUTH
```

---

## Diagramme 3 — Espace technicien et admin

```mermaid
flowchart LR
    subgraph TECH["Pages technicien"]
        RECH[recherche.php]
        LOGS[logs.php]
        STAT[statistique.php\n+heatmap horaire]
        ALERT[sites_insuffisants.php]
        DIFF[difference.php]
        PROFIL[profil.php]
    end

    subgraph ADMIN["Pages admin"]
        ALOGS[admin/logs.php]
        ACOMP[admin/comptes.php]
        ASEUIL[admin/seuils.php]
        ASITE[admin/modifier_site.php]
        ACFG[admin/config_speedtest.php\n24 params v1.9]
    end

    subgraph JS["JavaScript"]
        RECHJS[recherche.js]
        LOGSJS[logs.js]
        STATJS[statistique.js\nchargerHeatmap horaire]
        ALERTJS[alertes.js]
        CFGJS[config_speedtest.js\nvaliderConfig v1.9]
        UTILS[utils.js\ncreerPagination]
    end

    subgraph BACK["Backend PHP"]
        GETLOGS[get_logs.php\n+STDDEV]
        STPHP[stat.php\nstatistiques precise]
        GETALERT[get_alertes.php]
        GETHM[get_heatmap.php\nheure x jour semaine]
        GETCFG[get_config_speedtest.php]
        SAVECFG[save_config_speedtest.php]
        IPUTIL[getIP_util.php\nipDansReseau garde 0-32]
    end

    subgraph SVC["Services"]
        STAT_SVC[StatService\n+STDDEV]
        CACHE[CacheService]
        SEUIL_SVC[SeuilService]
        SITE_SVC[SiteResolverService]
    end

    subgraph BDD["BDD"]
        FTLOGS[(FT_LOGS\nIS_FAST)]
        FTSITE[(FT_SITE\nMASQUE_SITE 0-32)]
        FTSEUILS[(FT_SEUILS)]
        FTCFG[(FT_CONFIG_SPEEDTEST\n24 params)]
    end

    STAT --> STATJS
    ACFG --> CFGJS

    STATJS -. fetch .-> STPHP & GETHM
    CFGJS -. fetch GET .-> GETCFG
    CFGJS -. fetch POST .-> SAVECFG

    STPHP --> STAT_SVC & CACHE
    GETALERT --> SEUIL_SVC
    GETHM --> SEUIL_SVC

    GETLOGS -- R+STDDEV --> FTLOGS
    STPHP -- R --> FTLOGS & FTSITE & FTSEUILS
    GETHM -- R --> FTLOGS
    GETCFG -- R --> FTCFG
    SAVECFG -- U --> FTCFG
    IPUTIL -- R --> FTSITE

    RECH --> RECHJS & UTILS
    LOGS --> LOGSJS & UTILS
    ALERT --> ALERTJS & UTILS
```

---

## Diagramme 4 — Base de données : tables et relations FK (v1.10.0)

```mermaid
erDiagram
    FT_INTERREGION {
        int ID_INTERREGION PK
        varchar NOM_INTERREGION
    }
    FT_REGION {
        int ID_REGION PK
        varchar NOM_REGION
        int ID_INTERREGION FK
    }
    FT_DEPARTEMENT {
        int ID_DEPARTEMENT PK
        varchar NUM_DEPARTEMENT
        varchar NOM_DEPARTEMENT
        int ID_REGION FK
    }
    FT_SITE {
        varchar CODE_GX_SITE PK
        varchar NOM_SITE
        char CODE_POSTAL
        varchar ADRESSE
        varchar VILLE
        decimal LATITUDE
        decimal LONGITUDE
        tinyint IP_SPECIALE
        varchar IP_RESEAU
        tinyint MASQUE_SITE
        int ID_DEPARTEMENT FK
    }
    FT_LOGS {
        int ID_LOGS PK
        decimal PING_LOGS
        decimal DOWNLOAD_LOGS
        decimal UPLOAD_LOGS
        tinyint IS_FAST  %% colonne legacy v1.9
        datetime DATE_LOGS
        varchar IP_CLIENT
        varchar CODE_GX_SITE FK
    }
    FT_COMMENTAIRES {
        int ID_COMMENTAIRES PK
        datetime DATE_COMMENTAIRE
        varchar CONTENU_COMMENTAIRE
        int ID_LOGS FK
    }
    FT_SEUILS {
        int ID_SEUIL PK
        varchar NOM_SEUIL
        decimal VALEUR_BONNE
        decimal VALEUR_MAUVAISE
    }
    FT_COMPTES {
        int ID_COMPTE PK
        varchar ALIAS_COMPTE
        varchar MDP_COMPTE
        boolean IS_ADMIN
    }
    FT_LOGS_CONNEXION {
        int ID_LOG_CO PK
        int ID_COMPTE FK
        varchar TYPE_ACCES
        varchar IP_CONNEXION
        datetime DATE_CO
        tinyint SUCCES
    }
    FT_QUEUE {
        int ID_QUEUE PK
        varchar TOKEN
        tinyint ID_STATUS FK
        datetime CREATED_AT
        datetime STARTED_AT
    }
    FT_QUEUE_STATUS {
        tinyint ID_STATUS PK
        varchar NOM_STATUS
    }
    FT_QUEUE_LOCK {
        int ID PK
    }
    FT_CONFIG_SPEEDTEST {
        varchar CLE_CONFIG PK
        decimal VALEUR_CONFIG
        varchar LIBELLE
        varchar UNITE
        datetime DATE_MAJ
    }
    FT_AUDIT_SITES {
        int ID_AUDIT PK
        int ID_COMPTE FK
        varchar ACTION
        varchar ID_SITE
        varchar NOM_SITE
        datetime DATE_ACTION
        varchar IP_ACTION
        json DETAIL
    }
    FT_SEUILS_SITE {
        int ID_SEUIL_SITE PK
        varchar CODE_GX_SITE FK
        decimal DL_BON
        decimal DL_MAUVAIS
        decimal UL_BON
        decimal UL_MAUVAIS
        decimal PING_BON
        decimal PING_MAUVAIS
        varchar RAISON
        datetime DATE_MAJ
        int MAJ_PAR FK
    }

    FT_INTERREGION ||--o{ FT_REGION : "1 vers N"
    FT_REGION ||--o{ FT_DEPARTEMENT : "1 vers N"
    FT_DEPARTEMENT ||--o{ FT_SITE : "1 vers N"
    FT_SITE ||--o{ FT_LOGS : "1 vers N"
    FT_LOGS ||--o| FT_COMMENTAIRES : "1 vers 0/1"
    FT_COMPTES ||--o{ FT_LOGS_CONNEXION : "1 vers N"
    FT_QUEUE_STATUS ||--o{ FT_QUEUE : "1 vers N"
    FT_COMPTES ||--o{ FT_AUDIT_SITES : "1 vers N"
    FT_SITE ||--o{ FT_SEUILS_SITE : "1 vers 0/N"
    FT_COMPTES ||--o{ FT_SEUILS_SITE : "MAJ_PAR"
```

---

## Diagramme 5 — Moteur speedtest.js v1.10 — Test unique précis (~12s)

```mermaid
flowchart TD
    START([Clic bouton unique])

    subgraph INIT["Initialisation"]
        CFG[chargerConfigSpeedtest\nfetch get_config_speedtest.php]
        MIGRER[migrerConfig\nretrocompatibilite anciens params]
    end

    subgraph PRECISE["Test unique ~12s"]
        P_PING[mesurerPing precise\n3 prechauffages · 10 echantillons]
        P_DL[mesurerTelechargement precise\nReadableStream 6000ms]
        P_UL[mesurerEnvoi precise\nPOST blob 6000ms · 20Mo]
        P_SAVE[save_result.php MODE=precise]
    end

    subgraph METHOD["Methode ReadableStream"]
        RS1[fetch garbage.php taille 20Mo]
        RS2[reader.read loop\ncumul bytes]
        RS3[performance.now >= dureeMsDownload]
        RS4[reader.cancel\ndebit = bytes x 8 / duree]
    end

    START --> CFG --> MIGRER
    MIGRER --> P_PING --> P_DL --> P_UL --> P_SAVE

    P_DL --> RS1 --> RS2 --> RS3
    RS3 -- non --> RS2
    RS3 -- oui --> RS4
```
