# Ma Connexion

> Outil de supervision du débit réseau interne — développé dans le cadre d'un stage BUT Informatique

[![PHP](https://img.shields.io/badge/PHP-8.2-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-orange)](https://mysql.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6-yellow)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-320%20tests-green)](https://phpunit.de)
[![License](https://img.shields.io/badge/License-MIT-lightgrey)](LICENSE)

---

## Présentation

**Ma Connexion** est une application web interne de mesure et de centralisation des performances réseau. Elle permet aux équipes techniques de :

- mesurer le débit (download, upload, ping) depuis n'importe quel poste du réseau interne, en un clic
- consulter l'historique des mesures par site, région et département
- détecter les sites sous-performants via un système d'alertes et de seuils configurables
- visualiser la situation nationale sur une carte choroplèthe interactive (Leaflet)
- générer des rapports hebdomadaires et exporter les données

Ce projet a été développé **de zéro en 9 semaines** (7 avril – 10 juin 2026) dans le cadre d'un **stage de 2e année de BUT Informatique** (parcours Réalisation d'applications).

---

## Fonctionnalités

| Page | Description |
|---|---|
| **Test de débit** | Mesure précise ping / download / upload via ReadableStream (durée fixe ~8s) |
| **Recherche** | Liste des sites avec badge de verdict, recherche multi-champs, tri colonnes |
| **Logs** | Historique des tests avec filtres, graphique d'évolution, export CSV/PDF |
| **Statistiques** | Dashboard complet : Charts.js par site/région/département, carte Leaflet, heatmap |
| **Alertes** | Sites insuffisants filtrables + détection de régression automatique |
| **Rapport hebdo** | Vue nationale + sites dégradés, période 7j/30j/mois, export PDF |
| **Différence** | Comparaison de deux sessions avec filtres et export |
| **Gestion des sites** | Ajout, suppression unitaire et groupée (bulk) |
| **Seuils** | Seuils globaux + dérogations par site individuelles |
| **Config moteur** | Paramétrage dynamique via BDD (17 paramètres) |
| **Import logs** | Import CSV avec validation, UPSERT, rapport d'erreurs |

---

## Stack technique

**Backend**
- PHP 8.2 + PDO MySQL
- Architecture services : `SeuilService`, `StatService`, `CacheService`, `QueueService`, `ResultatService`, `SiteResolverService`, `AuditService`
- API REST sécurisée (CSRF, rate limiting, session expiry, whitelist endpoints)
- PHPUnit — 320 tests, 826 assertions, 0 échec

**Frontend**
- JavaScript ES6+ modulaire (aucun framework)
- Chart.js — graphiques et heatmap
- Leaflet.js — carte choroplèthe nationale
- CSS custom — dark mode + 4 palettes daltonisme (deutéranopie, protanopie, tritanopie, achromatopsie)
- Police Marianne (identité République Française)

**Base de données**
- MySQL / MariaDB
- 14 tables : `FT_INTERREGION` → `FT_REGION` → `FT_DEPARTEMENT` → `FT_SITE` → `FT_LOGS` + tables de support
- Hiérarchie géographique complète France Travail
- Seuils dérogatoires par site (`FT_SEUILS_SITE`)

**Outillage**
- XAMPP (Windows) — hébergement sur VM interne
- VBA Excel — macro `ExportSQL` pour générer les fichiers SQL depuis l'annuaire Excel
- PowerShell — géocodage GPS via `api-adresse.data.gouv.fr`
- `.htaccess` — URL rewriting, whitelist endpoints, headers de sécurité

---

## Architecture

```
ma-connexion/
├── src/                        # Code source de l'application
│   ├── index.php               # Page de test (point d'entrée public)
│   ├── login.php / erreur.php
│   ├── speedtest.js            # Moteur de mesure ReadableStream
│   ├── backend/
│   │   ├── config.php          # Connexion BDD + APP_VERSION
│   │   ├── includes/auth.php   # Authentification / sessions
│   │   ├── services/           # Logique métier (SeuilService, StatService…)
│   │   ├── ip/                 # Endpoints publics (test, résultats, file)
│   │   └── admin/              # Endpoints admin (logs, stats, config…)
│   ├── frontend/
│   │   ├── *.php               # Pages utilisateur (alertes, stats, diff…)
│   │   ├── admin/*.php         # Pages admin
│   │   ├── css/                # Styles par page
│   │   ├── js/                 # Scripts par page + modules stats/
│   │   └── fonts/              # Police Marianne
│   └── tests/
│       ├── php/                # Suite PHPUnit (SQLite in-memory)
│       └── js/                 # Suite QUnit
├── sql/                        # Scripts SQL (structure + config)
│   ├── create_database.sql     # Schéma complet (toutes les tables)
│   ├── clean.sql               # DROP toutes les tables
│   ├── indexes.sql             # Index sur FT_LOGS
│   ├── insert_config_speedtest.sql  # 17 paramètres moteur
│   ├── update_seuils.sql       # Seuils de qualité réseau
│   ├── migration_audit.sql     # Migration v1.9.2 (BDD existante)
│   └── geocoder.ps1            # Géocodage GPS (PowerShell)
├── vba/
│   └── ExportSQL.bas           # Macro Excel → génération SQL sites
├── docs/
│   ├── CHANGELOG.md            # Historique complet des versions
│   └── ma_connexion_mermaid.md # Diagrammes MCD / flux
├── .gitignore
└── README.md
```

---

## Installation

### Prérequis

- XAMPP (PHP 8.2+, MySQL 8.0+) ou équivalent LAMP/WAMP
- Composer (pour les dépendances PHP)
- Chrome (application testée et optimisée pour Chrome)

### Déploiement

**1. Copier les fichiers**
```
Copier le dossier src/ dans htdocs/ma-connexion/
```

**2. Configurer la base de données**

Créer un fichier `.env` dans le dossier racine de l'application (au-dessus de `src/`) :
```env
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=votre_mot_de_passe
DB_NAME=ma_connexion
APP_ENV=production
```

**3. Initialiser la base de données**

Exécuter les scripts SQL dans phpMyAdmin dans cet ordre :
```
1. sql/create_database.sql       ← crée la base et toutes les tables
2. sql/indexes.sql               ← index de performance
3. sql/insert_config_speedtest.sql ← paramètres moteur
4. sql/update_seuils.sql         ← seuils de qualité réseau
```

Puis alimenter les tables géographiques avec la macro VBA `vba/ExportSQL.bas` (voir section VBA).

**4. Vérifier la configuration Apache**

Le fichier `.htaccess` gère le rewriting et la sécurité. S'assurer que `mod_rewrite` est activé.

**5. Accès**

Ouvrir `http://localhost/ma-connexion/` dans Chrome.
Identifiants par défaut (à changer immédiatement) :
- Admin : `admin` / `admin`
- Tech : `tech` / `tech`

---

## Macro VBA

Le fichier `vba/ExportSQL.bas` est un module VBA à importer dans le classeur Excel contenant l'annuaire des sites.

Il génère automatiquement les fichiers SQL d'insertion depuis les colonnes Excel :

| Colonne | Donnée |
|---|---|
| A | Code GX site |
| B | Nom du site |
| C | Direction (région) |
| D | SDP (interrégion) |
| G/H | Adresse |
| I | Code postal |
| J | Ville |
| T | IP réseau (CIDR) |
| AA | Numéro département |

Lancer `ExportTout` pour générer tous les fichiers en une fois.

---

## Moteur de mesure

Le test de débit repose sur l'API **ReadableStream** du navigateur :

- **Ping** : N requêtes HTTP légères, calcul de la médiane (élimine les pics)
- **Download** : flux continu lu pendant une durée fixe (`dureeMsDownload` ms), les premières `PRECHAUFFAGE_MIN_MS` ms sont écartées pour laisser TCP s'établir
- **Upload** : envoi d'un blob en POST pendant `dureeMsUpload` ms, même principe

Ce choix garantit une durée de test **prévisible** quelle que soit la vitesse de connexion (contrairement à un fichier de taille fixe).

Tous les paramètres sont configurables sans toucher au code via la table `FT_CONFIG_SPEEDTEST` (page admin Config).

---

## Tests

**PHPUnit (backend)**
```bash
cd src/
composer install
./vendor/bin/phpunit tests/php/
```
320 tests — 826 assertions — 0 échec

**QUnit (frontend JavaScript)**

Ouvrir `src/tests/js/index.php` dans le navigateur.
78 tests — 0 échec

---

## Chronologie du projet (9 semaines)

| Semaine | Travaux principaux |
|---|---|
| S1 (7–11 avril) | Migration LibreSpeed → PHP/MySQL, setup VM XAMPP, architecture BDD initiale |
| S2 (14–18 avril) | Authentification, file d'attente, API REST, macro VBA ExportSQL v1 |
| S3 (28 avril–2 mai) | Sécurité (CSRF, rate limiting, .env, .htaccess), StatService, export CSV |
| S4 (5–9 mai) | Architecture services PHP (SeuilService, SiteResolverService), PHPUnit v1 |
| S5 (12–16 mai) | Carte Leaflet choroplèthe, géocodage GPS 1009/1044 sites, modules ES6 stats |
| S6 (19–23 mai) | Dashboard statistiques complet, seuils dérogatoires par site, rapport hebdo |
| S7 (26–30 mai) | Moteur ReadableStream finalisé, dark mode, accessibilité daltonisme, documentation passation |

---

## Sécurité

- Credentials dans `.env` (hors dossier web, jamais versionné)
- Mots de passe hashés en bcrypt (`password_hash`)
- Protection CSRF (token `bin2hex(random_bytes(32))`) sur toutes les requêtes POST
- Rate limiting (5 échecs / 10 min par IP)
- Session expiry 30 min d'inactivité
- Whitelist de 22 endpoints dans `.htaccess` (tout accès non listé → 403)
- En-têtes HTTP sécurité : `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`
- Accès restreint au réseau interne (IP privées uniquement)

---

## Accessibilité

- **Dark mode** : détection automatique (`prefers-color-scheme`) + bascule manuelle persistée en localStorage
- **4 modes daltonisme** : deutéranopie, protanopie, tritanopie, achromatopsie (palette Wong 2011)
- **Raccourcis clavier** : `R` (reset filtre), `?` (aide), `Échap` (fermer modals)
- **Police Marianne** avec `font-display: swap`

---

## Auteur

**Clément E.** — Étudiant BUT Informatique 2e année, parcours Réalisation d'applications  
Stage DSI — Caen, 2026

---

## Licence

MIT — Voir [LICENSE](LICENSE)
