# CHANGELOG — Ma Connexion

## [1.10.0] — 2026-05-29

### Détection de régression sur la page Alertes

- **`get_alertes.php`** : détection de régression par site — les 4 derniers tests sont récupérés via variable session MySQL (`@rownum`). Un site est marqué `en_regression: true` si ses 3 derniers tests sont insuffisants sur au moins une métrique ET que le 4e test (antérieur) ne l'était pas. Champ `en_regression` ajouté à la réponse JSON.
- **`alertes.js`** : badge `📉 Régression` affiché sur le nom du site + surbrillance jaune de la ligne (`.ligne-regression`). KPI "En régression" ajouté dans la barre (se colore en orange si > 0). Checkbox filtre "📉 Régressions seulement" branchée sur `filtrerEtAfficher()`.
- **`alertes.php`** : KPI "En régression" dans la barre, colonne "Régression" dans le tableau, checkbox filtre.
- **`alertes.css`** : `.badge-regression`, `.ligne-regression`, `.kpi--alerte`, `.kpi--sep`, `.filter-chk`.

### Suppression groupée de sites (admin)

- **`gestion_sites.php`** : troisième onglet "🗑 Suppression groupée" (admin uniquement) — recherche multi-sélection avec checkboxes, bouton "Tout sélectionner", récap des sites cochés avec tags, bouton de confirmation avec compteur en temps réel ("Suppression en cours… 2 / 5").
- **`gestion_sites.js`** : module IIFE `(() => { ... })()` ajouté — fonctions `bulkRechercher`, `bulkToggle`, `bulkToutCocher`, `bulkDeselectTout`, `bulkConfirmer` exposées dans `GS`. Suppression en série via `supprimer_site.php` existant sans modification du backend.
- **`gestion_sites.css`** : `.bulk-site-row`, `.bulk-checkbox`, `.bulk-selected`, `.bulk-recap`, `.bulk-tag`, dark mode.

### Découpage CSS de index.css

- **`frontend/css/index.css`** divisé en 5 fichiers thématiques :
  - `index.css` → variables locales, layout, notice de sauvegarde, animations
  - `index.btn.css` → bouton `#btn-lancer` et tous ses états (running, annuler, disabled)
  - `index.progress.css` → file d'attente + barre de progression globale
  - `index.cards.css` → sections résultats, cartes métriques, modal tooltip
  - `index.encart.css` → encart pédagogique "Comment lire les résultats"
- **`index.php`** : chargement des 5 fichiers via 5 balises `<link>`.

### Commentaires et documentation inline

- **`statistique.js`** : architecture ES6 modules documentée, flux de données en 4 étapes, JSDoc sur `surFiltreChange`/`chargerSeuils`/`chargerNationale`, explication du pourquoi de la carte Leaflet dans ce fichier, `_normaliser` documentée, `invalidateSize()` commenté.
- **`header.js`** : justification du IIFE anti-flash daltonien (exécution avant DOMContentLoaded pour éviter le FOUC), JSDoc sur `toggleMenuDaltonien` et `choisirMode`, commentaires sur badge alertes/copierIP/raccourcis.
- **`save_result.php`** : section "décisions d'architecture" — pas d'auth (IP comme identifiant implicite), délégation CIDR + cache, validation dans ResultatService pour PHPUnit. Explication résolution CIDR avec exemple.
- **`logs_admin.php`** : section "décisions d'architecture" — double passe verdict SQL+PHP justifiée, pourquoi sprintf() et non PDO pour le HAVING, pourquoi JOINTURES_LOGS en constante partagée.

### Correctifs

- **`frontend/admin/logs.php`** : suppression du double chargement de `utils.js` (causait `SyntaxError: const SEUILS already declared`).

---

## [1.9.9] — 2026-05-26

### Exports CSV/PDF — modal d'options générique

- **`utils.js`** : `ouvrirModalExport(config)` — modal générique réutilisable sur toutes les pages. Options : séparateur (`;` / `,`), encodage (UTF-8 BOM / UTF-8 pur), inclure statistiques, choix des colonnes à cocher. Préférences persistées dans `localStorage` (`mc_export_prefs`). Bouton "Tout décocher / Tout sélectionner" sur la grille de colonnes.
- **`utils.js`** : `telechargerCSV(lignes, nomFichier, separateur, bom)` — fonction partagée remplaçant les doublons locaux dans chaque fichier JS.
- **`alertes.js`** : export CSV refactorisé avec modal (11 colonnes sélectionnables). Export PDF ajouté (avec/sans code couleur, bloc stats).
- **`difference.js`** : export CSV refactorisé avec modal (7 colonnes). Export PDF ajouté (couleurs sur les écarts KO, stats sessions).
- **`mon_historique.js`** : export CSV refactorisé avec modal (6 colonnes). Export PDF ajouté.
- **`logs.js`** : export CSV refactorisé avec modal (6 colonnes, séparateur, BOM). Export PDF existant étendu avec stats dans le rapport.
- **`rapport_hebdo.js`** : `window.print()` remplacé par modal PDF avec option couleurs.
- **`recherche.js`** : export CSV ajouté (7 colonnes sélectionnables, affiché uniquement si résultats présents).
- **`audit.js`** : export CSV ajouté (6 colonnes incluant le détail diff en texte brut).
- **`get_logs.php`** : export CSV lit `separateur`, `bom`, `stats`, `colonnes` depuis GET. Stats calculées en PHP si `stats=1`.
- **`get_alertes.php`** : export CSV entièrement réécrit avec `fputcsv` inline, support 13 colonnes sélectionnables, séparateur, BOM, stats.
- **`fonts/style.css`** : styles `.export-modal-*`, `.export-toggle-btn`, `.export-colonnes-grid`, `.export-col-toggle-all` ajoutés (dark mode inclus).

### Styles PDF centralisés

- **`fonts/style.css`** : `.pdf-table`, `.pdf-stats`, `.pdf-meta`, `.pdf-stats-texte` + `@media print` commun centralisés — les `<style>` injectés dynamiquement dans chaque export PDF réduits à ~230 caractères (masquage UI uniquement).

### Alertes — exclusion télétravail

- **`get_alertes.php`** : `GXTELETRAV` exclu du filtre des sites insuffisants (mesures non représentatives du réseau WAN).

### Import de logs CSV (admin)

- **`backend/admin/import_logs.php`** (nouveau) : endpoint POST — UPSERT sur `ID_LOGS` via `ON DUPLICATE KEY UPDATE`. Auto-détection séparateur (`;` / `,`), gestion BOM, validation ligne par ligne (ID, date format `jj/mm/aaaa HH:ii`, mode, valeurs positives). Lignes `#` et lignes vides ignorées. Transaction unique — rollback en cas d'erreur BDD. Retourne `inseres`, `ecrases`, `ignores`, `erreurs[]`.
- **`frontend/admin/import_logs.php`** (nouveau) : page admin avec zone drag & drop, sélecteur fichier, résumé après import (compteurs + liste erreurs). Admin only (`requireAdmin()`).
- **`includes/header.php`** : lien "📥 Import logs" ajouté dans la navigation admin.

### Tooltips contextuels

- **`utils.js`** : système de tooltip générique — bulle unique `#ft-tooltip`, positionnée dynamiquement (au-dessus ou en-dessous selon l'espace). Délégation d'événements (`mouseenter`/`focusin`) — fonctionne sur les éléments générés dynamiquement. `ajouterAide(element, texte)` disponible globalement. Accessible au clavier (`tabindex="0"`, `aria-label`).
- **`fonts/style.css`** : `.ft-tooltip` (bulle sombre, flèche directionnelle, animation fade) + `.ft-aide` (badge `?` discret, dark mode).
- **`alertes.php`** : aides sur les 4 KPIs, 3 colonnes tableau, 3 labels filtres.
- **`alertes.js`** : aides dynamiques sur les labels Période, Métrique, Recherche.
- **`logs.php`** : aides sur les 6 colonnes tableau (Date, IP, Mode, Ping, DL, UL).
- **`rapport_hebdo.php`** : aides sur les 4 sections (Vue nationale, Régions, Top dégradés, Top meilleurs).
- **`rapport_hebdo.js`** : aides dynamiques sur les 4 KPI cards via `MutationObserver`.
- **`difference.php`** : aides sur la description écart, seuils admin, filtres site et KO.
- **`seuils.php`** : aides sur les colonnes Valeur bonne/mauvaise et les 3 métriques.

### Corrections de bugs

- **`alertes.php`** : `<script src="js/utils.js?v=1.9.8">` → `v=<?= APP_VERSION ?>` (causait ReferenceError: echapper is not defined).
- **`alertes.php`** : `<th>Logs</th>` manquant dans le `<thead>` (décalage colonnes).
- **`difference.js`, `mon_historique.js`** : `style.textContent` avec concaténation multi-lignes mal coupée par le patch regex → remplacement du bloc complet (SyntaxError: Unexpected token ':').

---

## [1.9.8] — 2026-05-22

### Seuils dérogatoires par site

- **`FT_SEUILS_SITE`** (nouvelle table) : seuils de qualité spécifiques par site — 6 colonnes DECIMAL nullable (DL/UL/PING × bon/mauvais), champ `RAISON`, `DATE_MAJ` auto, `MAJ_PAR` FK vers `FT_COMPTES`. FK CASCADE sur `FT_SITE`. NULL sur une colonne = conserver le seuil global.
- **`migration_seuils_site.sql`** : `CREATE TABLE IF NOT EXISTS` — à jouer une seule fois.
- **`SeuilService`** : nouvelles méthodes `chargerPourSite()` (fusion globaux + dérogation), `enregistrerDerogation()` (UPSERT, suppression auto si tout null, 0 traité comme null), `supprimerDerogation()`, `chargerDerogation()`.
- **`get_alertes.php`** : `chargerPourSite()` par site dans la boucle de filtrage. Champ `a_derogation` dans la réponse JSON et l'export CSV.
- **`rapport_hebdo.php`** : `chargerPourSite()` dans le filtre des sites insuffisants.
- **`get_site.php`** : champ `derogation_seuils` ajouté à la réponse JSON.
- **`modifier_site.php`** (backend + frontend + JS + CSS) : section "Seuils dérogatoires" avec 3 colonnes (DL/UL/Ping), badge "⚠ Actif", bouton reset, pré-remplissage depuis `derogation_seuils`.
- **`SeuilsDerogationTest.php`** (24 tests) : chargerPourSite, enregistrerDerogation, supprimerDerogation, impact sur verdict.

---

## [1.9.7] — 2026-05-22

### Rapport hebdomadaire — période configurable + verdicts KPI côté serveur

- Sélecteur 3 boutons : **7 jours** / **30 jours** / **Ce mois**. Rechargement sans rechargement de page.
- Backend : paramètre GET `jours` (7 | 30 | 0), validé contre whitelist. Mode "Ce mois" calcule les jours exacts depuis le 1er. Champ `periode` retourné dans le JSON.
- Verdicts KPI (`verdict_download/upload/ping`) calculés côté PHP via `SeuilService` et retournés dans `nationale` — plus de recalcul JS. Garantit la cohérence si les seuils changent.
- `rapport_hebdo.css` : styles `.rapport-periode-selector` / `.rapport-periode-btn` + dark mode + `@media print`.

---

## [1.9.6] — 2026-05-22

### Tests PHPUnit — couverture mode historique et rapport hebdomadaire

- **`GetlogsHistoriqueTest.php`** (37 tests) : mode historique de `get_logs.php` — WHERE `IP_CLIENT`, SELECT extra, noms et en-têtes CSV, filtres, pagination.
- **`RapportHebdoTest.php`** (35 tests) : filtrage insuffisants, top dégradés/bons, sites actifs, semaine ISO (S21 2026 = 18→24 mai), enrichissement verdicts, structure JSON.
- **Total PHPUnit** : 204 → **254 tests** — 0 échec.

---

## [1.9.5] — 2026-05-22

### Rapport hebdomadaire

- **`backend/admin/rapport_hebdo.php`** : endpoint JSON — agrège les 7 derniers jours (tests précis) : moyennes nationales, par région, sites sous seuil, top 5 dégradés/meilleurs. Utilise `StatService` + `SeuilService`, accessible aux techniciens et admins.
- **`frontend/admin/rapport_hebdo.php`** : page de consultation avec en-tête semaine ISO, 4 KPI nationaux avec skeleton loader, tableau régions avec verdict bilan, tableau sites insuffisants, top 5 dégradés / top 5 meilleurs.
- **`frontend/js/rapport_hebdo.js`** : JS vanilla — chargement async, recalcul verdicts côté client, helpers `fmt()` / `cellDebit()` / `htmlEsc()`, gestion erreurs.
- **`frontend/css/rapport_hebdo.css`** : styles dédiés — KPI cards avec barres colorées, skeleton animation, tableau rapport, top dégradés/bons côte à côte, impression CSS (`@media print`) pour export PDF natif via `window.print()`, dark mode complet.
- **`.htaccess`** : `rapport_hebdo.php` ajouté à la whitelist admin.
- **`header.php`** : lien « 📅 Rapport » ajouté en fin de nav admin.

## [1.9.4] — 2026-05-22

### Direction artistique — refonte style.css

- **Bandeau République** : liseré tricolore discret (::after) sous le bandeau DSI
- **Header** : hauteur fixe 64px, ombre légère, `box-shadow` sur la nav, z-index structuré
- **Tokens CSS** : nouveaux `--ft-shadow-xs/sm/md/lg`, `--ft-radius-sm/md/lg/xl`, `--ft-transition`, `--ft-border-light`, `--ft-card-bg` — cohérence partout
- **Fond page** : `#F4F5FA` (légèrement plus chaud, moins clinique que `#FAFAF7`)
- **Navigation** : hauteur 44px fixe, hover avec fond `rgba` discret, active avec fond léger
- **Boutons** : `box-shadow` avec depth (inset highlight + ombre externe), `translateY(-1px)` au hover, active press
- **Cartes** : `border-radius: 14px`, ombre `var(--ft-shadow-sm)`, hover translateY avec ombre md
- **Barres de progression** : dégradé `#283276 → #406BDE` au lieu de couleur plate
- **KPI cards** : bandes colorées en dégradé plutôt qu'aplat
- **Tableaux** : `border-radius` sur les coins de thead, `box-shadow` sur le wrapper
- **Inputs** : styles unifiés avec focus ring bleu 3px
- **Typographie** : `font-size: 15px` base, `letter-spacing` et `font-variant-numeric: tabular-nums` sur les valeurs
- **Dark mode** : fond `#0F1020` (plus profond), bleu accent `#7B9CF7`, bordures `#2A2E50`
- **Palettes daltonien** : inchangées (Wong 2011 — correctes)

## [1.9.3] — 2026-05-22

### Nettoyage et cohérence

- **`verdicthelper.php` supprimé** : fichier mort depuis la migration vers `SeuilService` (v1.8). Plus aucun fichier ne l'incluait.
- **`geocodage.php` supprimé** : script usage unique de géocodage, marqué ⚠ À SUPPRIMER depuis v1.7. Retiré aussi de la whitelist `.htaccess`.
- **Normalisation fins de ligne** : 37 fichiers PHP/JS/CSS convertis de CRLF → LF pour cohérence avec le reste du projet.
- **`index.js`** : commentaire de version mis à jour (v1.9.0 → v1.9.2).
- **`upload_measure.php`** : commentaire ajouté pour expliquer le `Access-Control-Allow-Origin: *` (nécessaire moteur speedtest, intranet uniquement).
- **`README.md`** : arborescence mise à jour (suppression `geocodage.php`, description corrigée de `speedtest.js`, ajout `speedtest_debug.js`).

## [1.9.2] — 2026-05-21

### Audit des modifications de sites

- **`FT_AUDIT_SITES`** : nouvelle table traçant chaque AJOUT / MODIFICATION / SUPPRESSION de site par les techniciens et admins. Champs : `ID_AUDIT`, `ID_COMPTE`, `ACTION` (enum), `ID_SITE` (VARCHAR 10), `NOM_SITE`, `DATE_ACTION`, `IP_ACTION`, `DETAIL` (JSON diff)
- **`AuditService.php`** : service centralisé d'écriture d'audit — calcul automatique du diff avant/après pour les modifications (seuls les champs réellement modifiés sont enregistrés), snapshot complet pour les ajouts et suppressions
- **`ajouter_site.php`**, **`modifier_site.php`**, **`supprimer_site.php`** : hook `AuditService::enregistrer()` ajouté après chaque opération. Pour la suppression, l'audit est écrit **dans la transaction** — un rollback annule aussi l'entrée d'audit
- **`get_audit.php`** : endpoint JSON paginé (filtres : compte, action, période, site) — accessible aux admins uniquement
- **`audit.php`** + **`audit.js`** + **`audit.css`** : page de consultation admin avec tableau diff visuel (avant/après par champ pour les modifications, liste des champs pour ajouts/suppressions), pagination, filtres, badges colorés par type d'action

### Gestion des sites — nouvelles fonctionnalités

- **`get_site.php`** : nouvel endpoint GET retournant toutes les infos d'un site (avec jointures région/interrégion) — découple la lecture de la modification
- **`get_departements.php`** : nouvel endpoint GET retournant la liste des départements pour le select du formulaire
- **`modifier_site.php` (frontend)** : champ département passe de `<input readonly>` à `<select>` peuplé depuis `get_departements.php`. Chargement parallèle (`Promise.all`) données site + liste départements. Région mise à jour automatiquement après sauvegarde
- **`profil.php`** : ajout d'un bloc "Mes modifications de sites" avec lien vers `audit.php` pré-filtré sur le compte connecté

### Page "Mon historique" (agents)

- **`mon_historique.php`** + **`mon_historique.js`** : nouvelle page accessible à tous les agents connectés. Affiche les tests effectués depuis le poste courant (filtre sur `IP_CLIENT`). Filtres mode/verdict/date, stats agrégées (avg/min/max), graphique Chart.js double axe (Mbit/s + ms), export CSV, pagination
- **`get_logs.php`** : ajout du mode `historique` — filtre sur `IP_CLIENT` de session au lieu de `CODE_GX_SITE`. Affiche `CODE_GX_SITE` au lieu de `IP_CLIENT` dans la réponse. Corrections de bugs : `$requeteExport` non définie, `$vPing/$vDl/$vUl` → `$verdictPing/$verdictDl/$verdictUl` dans le filtre verdict

### CSS — refonte dark mode et cohérence

- **`audit.css`** : variables custom (`--bleu-ft`, `--card-bg`, `--border-color`, `--texte-secondaire`) remplacées par les variables globales de `style.css` (`--ft-blue`, `--ft-border`, etc.)
- **`modifier_site.css`** : styles du `<select>` département ajoutés (padding, focus, dark mode). `max-width: 640px` → `100%`, `row-3` : `2fr 1fr 1fr` → `1fr 1fr 2fr`, `min-width: 0` sur les labels — corrige les débordements
- **`logs.css`** : header tableau passe de `#f0f0f0` à `var(--ft-blue)`, cohérence avec le reste
- **`recherche.css`** : pagination dupliquée (`.pagination-bar` + `.pagination-wrapper`) supprimée — `.pagination-wrapper` et `.btn-page` centralisés dans `style.css`
- **`login.css`**, **`comptes.css`**, **`profil.css`**, **`seuils.css`**, **`commentaires.css`** : dark mode ajouté, headers tableaux en `var(--ft-blue)`, variables globales utilisées
- **`config_speedtest.css`** : dark mode ajouté pour badges de mode et alertes de validation
- **`logs_admin.css`**, **`index.css`** : CRLF → LF

### Corrections de bugs

- **`site_info.php`** : `$resultats` → `$results` (ligne 104 et 124), `$vPing/$vDl/$vUl` → `$verdictPing/$verdictDl/$verdictUl` (ligne 112) — `dernier_verdict` retournait toujours `null`
- **`get_audit.php`** : `LIMIT/OFFSET` passés via `bindValue(..., PDO::PARAM_INT)` au lieu de `execute([..., $limite, $offset])` — MariaDB refusait les strings pour `LIMIT`
- **`get_audit.php`** : `$lignes = $requeteData->fetchAll()` manquant après `execute()`
- **`modifier_site.php` (frontend)** : `statistique.css` retiré des includes (faux conflit sur `.btn-save`)

### VBA — `ExportSQL.bas`

- **`ExportCreateDatabase`** : ajout de `FT_AUDIT_SITES` dans le schéma, colonnes `MODE` et `SESSION_ID` ajoutées à `FT_LOGS`, seuils `difference_seuil_ping` / `difference_seuil_upload` ajoutés
- **`ExportCleanSQL`** : `DROP TABLE IF EXISTS FT_AUDIT_SITES` ajouté en premier (FK sur `FT_COMPTES`)
- **`ExportSeuilsSQL`** : ajout des deux nouveaux seuils `difference_seuil_ping` (15 %) et `difference_seuil_upload` (30 %)
- **`ExportMigrationAuditSQL`** (nouvelle macro) : génère `migration_audit.sql` — à jouer une seule fois sur BDD existante : `CREATE TABLE IF NOT EXISTS FT_AUDIT_SITES`, `ALTER TABLE MODIFY ID_SITE VARCHAR(10)`, `INSERT IGNORE` des nouveaux seuils, `ADD COLUMN IF NOT EXISTS` pour `MODE` et `SESSION_ID` sur `FT_LOGS`
- **`ExportAuditSQL`** (nouvelle macro) : génère un modèle `export_audit.sql` avec instructions pour exporter les données via phpMyAdmin
- **`ExportTout`** : appelle `ExportMigrationAuditSQL` en fin de séquence, message final affiche l'ordre d'exécution complet
- **Commentaires** : tous les blocs refactorisés avec en-têtes, commentaires inline sur la logique CIDR, section séparées par des bandeaux

---

## [1.9.1] — 2026-05-20

### Moteur speedtest — Préchauffage TCP (`speedtest.js`)

- **Préchauffage download** : ajout de `prechauffageDownload()` — stream sacrifié de 1,2 s avant chaque mesure de téléchargement. Élimine les faux écarts rapide/précis causés par le slow start TCP (connexion non établie à plein régime au début de la mesure).
- **Préchauffage upload** : ajout de `prechauffageUpload()` — POST de 256 Ko avant chaque mesure d'envoi, même raison.
- **`parallel` forcé à 1** dans `migrerConfig()` : même si la BDD envoie `parallel > 1`, la valeur est systématiquement ramenée à 1. Plusieurs streams simultanés faussent le calcul sur les liaisons WAN asymétriques (le stream le plus rapide tire le dénominateur vers le bas).
- **Durées fast par défaut** : `dureeMsDownload` et `dureeMsUpload` passées de 2000 ms à 3000 ms pour laisser davantage de temps au slow start TCP de se stabiliser.
- Constantes nommées : `DUREE_PRECHAUFFAGE_DOWNLOAD_MS = 1200`, `DUREE_PRECHAUFFAGE_UPLOAD_MS = 800`.

### Configuration BDD (`FT_CONFIG_SPEEDTEST`)

- `fast_download_parallel` et `fast_upload_parallel` : 3 → **1**
- `precise_download_parallel` et `precise_upload_parallel` : 3 → **1**
- `fast_download_dureeMsDownload` et `fast_upload_dureeMsUpload` : 2000 → **3000** ms

### Architecture JS — Modules ES6 (`statistique.js`)

- **Migration Alpine.js → Vanilla JS** : Alpine.js incompatible avec la CSP du serveur (`unsafe-eval` interdit). Réécriture complète en vanilla JS DOM.
- **Découpage en modules ES6** : `statistique.js` (point d'entrée) importe 5 modules dans `js/stats/` :
  - `state.js` — source de vérité centrale (`STATE`)
  - `api.js` — couche HTTP (appels `stat.php`)
  - `verdicts.js` — calcul verdicts + pastilles colorées
  - `charts.js` — helpers Chart.js (`creerGraphBarre`, `creerGraphLigne`, `creerGraphDoughnut`)
  - `filtres.js` — filtrage données + KPIs + santé + top/flop + bandeaux
  - `panels.js` — logique de chaque onglet du dashboard
- `<script type="module">` dans `statistique.php` — aucune dépendance externe, zéro contrainte CSP.
- **Carte Leaflet** maintenue dans `statistique.js` directement (hors modules) pour éviter les problèmes d'initialisation avec les panels `display:none`. CSS Leaflet ajouté dans `<head>` (cause du bug d'affichage).

### Sécurité SQL

- **`StatService::comparaisonModes()`** : remplacement de `pdo->quote($idSite)` par `prepare()` + `bindParam(':site_id')` — élimine le dernier `pdo->quote()` du projet.
- **`logs_admin.php` HAVING** : valeurs numériques de seuils formatées via `sprintf('%.4f')` au lieu d'une interpolation directe — défense en profondeur.
- **`difference.php`** : `display_errors` remis à `0` (était à `1`, oubli de debug).

### Documentation et qualité code

- **PHPDoc complet** sur tous les services : `StatService`, `QueueService`, `SeuilService`, `SiteResolverService`, `CacheService`, `ResultatService`.
- **JSDoc complet** sur tous les modules JS : `state.js`, `api.js`, `verdicts.js`, `charts.js`, `filtres.js`, `panels.js`, `index.js`, `logs.js`, `recherche.js`, `utils.js`, `difference.js`.
- **Variables françaises** dans l'ensemble des backends et modules JS.