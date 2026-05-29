<?php
$phpSelf = $_SERVER['PHP_SELF'] ?? '';
if (str_contains($phpSelf, '/frontend/admin/')) {
    $rootPath = '../../frontend/';
} elseif (str_contains($phpSelf, '/frontend/')) {
    $rootPath = '../frontend/';
} else {
    $rootPath = 'frontend/';
}
?>

<!-- Bandeau DSI -->
<div class="bandeau-dsi">
    Outil interne — DSI France Travail &nbsp;·&nbsp; Usage strictement réservé aux agents
</div>

<div class="header">
    <div class="logo-bar">
        <img src="<?= $rootPath ?>fonts/logo_ft.svg"
            alt="France Travail" height="40"
            style="height:40px; width:auto;">
        <div class="logo-separateur"></div>
        <span class="logo-text">Ma Connexion</span>
    </div>
    <?php if ($estConnecte): ?>
    <div class="ip-badge" style="margin:0;">
        Connecté : <?= htmlspecialchars($_SESSION['alias']) ?> <button class="btn-copier-ip" onclick="copierIP()" title="Copier mon IP" id="btn-copier-ip">📋</button>
        <?= $_SESSION['is_admin'] ? '<span class="badge-admin">[Admin]</span>' : '' ?>
        — <a href="<?= $rootPath ?>profil.php" class="logout-link">Mon profil</a>
        — <a href="/login.php?logout" class="logout-link">Déconnexion</a>
        — <div class="daltonien-wrapper">
            <button id="btn-daltonien" class="btn-daltonien" onclick="toggleMenuDaltonien()" title="Accessibilité visuelle">
                👁 <span id="daltonien-label">Normal</span> ▾
            </button>
            <div id="daltonien-menu" class="daltonien-menu hidden" role="menu">
                <button class="daltonien-option" onclick="choisirMode('')">🔵 Normal</button>
                <button class="daltonien-option" onclick="choisirMode('deuteranopie')">🟡 Deutéranopie <small>rouge-vert (vert)</small></button>
                <button class="daltonien-option" onclick="choisirMode('protanopie')">🟠 Protanopie <small>rouge-vert (rouge)</small></button>
                <button class="daltonien-option" onclick="choisirMode('tritanopie')">🟣 Tritanopie <small>bleu-jaune</small></button>
                <button class="daltonien-option" onclick="choisirMode('achromatopsie')">⬜ Achromatopsie <small>sans couleur</small></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php if ($estConnecte): ?>
<?php
$_navSelf    = $_SERVER['PHP_SELF'] ?? '';
$_navBase    = basename($_navSelf);
$_navIsAdmin = str_contains($_navSelf, '/admin/');
$_estAdmin   = $_SESSION['is_admin'] ?? false;
?>
<nav class="main-nav">
    <a href="/login.php"                            class="nav-link <?= $_navBase === 'login.php'       ? 'active' : '' ?>">🏠 Accueil</a>
    <a href="<?= $rootPath ?>recherche.php"         class="nav-link <?= $_navBase === 'recherche.php'   ? 'active' : '' ?>">🔍 Recherche</a>
    <a href="<?= $rootPath ?>statistique.php"       class="nav-link <?= $_navBase === 'statistique.php' ? 'active' : '' ?>">📊 Statistiques</a>
    <a href="<?= $rootPath ?>alertes.php"           class="nav-link <?= $_navBase === 'alertes.php'     ? 'active' : '' ?>" id="nav-alertes">🔔 Alertes <span id="badge-nav-alertes" class="badge-nav hidden"></span></a>
    <a href="<?= $rootPath ?>difference.php"        class="nav-link <?= $_navBase === 'difference.php'  ? 'active' : '' ?>">🆚 Différence</a>
    <a href="<?= $rootPath ?>mon_historique.php"   class="nav-link <?= $_navBase === 'mon_historique.php' ? 'active' : '' ?>">📈 Mon historique</a>
    <a href="<?= $rootPath ?>admin/gestion_sites.php" class="nav-link <?= $_navBase === 'gestion_sites.php' ? 'active' : '' ?>">🏢 Sites</a>
    <?php if ($_estAdmin): ?>
    <span class="nav-sep">│</span>
    <a href="<?= $rootPath ?>admin/seuils.php"          class="nav-link <?= $_navBase === 'seuils.php'          ? 'active' : '' ?>">⚙ Seuils</a>
    <a href="<?= $rootPath ?>admin/comptes.php"         class="nav-link <?= $_navBase === 'comptes.php'         ? 'active' : '' ?>">👥 Comptes</a>
    <a href="<?= $rootPath ?>admin/config_speedtest.php" class="nav-link <?= $_navBase === 'config_speedtest.php' ? 'active' : '' ?>">⚙ SpeedTest</a>
    <a href="<?= $rootPath ?>admin/logs.php"            class="nav-link <?= ($_navBase === 'logs.php' && $_navIsAdmin) ? 'active' : '' ?>">📋 Logs</a>
    <a href="<?= $rootPath ?>admin/audit.php"           class="nav-link <?= $_navBase === 'audit.php'           ? 'active' : '' ?>">🔍 Audit</a>
    <a href="<?= $rootPath ?>admin/rapport_hebdo.php"    class="nav-link <?= $_navBase === 'rapport_hebdo.php'   ? 'active' : '' ?>">📅 Rapport</a>
    <a href="<?= $rootPath ?>admin/import_logs.php"    class="nav-link <?= $_navBase === 'import_logs.php'    ? 'active' : '' ?>">&#128229; Import logs</a>
    <?php endif; ?>
</nav>
<script src="<?= $rootPath ?>js/header.js?v=<?= APP_VERSION ?>" defer></script>
<?php endif; ?>