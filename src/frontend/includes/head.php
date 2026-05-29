<?php
// frontend/includes/head.php
// Inclure dans le <head> de chaque page.
// $pageTitle doit être défini avant l'include.
$phpSelf = $_SERVER['PHP_SELF'] ?? '';
if (str_contains($phpSelf, '/frontend/admin/')) {
    $assetsRoot = '../../frontend/';
} elseif (str_contains($phpSelf, '/frontend/')) {
    $assetsRoot = '../frontend/';
} else {
    $assetsRoot = 'frontend/';
}
?>
<meta charset="UTF-8">
<script>
// Appliqué avant tout rendu pour éviter le flash du mode daltonien au changement de page
(function(){
    var d = localStorage.getItem('fd_daltonien_mode');
    if(d && ['deuteranopie','protanopie','tritanopie','achromatopsie'].includes(d))
        document.documentElement.classList.add('daltonien','daltonien-'+d);
})();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Ma Connexion' : 'Ma Connexion' ?></title>
<!-- Préchargement des polices Marianne — évite le FOUT (flash de police au chargement) -->
<link rel="preload" href="<?= $assetsRoot ?>fonts/Marianne-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $assetsRoot ?>fonts/Marianne-Medium.woff2"  as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= $assetsRoot ?>fonts/Marianne-Bold.woff2"    as="font" type="font/woff2" crossorigin>
<link rel="icon" type="image/png" href="<?= $assetsRoot ?>fonts/favicon.png">
<link rel="stylesheet" href="<?= $assetsRoot ?>fonts/style.css?v=<?= defined('APP_VERSION') ? APP_VERSION : '1' ?>">
<script src="<?= $assetsRoot ?>js/utils.js?v=<?= defined('APP_VERSION') ? APP_VERSION : '1' ?>"></script>
<script>const APP_VERSION = "<?= defined('APP_VERSION') ? APP_VERSION : '1.8.0' ?>";</script>