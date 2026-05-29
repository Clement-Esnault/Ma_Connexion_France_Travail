<?php
/**
 * config.php — Configuration centrale de Ma Connexion
 *
 * Inclus par require_once dans tous les scripts backend.
 * Fournit : $pdo (connexion MySQL), APP_VERSION, APP_ENV.
 *
 * ── Fichier .env ──────────────────────────────────────────────────────────────
 * Placé dans htdocs/ (un niveau AU-DESSUS du dossier speedtest/).
 * Jamais servi par Apache grâce au .htaccess racine.
 *
 * Variables attendues :
 *   DB_HOST      → adresse MySQL (127.0.0.1 recommandé sur XAMPP Windows,
 *                  'localhost' peut tenter une socket Unix inexistante)
 *   DB_USERNAME  → utilisateur MySQL (ex: root sur XAMPP dev)
 *   DB_PASSWORD  → mot de passe MySQL
 *   DB_NAME      → nom de la base (ft_speedtest)
 *   APP_ENV      → 'development' | 'production' (contrôle l'affichage des erreurs)
 *
 * Modèle disponible dans .env.example à la racine du projet.
 *
 * ── APP_VERSION ───────────────────────────────────────────────────────────────
 * Utilisée comme paramètre de cache-busting sur tous les assets CSS/JS :
 *   <link href="style.css?v=<?= APP_VERSION ?>">
 * Incrémenter à chaque déploiement pour forcer le rechargement navigateur.
 */

$envFile = __DIR__ . '/../../.env';

if ($envFile === false || !file_exists($envFile)) {
    die(json_encode(['error' => 'Fichier .env introuvable. Placez-le dans htdocs/ à côté du dossier speedtest/.']));
}

$env = parse_ini_file($envFile);

$host        = $env['DB_HOST']     ?? '127.0.0.1';
$db_username = $env['DB_USERNAME'] ?? '';
$db_password = $env['DB_PASSWORD'] ?? '';
$dbname      = $env['DB_NAME']     ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    // ERRMODE_EXCEPTION : toutes les erreurs PDO lèvent une exception catchable
    // plutôt que de retourner false silencieusement — indispensable pour les
    // transactions dans QueueService et les try/catch défensifs de SeuilService.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    define('APP_ENV',     $env['APP_ENV'] ?? 'production');
    define('APP_VERSION', '1.10.0');
} catch (PDOException $e) {
    // En production : ne pas exposer les détails de connexion dans la réponse.
    // En développement : le message PDO inclut host/dbname pour faciliter le debug.
    die(json_encode(['error' => $e->getMessage()]));
}