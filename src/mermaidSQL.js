
mermaid.initialize({ startOnLoad: false, theme: 'default' });

const diagram = `erDiagram
  FT_INTERREGION {
    int ID_INTERREGION PK
    string NOM_INTERREGION
  }
  FT_REGION {
    int ID_REGION PK
    string NOM_REGION
    int ID_INTERREGION FK
  }
  FT_DEPARTEMENT {
    int ID_DEPARTEMENT PK
    string NUM_DEPARTEMENT
    string NOM_DEPARTEMENT
    int ID_REGION FK
  }
  FT_SITE {
    string CODE_GX_SITE PK
    string NOM_SITE
    string CODE_POSTAL
    string ADRESSE
    string VILLE
    float LATITUDE
    float LONGITUDE
    int IP_SPECIALE
    string IP_RESEAU
    int MASQUE_SITE
    int ID_DEPARTEMENT FK
  }
  FT_LOGS {
    int ID_LOGS PK
    float PING_LOGS
    float DOWNLOAD_LOGS
    float UPLOAD_LOGS
    int IS_FAST
    datetime DATE_LOGS
    string IP_CLIENT
    string CODE_GX_SITE FK
  }
  FT_COMMENTAIRES {
    int ID_COMMENTAIRES PK
    datetime DATE_COMMENTAIRE
    string CONTENU_COMMENTAIRE
    int ID_LOGS FK
  }
  FT_COMPTES {
    int ID_COMPTE PK
    string ALIAS_COMPTE
    string MDP_COMPTE
    int IS_ADMIN
  }
  FT_LOGS_CONNEXION {
    int ID_LOG_CO PK
    string TYPE_ACCES
    string IP_CONNEXION
    datetime DATE_CO
    int SUCCES
    int ID_COMPTE FK
  }
  FT_QUEUE_STATUS {
    int ID_STATUS PK
    string NOM_STATUS
  }
  FT_QUEUE_LOCK {
    int ID PK
  }
  FT_QUEUE {
    int ID_QUEUE PK
    string TOKEN
    int ID_STATUS FK
    datetime CREATED_AT
    datetime STARTED_AT
  }
  FT_SEUILS {
    int ID_SEUIL PK
    string NOM_SEUIL
    float VALEUR_BONNE
    float VALEUR_MAUVAISE
  }
  FT_CONFIG_SPEEDTEST {
    string CLE_CONFIG PK
    float VALEUR_CONFIG
    string LIBELLE
    string UNITE
    datetime DATE_MAJ
  }
  FT_INTERREGION ||--o{ FT_REGION : contient
  FT_REGION ||--o{ FT_DEPARTEMENT : contient
  FT_DEPARTEMENT ||--o{ FT_SITE : contient
  FT_SITE ||--o{ FT_LOGS : genere
  FT_LOGS ||--o{ FT_COMMENTAIRES : commente
  FT_COMPTES ||--o{ FT_LOGS_CONNEXION : effectue
  FT_QUEUE_STATUS ||--o{ FT_QUEUE : definit`;

(async () => {
  try {
    const { svg } = await mermaid.render('erd-graph', diagram);
    document.getElementById('erd').innerHTML = svg;
  } catch(e) {
    document.getElementById('err').textContent = 'Erreur: ' + e.message;
  }
})();