# CHANGELOG — Ma Connexion

## [1.10.0] — 2026-05-28

### Mode de test unique — suppression du mode rapide

- **`index.php`** : suppression de `renderSectionResultats('fast', …)` — la section `#section-fast` n'est plus rendue. Le titre de section a également été supprimé (`renderSectionResultats` simplifié). Description de page ajoutée : "Diagnostiquez la qualité de votre connexion réseau interne en quelques secondes."
- **`index.js`** : refonte complète — `PHASES` réduit au mode `precise` uniquement. `lancerTestComplet()` enchaîne directement ping → download → upload sans phase rapide préalable. `afficherPhase()` supprimé (plus de label de phase intermédiaire). Timeout 4 s sur la détection IP (fallback `—` propre). Spinner inline sur le bouton pendant le test (`::before` + `@keyframes btn-spin`).
- **`speedtest.js`** : `CONFIGS_MODE` réduit à `precise` uniquement. `estConfigValide()` ne vérifie plus que `['precise']`. `migrerConfig()` boucle sur `['precise']` uniquement. Valeurs fallback alignées : `echantillons: 10`, `delay: 30`, `dureeMsDownload/Upload: 6000`, `tailleMoBlob: 20`.
- **`ResultatService`** : `MODES_VALIDES = ['precise']` — le mode `fast` n'est plus accepté en insertion.
- **`get_config_speedtest.php`** : bloc `fast` supprimé de `configParDefaut()` et de la reconstruction `$config`. Commentaire de rétropédalage conservé dans l'en-tête pour faciliter un éventuel retour arrière (les lignes `fast_*` sont toujours présentes en BDD).
- **`stat.php`** : `$modesValides = ['precise']` — le filtre GET `mode=fast` n'est plus accepté.
- **`config_speedtest.js`** : toutes les boucles `['precise', 'fast']` remplacées par `['precise']`. Les champs `fast_*` ne sont plus rendus dans le formulaire admin.
- **`panels.js`** : `chargerComparaison()` réécrit — tableau à 2 colonnes (résultat + écart-type), graphe à 1 série. La colonne "Mode rapide" vide est supprimée.
- **`index.css`** : variables CSS `--ft-fast-*` supprimées (light + dark). Règles `#section-fast` et `.section-titre-*` supprimées. Règle orpheline `.section-titre-note { display:none }` supprimée du media query mobile. Spinner `.btn-lancer.running::before` ajouté.
- **`tests/js/test.speedtest.js`** : 4 tests `dureePrechauffage()` mis à jour (plancher 2500 ms, proportion 0,25). `testModeFastAccepte` supprimé. `estConfigValide` : config valide sans clé `fast`. `migrerConfig` : suppression des assertions `fast.*`, ajout d'un test de migration depuis ancienne config sans `dureeMsDownload`.
- **`tests/php/ResultatserviceTest.php`** : `testModeFastAccepte` → `testModeFastRefuse` avec `assertNotNull`.

### Préchauffage TCP renforcé — stabilisation upload WAN

- **`speedtest.js`** : `PRECHAUFFAGE_MIN_MS` : 1500 → **2500 ms**. `PRECHAUFFAGE_PROPORTION` : 0,15 → **0,25**. Blob de préchauffage upload : 256 Ko → **4 Mo** (permet d'établir correctement la fenêtre TCP sur liaison WAN longue distance avant la mesure).
- **Configuration BDD** recommandée : `precise_download_dureeMsDownload` → 6000, `precise_upload_dureeMsUpload` → 6000, `precise_upload_tailleMoBlob` → 20.

### Sécurité et hardening serveur

- **`.htaccess`** : `Options -Indexes` ajouté — désactive l'affichage des index de répertoire sur l'ensemble du projet (les répertoires sans `index.php` retournent 403 au lieu de lister leur contenu).
- **`save-notice`** : message d'IP inconnue allégé — ne révèle plus l'IP cliente ni le message technique en cas de site non reconnu.

### Nettoyage et qualité code

- Tous les commentaires JSDoc `{'fast'|'precise'}` → `{'precise'}` dans `speedtest.js`.
- Mention "Durées fast augmentées" supprimée du CHANGELOG interne JSDoc.
- `panels.js` : import inutilisé `modeRapide` supprimé.
- **320 tests PHPUnit — 826 assertions — 0 échec.**
- **78 tests QUnit JavaScript — 0 échec.**

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
- **`SeuilsDerogationTest.php`** (24 tests) : chargerPourSite, enregistrerDerogation, supprimerDerogation, impact sur verdict. Total : 254 tests — 0 échec.

### Rapport hebdomadaire — période configurable + verdicts KPI serveur (v1.9.7)

- Sélecteur 3 boutons 7 jours / 30 jours / Ce mois dans la toolbar, rechargement sans reload.
- Backend : paramètre GET `jours` validé contre whitelist `[7, 30, 0]`, champ `periode.libelle` retourné.
- Verdicts KPI nationaux calculés côté PHP via `SeuilService` — garantit la cohérence si les seuils changent.

### Tests PHPUnit — couverture mode historique et rapport (v1.9.6)

- **`GetlogsHistoriqueTest.php`** (37 tests) : mode historique `get_logs.php` — clause WHERE IP_CLIENT, SELECT extra, CSV noms/en-têtes/lignes, modes valides, pagination.
- **`RapportHebdoTest.php`** (35 tests) : filtrage insuffisants, top dégradés/bons, comptage sites actifs, infos semaine ISO, verdicts, structure JSON. Total : 230 tests — 0 échec.

### Rapport hebdomadaire (v1.9.5)

- **`backend/admin/rapport_hebdo.php`** : endpoint JSON 7j — nationales, par région, sites insuffisants, top 5 dégradés/meilleurs, infos semaine ISO.
- **`frontend/admin/rapport_hebdo.php`** + **`rapport_hebdo.js`** + **`rapport_hebdo.css`** : page de consultation avec 4 KPI, skeleton loaders, export PDF natif via `window.print()`, lien « 📅 Rapport » dans la nav admin.

### Direction artistique France Travail (v1.9.4)

- **`style.css`** entièrement réécrit (1492 lignes) : liseré tricolore République sur le bandeau DSI, tokens CSS `--ft-shadow-*/radius-*/transition`, fond page `#F4F5FA`, boutons avec depth (inset highlight + translateY), barres de progression en dégradé, KPI cards avec bandes colorées, navigation hauteur fixe 44px, dark mode `#0F1020` plus profond, palettes daltonien inchangées (Wong 2011).

### Nettoyage et cohérence (v1.9.3)

- **`verdicthelper.php`** supprimé (fichier mort depuis `SeuilService`).
- **`geocodage.php`** supprimé + retiré de la whitelist `.htaccess`.
- 37 fichiers PHP/JS/CSS normalisés CRLF → LF.
- **`index.js`** : commentaire de version corrigé (v1.9.0 → v1.9.2).
- **`README.md`** : arborescence mise à jour.

---

## [1.9.9] — 2026-05-26

### Exports CSV/PDF — modal d'options générique

- **`utils.js`** : `ouvrirModalExport(config)` — modal générique réutilisable sur toutes les pages. Options : séparateur (`;` / `,`), encodage (UTF-8 BOM / UTF-8 pur), inclure statistiques, choix des colonnes à cocher. Préférences persistées dans `localStorage` (`mc_export_prefs`). Bouton "Tout décocher / Tout sélectionner".
- **`utils.js`** : `telechargerCSV()` — fonction partagée remplaçant les doublons locaux.
- **`alertes.js`** : export CSV avec modal (11 colonnes). Export PDF ajouté (couleurs, stats).
- **`difference.js`** : export CSV avec modal (7 colonnes). Export PDF ajouté.
- **`mon_historique.js`** : export CSV et PDF avec modal.
- **`logs.js`** : export CSV avec modal (6 colonnes). Export PDF étendu avec stats.
- **`rapport_hebdo.js`** : `window.print()` remplacé par modal PDF avec option couleurs.
- **`recherche.js`** : export CSV ajouté (7 colonnes, affiché uniquement si résultats).
- **`audit.js`** : export CSV ajouté (6 colonnes dont diff en texte brut).
- **`statistique.js`** : export CSV + PDF ajoutés sur les onglets sites/régions/départements/interrégions.
- **`get_logs.php`** : export CSV lit `separateur`, `bom`, `stats`, `colonnes`. Stats calculées en PHP.
- **`get_alertes.php`** : export CSV réécrit avec `fputcsv` inline, 13 colonnes sélectionnables.
- **`fonts/style.css`** : styles `.export-modal-*`, `.export-toggle-btn`, `.export-colonnes-grid` (dark mode).

### Styles PDF centralisés

- **`fonts/style.css`** : `.pdf-table`, `.pdf-stats`, `.pdf-meta` + `@media print` commun — `<style>` injectés dynamiquement réduits à ~230 caractères.

### Alertes — exclusion télétravail

- **`get_alertes.php`** : `GXTELETRAV` exclu du filtre des sites insuffisants (mesures VPN non représentatives).

### Import de logs CSV (admin)

- **`backend/admin/import_logs.php`** (nouveau) : UPSERT sur `ID_LOGS` via `ON DUPLICATE KEY UPDATE`. Auto-détection séparateur, gestion BOM, validation ligne par ligne. Transaction unique — rollback si erreur BDD.
- **`frontend/admin/import_logs.php`** (nouveau) : page admin drag & drop, résumé après import. Admin only.
- **`includes/header.php`** : lien "📥 Import logs" dans la navigation admin.

### Tooltips contextuels

- **`utils.js`** : système de tooltip générique — bulle `#ft-tooltip`, `ajouterAide(element, texte)` global. Accessible clavier (`tabindex`, `aria-label`).
- **`fonts/style.css`** : `.ft-tooltip` + `.ft-aide` (badge `?`, dark mode).
- Aides ajoutées sur : `alertes.php` (4 KPIs + 3 colonnes + 3 filtres), `logs.php` (6 colonnes), `rapport_hebdo.php` (4 sections), `difference.php` (4 éléments), `seuils.php` (5 éléments).

### Page d'erreur améliorée

- **`erreur.php`** : refonte complète — grand code 404/403/500, icône, liens de navigation contextuels (Accueil, Recherche, Alertes, Rapport selon rôle). Gestion 500 ajoutée. Chemins absolus pour compatibilité redirect Apache.

### Corrections de bugs

- **`alertes.php`** : `utils.js` en version codée en dur → `APP_VERSION` (ReferenceError echapper).
- **`alertes.php`** : `<th>Logs</th>` manquant dans le `<thead>`.
- **`difference.js`**, **`mon_historique.js`** : `style.textContent` multi-lignes mal coupé (SyntaxError).
- **`seuils.php`** : balises HTML injectées dans un array PHP → déplacées dans le rendu HTML.

---

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
