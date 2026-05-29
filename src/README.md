# France Débit — Application de test de débit réseau interne

Outil interne France Travail DSI Normandie.  
Permet aux agents de tester la qualité de leur connexion réseau depuis leur navigateur. Les résultats sont automatiquement associés au site France Travail correspondant à l'IP du poste.

---

## Accès

| Interface | URL |
|---|---|
| Test de débit (public) | http://dl00200348.sip24.pole-emploi.intra/ |
| Test de débit (IP directe) | http://YOUR_SERVER_IP/ |
| Connexion technicien / admin | http://YOUR_SERVER_IP/login.php |

---

## Comptes par défaut

| Rôle | Login |
|---|---|
| Technicien | `tech` |
| Administrateur | `admin` |

> ⚠️ Les mots de passe par défaut sont définis dans `create_database.sql`.  
> Les modifier avant mise en production via **Admin → Comptes**.

---

## Architecture

```
htdocs/
│   .env                   ← Credentials MySQL (jamais versionné)
│   .env.example           ← Modèle documenté
│   composer.json          ← Dépendances PHP (PHPUnit)
│   .editorconfig          ← Style de code uniforme (Eclipse, IntelliJ, VS Code)
│
└── speedtest/
    │   index.php          ← Page publique de test de débit
    │   login.php          ← Page de connexion (tech + admin)
    │   erreur.php         ← Page d'erreur personnalisée (403, 404)
    │   speedtest.js       ← Moteur de mesure (ReadableStream, durée fixe)
    │   speedtest_debug.js ← Outil de dev — comparaison des méthodes de mesure (non chargé en prod)
    │   .htaccess          ← Sécurité HTTP (headers, blocage backend/, erreurs)
    │
    ├── backend/
    │   │   config.php             ← Connexion PDO MySQL (lit htdocs/.env)
    │   ├── includes/
    │   │   └── auth.php           ← requireLogin(), requireAdmin(), CSRF helpers
    │   ├── services/
    │   │   ├── SeuilService.php   ← Seuils de qualité + calcul de verdicts (remplace verdicthelper)
    │   │   ├── QueueService.php   ← File d'attente (transactions InnoDB)
    │   │   ├── StatService.php    ← Statistiques agrégées (8 méthodes)
    │   │   ├── ResultatService.php ← Validation + insertion d'un résultat de test
    │   │   ├── SiteResolverService.php ← Résolution IP → CODE_GX_SITE (CIDR)
    │   │   └── CacheService.php   ← Cache fichier JSON TTL 5 min (cache/)
    │   ├── admin/
    │   │   ├── login_unified.php  ← Authentification bcrypt + rate limiting
    │   │   ├── logout.php
    │   │   ├── comptes.php        ← CRUD comptes techniciens/admins
    │   │   ├── seuils.php         ← Mise à jour des seuils + invalidation cache
    │   │   ├── get_seuils.php     ← API JSON seuils
    │   │   ├── logs_admin.php     ← API logs (vue admin) + export CSV + buildFilters()
    │   │   ├── commentaires.php   ← API commentaires (tech + admin)
    │   │   ├── profil.php         ← Changement de mot de passe utilisateur
    │   │   ├── stat.php           ← API statistiques avec cache + filtre période
    │   │   ├── modifier_site.php  ← API GET/POST modification d'un site (IP, adresse, GPS)
    │   │   ├── difference.php     ← API comparaison test rapide vs précis (sessions appairées)
    │   │   └── save_config_speedtest.php ← Sauvegarde config moteur speedtest (admin)
    │   └── ip/
    │       ├── save_result.php    ← Enregistrement d'un test en BDD
    │       ├── save_comment.php   ← Enregistrement d'un commentaire
    │       ├── get_logs.php       ← API logs par site
    │       ├── delete_log.php     ← Suppression d'un log (admin)
    │       ├── queue.php          ← File d'attente des tests
    │       ├── getIP.php          ← Retourne l'IP client (JSON)
    │       ├── getIP_util.php     ← Détection IP multi-proxy + DNS PTR + ipDansReseau()
    │       ├── site_info.php      ← Infos du site associé à une IP
    │       ├── get_alertes.php    ← API sites insuffisants (filtre période, métrique, région, export CSV)
    │       ├── get_config_speedtest.php ← Endpoint public retournant la config speedtest en JSON
    │       ├── garbage.php        ← Endpoint download (mesure débit)
    │       └── empty.php          ← Endpoint upload (mesure débit)
    │
    ├── cache/                     ← Fichiers JSON du cache stats (auto-créé)
    │
    ├── frontend/
    │   │   recherche.php      ← Recherche de sites (tech + admin)
    │   │   logs.php           ← Logs d'un site (tech + admin)
    │   │   statistique.php    ← Dashboard statistiques (tech + admin)
    │   │   alertes.php        ← Sites avec performances insuffisantes (tech + admin)
│   │   commentaires.php   ← Vue commentaires + lien vers log (tech + admin)
    │   │   profil.php         ← Changement de mot de passe (tech + admin)
    │   │   difference.php     ← Comparaison test rapide vs précis (tech + admin)
    │   ├── admin/
    │   │   ├── comptes.php    ← Gestion des comptes (admin)
    │   │   ├── modifier_site.php ← Formulaire de modification d'un site (admin)
    │   │   ├── seuils.php     ← Gestion des seuils (admin)
    │   │   └── logs.php       ← Logs tous sites + export CSV + suppression (admin)
    │   ├── css/               ← Feuilles de style par page (responsive mobile inclus)
│   │   └── index.css      ← Styles spécifiques à la page de test
    │   ├── fonts/
    │   │   └── style.css      ← CSS global + variables France Travail
    │   ├── includes/
    │   │   ├── session.php    ← Session + CSRF + expiration 30 min
    │   │   └── header.php     ← Header HTML commun
    │   └── js/
    │       ├── index.js       ← Test de débit (queue, verdicts, feedback IP)
    │       ├── utils.js       ← fetchJson(), seuils SEUILS, classCouleur(), tooltipVerdict(), creerPagination()
    │       ├── alertes.js     ← Page alertes (chargement, tri, pagination, export CSV)
    │       ├── modifier_site.js ← Formulaire modification site (chargement, validation, soumission)
    │       ├── logs_admin.js  ← Coloration du tableau admin logs (dépend utils.js)
    │       ├── profil.js      ← Changement de mot de passe via fetch (sans rechargement)
    │       ├── recherche.js   ← Recherche + tri + pagination mémorisée
    │       ├── logs.js        ← Logs + tooltips verdicts + highlight depuis commentaires
    │       ├── statistique.js ← Dashboard (Chart.js, Leaflet, marqueurs GPS, heatmap)
    │       └── config_speedtest.js ← Config moteur speedtest (admin)
    ├── geojson/
    │   ├── regions-version-simplifiee.geojson    ← GeoJSON régions (servi localement)
    │   └── departements-version-simplifiee.geojson ← GeoJSON départements (servi localement)
    │
    ├── tests/
    │   ├── IputilTest.php       ← 27 tests — normaliserCandidatIp, ipDansReseau, getClientIp
    │   ├── CacheTest.php ← 14 tests — CacheService (get, set, TTL, flush, sanitize)
    │   ├── QueueTest.php ← 25 tests — QueueService sur SQLite en mémoire
    │   ├── StatTest.php  ← 27 tests — StatService sur SQLite en mémoire
    │   ├── SaveresultTest.php   ← 16 tests — validation mesures + résolution CIDR
    │   ├── SpeedtestTest.php    ← 25 tests — ipDansReseau, validation mesures, pagination
    │   ├── GetLogsTest.php      ← 9 tests  — construction WHERE, pagination, JSON
    │   ├── SeuilsTest.php       ← 15 tests — réindexation seuils + verdicts
    │   └── phpunit.xml
    │
    └── sql/                   ← Scripts SQL générés par la macro VBA Excel
        └── indexes.sql        ← Index sur FT_LOGS (si base existante)
```

---

## Base de données

**Nom :** `ft_speedtest`  
**Serveur :** localhost (XAMPP)

| Table | Rôle |
|---|---|
| `FT_SITE` | Référentiel des sites France Travail (code, nom, adresse, GPS, IP/masque, géographie) |
| `FT_INTERREGION` | Référentiel interrégions |
| `FT_REGION` | Référentiel régions |
| `FT_DEPARTEMENT` | Référentiel départements |
| `FT_LOGS` | Résultats de tests (ping, download, upload, IP, date) |
| `FT_COMMENTAIRES` | Commentaires libres associés aux tests |
| `FT_COMPTES` | Comptes techniciens et administrateurs (bcrypt) |
| `FT_SEUILS` | Seuils de colorisation (bon/mauvais) par métrique |
| `FT_CONFIG_SPEEDTEST` | Paramètres du moteur de speedtest (taille fichiers, nb mesures, modes précis/rapide) |
| `FT_QUEUE` | File d'attente des tests en cours |
| `FT_QUEUE_STATUS` | Référentiel statuts file d'attente |
| `FT_QUEUE_LOCK` | Verrou InnoDB pour l'exclusivité de la file |
| `FT_LOGS_CONNEXION` | Historique des connexions + échecs (rate limiting) |

### Colonnes géographiques de FT_SITE

| Colonne | Type | Rôle |
|---|---|---|
| `ADRESSE` | `VARCHAR(150)` | Adresse postale complète (colonnes G+H de l'Excel) |
| `VILLE` | `VARCHAR(100)` | Ville (colonne J de l'Excel) |
| `CODE_POSTAL` | `CHAR(5)` | Code postal (colonne I de l'Excel) |
| `LATITUDE` | `DECIMAL(9,6)` | Latitude GPS — remplie par `geocoder.ps1` |
| `LONGITUDE` | `DECIMAL(9,6)` | Longitude GPS — remplie par `geocoder.ps1` |

### Index sur FT_LOGS

| Index | Colonnes | Usage |
|---|---|---|
| `idx_logs_site` | `CODE_GX_SITE` | Filtrage par site |
| `idx_logs_date` | `DATE_LOGS` | Filtrage par date |
| `idx_logs_site_date` | `CODE_GX_SITE, DATE_LOGS` | Filtrage combiné |
| `idx_logs_ip_client` | `IP_CLIENT` | Recherche par IP |

---

## Sécurité

| Mécanisme | Détail |
|---|---|
| Credentials | Fichier `htdocs/.env` hors du dossier web, lu par `config.php` via `parse_ini_file()` |
| Authentification | bcrypt via `password_hash()` / `password_verify()` |
| Sessions | Expiration après 30 min d'inactivité (`SESSION_TIMEOUT = 1800`) |
| CSRF | Token `bin2hex(random_bytes(32))` en session, vérifié sur tous les POST sensibles |
| Rate limiting | 5 échecs de connexion en 10 min → blocage IP via `FT_LOGS_CONNEXION` |
| En-têtes HTTP | `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`, `CSP` |
| Accès backend | Liste blanche de 24 endpoints dans `.htaccess` — tout autre fichier `backend/` retourne 403 |
| Accès .env | `<FilesMatch>` dans `.htaccess` — `Require all denied` |
| debug_ip | Restreint aux requêtes depuis `127.0.0.1` / `::1` uniquement |

---

## Déploiement

### Prérequis
- Windows + XAMPP (Apache + MySQL + PHP 8.2+)
- Extensions PHP requises : `pdo_mysql`, `mbstring`, `json`
- `mod_rewrite` activé dans `httpd.conf` (`AllowOverride All` sur `htdocs/`)

### Étapes

1. Copier le dossier `speedtest/` dans `C:\xampp\htdocs\`
2. Créer `C:\xampp\htdocs\.env` à partir de `.env.example` avec les vrais credentials
3. Créer le dossier `cache/` dans `speedtest/` (accessible en écriture par Apache)
4. Démarrer Apache et MySQL via XAMPP Control Panel
5. Importer les scripts SQL dans phpMyAdmin dans l'ordre :
   1. `create_database.sql` — schéma complet + données initiales
   2. `insert_interregions.sql`
   3. `insert_regions.sql`
   4. `insert_departements.sql`
   5. `insert_sites.sql`
   6. `insert_config_speedtest.sql` — paramètres du moteur de speedtest
   7. `indexes.sql` — uniquement si la base existait avant
6. Géocoder les sites (voir section Géocodage GPS)
7. Accéder à `http://localhost/speedtest/`

### Synchronisation depuis le poste WINDOWS_USER

```bat
:: Export vers le serveur (exclure .env)
pscp -r -exclude .env C:\xampp\htdocs\speedtest\ VM_ADMIN@YOUR_SERVER_IP:/xampp/htdocs/

:: Import depuis le serveur
pscp -r VM_ADMIN@YOUR_SERVER_IP:/xampp/htdocs/speedtest/ C:\xampp\htdocs\speedtest\
```

---

## Géocodage GPS des sites

Les coordonnées GPS sont stockées dans `FT_SITE.LATITUDE` / `LONGITUDE` et utilisées par la carte pour afficher un marqueur précis par agence.

### Procédure (à rejouer après chaque mise à jour du référentiel Excel)

1. Dans phpMyAdmin, exporter en CSV :
   ```sql
   SELECT CODE_GX_SITE, NOM_SITE, ADRESSE, CODE_POSTAL, VILLE
   FROM FT_SITE
   WHERE ADRESSE IS NOT NULL AND VILLE IS NOT NULL
   AND (LATITUDE IS NULL OR LONGITUDE IS NULL)
   ```
2. Sauvegarder sous `C:\Temp\sites_a_geocoder.csv` sur le poste client (qui a internet)
3. Lancer depuis PowerShell :
   ```powershell
   powershell -ExecutionPolicy Bypass -File C:\Temp\geocoder.ps1
   ```
4. Importer `C:\Temp\update_coordonnees.sql` dans phpMyAdmin

> Le script appelle `api-adresse.data.gouv.fr` (gratuit, officiel) et gère un fallback ville+CP si l'adresse complète ne donne pas de résultat. 1009/1044 sites géocodés lors de la première exécution.

---

## Cache statistiques

Les requêtes agrégées lourdes (`par_site`, `par_region`, `par_interregion`, `par_departement`, `nationale`) sont mises en cache 5 minutes dans `cache/`.

- **Invalidation automatique** : à chaque modification des seuils via l'interface admin
- **Invalidation manuelle** (admin uniquement) : `GET /backend/admin/stat.php?type=par_site&flush=1`
- **Filtre période** : `?periode=30` — limite les agrégations aux N derniers jours (défaut : 30j dans l'interface)
- **Dossier** : `speedtest/cache/` — doit être accessible en écriture par Apache

---

## Macro VBA Excel

Le fichier Excel de référentiel (`.xlsm`) contient le module `ExportSQL_FranceDebit`.  
Lancer `ExportTout` génère 9 fichiers dans `C:\Temp\` :

| Ordre | Fichier | Contenu |
|---|---|---|
| 0 | `create_database.sql` | Schéma complet + index + données initiales |
| 1 | `clean.sql` | DROP TABLE dans l'ordre inverse des FK |
| 2 | `insert_interregions.sql` | UPSERT interrégions (col D) |
| 3 | `insert_regions.sql` | UPSERT régions (col C) |
| 4 | `insert_departements.sql` | UPSERT départements (col AA) |
| 5 | `insert_sites.sql` | UPSERT sites (cols A, B, G, H, I, J, T, AA) |
| 6 | `indexes.sql` | CREATE INDEX sur FT_LOGS |
| 7 | `insert_config_speedtest.sql` | INSERT valeurs par défaut `FT_CONFIG_SPEEDTEST` (généré par `ExportConfigSpeedtest`) |
| — | `update_sites.sql` | UPDATE sites existants (généré par `UpdateSiteSQL`) |

### Colonnes Excel utilisées par ExportSiteSQL

| Colonne | Lettre | Champ BDD |
|---|---|---|
| 1 | A | `CODE_GX_SITE` |
| 2 | B | `NOM_SITE` |
| 7 | G | `ADRESSE` (principale) |
| 8 | H | `ADRESSE` (complément — concaténé à G) |
| 9 | I | `CODE_POSTAL` |
| 10 | J | `VILLE` |
| 20 | T | `IP_RESEAU` + `MASQUE_SITE` |
| 27 | AA | `NUM_DEPARTEMENT` (FK) |

---

## Fonctionnalités principales (v1.10.0)

| Page | Fonctionnalité |
|---|---|
| Index | Test unique précis (~8s), file d'attente, encart pédagogique repliable |
| Recherche | Recherche multi-champs, tri colonnes, badge dernier verdict par site |
| Logs | Graphique évolution ping/DL/UL, filtre verdict, stats agrégées, export CSV/PDF |
| Statistiques | Charts par site/région/département/interrégion, carte Leaflet, heatmap, évolution |
| Alertes | Sites insuffisants filtrables, **détection de régression** (badge + KPI + filtre), export CSV/PDF |
| Différence | Comparaison de sessions avec filtres, tri, pagination, export CSV/PDF |
| Logs admin | Filtre verdict SQL, cellules colorées, suppression par plage/âge |
| Gestion des sites | Ajout, suppression unitaire, **suppression groupée** (bulk) avec checkboxes |
| Config speedtest | Paramétrage dynamique du moteur via `FT_CONFIG_SPEEDTEST` (26 paramètres) |
| Seuils | Seuils globaux + **seuils dérogatoires par site** (`FT_SEUILS_SITE`) |
| Rapport hebdo | Vue nationale + sites dégradés, période configurable (7j/30j/mois) |
| Import logs | Import CSV avec validation, UPSERT, rapport d'erreurs |

---

## Versionnement

`APP_VERSION` est définie dans `backend/config.php`. Modifier cette valeur suffit à invalider le cache navigateur de tous les assets CSS/JS.

---

## Tests unitaires

Les commandes sont à lancer depuis la racine du projet (`C:\xampp\htdocs\speedtest\`).

Les commandes sont à lancer depuis `C:\xampp\htdocs\speedtest\tests\`.

**Option 1 — Via Composer (recommandé, nécessite internet)**

```bat
:: 1. Installer les dépendances (une seule fois, depuis la racine)
cd C:\xampp\htdocs\speedtest
C:\xampp\php\php.exe C:\SRV\composer.phar install

:: 2. Lancer les tests (depuis tests/)
cd tests
C:\xampp\php\php.exe vendor\bin\phpunit -c phpunit.xml

:: 3. Avec rapport HTML
C:\xampp\php\php.exe vendor\bin\phpunit -c phpunit.xml --testdox-html rapport_tests.html
```

**Option 2 — Via le .phar directement (sans internet, recommandé sur la VM)**

```bat
cd C:\xampp\htdocs\speedtest\tests

:: Tests uniquement
C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c phpunit.xml

:: Tests + rapport HTML
C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c phpunit.xml --testdox-html rapport_tests.html
```

| Fichier | Tests | Couverture |
|---|---|---|
| `IputilTest.php` | 27 | `normaliserCandidatIp`, `ipDansReseau`, `getClientIp` |
| `CacheTest.php` | 14 | `CacheService` — get, set, TTL, invalidate, flush, sanitize |
| `QueueTest.php` | 25 | `QueueService` — rejoindre, statut, terminer, viderTable sur SQLite |
| `StatTest.php` | 27 | `StatService` — toutes méthodes sur SQLite en mémoire |
| `SaveresultTest.php` | 16 | Validation mesures + résolution CIDR du site |
| `SpeedtestTest.php` | 25 | `ipDansReseau`, validation mesures, pagination, patterns SQL |
| `GetLogsTest.php` | 9 | Construction WHERE, pagination, structure JSON |
| `SeuilsTest.php` | 18 | Réindexation seuils + verdicts via `SeuilService` (cas limites inclus) |

---

## Identité visuelle

| Élément | Valeur |
|---|---|
| Bleu primaire | `#283276` |
| Bleu secondaire | `#406BDE` |
| Rouge | `#E1000F` |
| Police | Marianne (DSFR) |
| Logo | `frontend/fonts/logo_ft.svg` |

---

## Débogage — Test avec une IP spécifique

Le paramètre `debug_ip` dans `save_result.php` permet de simuler un test depuis une IP arbitraire. Restreint aux sessions admin depuis localhost uniquement.

Depuis la console du navigateur sur le serveur XAMPP :

```javascript
fetch('/backend/ip/save_result.php?debug_ip=10.X.X.X', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ping: 50, download: 10, upload: 5 })
}).then(r => r.json()).then(console.log);
```

La réponse indique le `CODE_GX_SITE` matché ou une erreur si aucun site ne correspond.

---

## Contact

Développé par **Clément Esnault** — stagiaire DSI France Travail Normandie (avril–juin 2026).  
Référent technique : **Votre référent entreprise**.

---

## Changelog

Voir [`CHANGELOG.md`](CHANGELOG.md) pour l'historique complet des modifications.

Voir [`CONTRIBUTING.md`](CONTRIBUTING.md) pour les conventions de développement.

## Architecture JS — Modules ES6

Le dashboard statistiques (`frontend/js/statistique.js`) utilise des modules ES6 natifs
sans bundler ni dépendance externe :

```
frontend/js/
├── statistique.js          ← Point d'entrée (type="module")
└── stats/
    ├── state.js            ← État global (STATE)
    ├── api.js              ← Couche HTTP → stat.php
    ├── verdicts.js         ← Calcul verdicts + pastilles
    ├── charts.js           ← Helpers Chart.js
    ├── filtres.js          ← Filtrage + KPIs + bandeaux
    └── panels.js           ← Logique de chaque onglet
```

> **Pourquoi pas Alpine.js ?**  
> La CSP du serveur interdit `unsafe-eval`, ce qui empêche Alpine.js standard (qui utilise `new Function()`) ET Alpine CSP (qui interdit `x-html`, les accents dans les expressions, `Number()`, `encodeURIComponent()`). Solution : vanilla JS + modules ES6 natifs, entièrement compatibles CSP.

---

## Tests PHPUnit

```bash
:: Lancer tous les tests
C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c phpunit.xml

:: Tests + rapport HTML
C:\xampp\php\php.exe C:\SRV\phpunit-11.5.55.phar -c phpunit.xml --testdox-html rapport_tests.html
```

| Fichier | Tests | Couverture |
|---|---|---|
| `IputilTest.php` | 27 | `normaliserCandidatIp`, `ipDansReseau`, `getClientIp` |
| `CacheTest.php` | 14 | `CacheService` — get, set, TTL, invalidate, flush, sanitize |
| `QueueTest.php` | 25 | `QueueService` — rejoindre, statut, terminer, viderTable sur SQLite |
| `StatTest.php` | 27 | `StatService` — toutes méthodes sur SQLite en mémoire |
| `SaveresultTest.php` | 16 | Validation mesures + résolution CIDR du site |
| `SpeedtestTest.php` | 25 | `ipDansReseau`, validation mesures, pagination, patterns SQL |
| `GetLogsTest.php` | 9 | Construction WHERE, pagination, structure JSON |
| `SeuilsTest.php` | 18 | Réindexation seuils + verdicts via `SeuilService` (cas limites inclus) |
| `HeatmapTest.php` | 12 | `StatService::heatmapHoraire()` — filtres site/mode/période, structure JSON |