<?php
// erreur.php — Page d'erreur personnalisée (403, 404, 500).
$code = intval($_GET['code'] ?? 404);
$code = in_array($code, [403, 404, 500], true) ? $code : 404;

$messages = [
    403 => [
        'titre'   => 'Accès refusé',
        'desc'    => 'Vous n\'avez pas les droits nécessaires pour accéder à cette ressource.',
        'icone'   => '🔒',
        'conseil' => 'Si vous pensez que c\'est une erreur, contactez votre administrateur.',
    ],
    404 => [
        'titre'   => 'Page introuvable',
        'desc'    => 'La page demandée n\'existe pas ou a été déplacée.',
        'icone'   => '🔍',
        'conseil' => 'Vérifiez l\'URL ou utilisez la navigation ci-dessous.',
    ],
    500 => [
        'titre'   => 'Erreur serveur',
        'desc'    => 'Une erreur interne s\'est produite. L\'équipe technique a été notifiée.',
        'icone'   => '⚙️',
        'conseil' => 'Réessayez dans quelques instants.',
    ],
];

$msg = $messages[$code];
http_response_code($code);

// Liens de navigation utiles (adaptés selon si connecté ou non)
session_start();
$estConnecte = !empty($_SESSION['utilisateur_id']);
$estAdmin    = !empty($_SESSION['is_admin']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no">
    <title><?= htmlspecialchars($msg['titre']) ?> — Ma Connexion</title>
    <link rel="icon" type="image/png" href="/frontend/fonts/favicon.png">
    <link rel="stylesheet" href="/frontend/fonts/style.css?v=<?= defined('APP_VERSION') ? APP_VERSION : '1' ?>">
    <script>const APP_VERSION = "<?= defined('APP_VERSION') ? APP_VERSION : '1.9.8' ?>";</script>
    <style>
        .erreur-page {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
            text-align: center;
            padding: 3rem 2rem;
        }
        .erreur-code {
            font-size: 6rem;
            font-weight: 800;
            color: var(--ft-border, #d0d7f0);
            line-height: 1;
            margin-bottom: 0.5rem;
            letter-spacing: -4px;
        }
        .erreur-icone {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .erreur-titre {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--ft-primary, #283276);
            margin-bottom: 0.5rem;
        }
        .erreur-desc {
            font-size: 14px;
            color: var(--ft-muted, #6b7a9e);
            max-width: 420px;
            margin-bottom: 0.4rem;
        }
        .erreur-conseil {
            font-size: 13px;
            color: var(--ft-muted, #6b7a9e);
            margin-bottom: 2rem;
        }
        .erreur-liens {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .erreur-btn {
            display: inline-block;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            font-family: "Marianne", sans-serif;
            transition: background .15s, color .15s;
        }
        .erreur-btn--primary {
            background: var(--ft-blue, #406BDE);
            color: #fff;
        }
        .erreur-btn--primary:hover { background: var(--ft-primary, #283276); }
        .erreur-btn--secondary {
            background: transparent;
            color: var(--ft-blue, #406BDE);
            border: 1.5px solid var(--ft-blue, #406BDE);
        }
        .erreur-btn--secondary:hover { background: #f0f3ff; }
        .erreur-divider {
            width: 48px;
            height: 3px;
            background: var(--ft-red, #E1000F);
            border-radius: 2px;
            margin: 1rem auto 1.5rem;
        }
        @media (prefers-color-scheme: dark) {
            .erreur-code  { color: #2e3260; }
            .erreur-titre { color: #7b9af7; }
            .erreur-btn--secondary { color: #7b9af7; border-color: #7b9af7; }
            .erreur-btn--secondary:hover { background: #252740; }
        }
    </style>
</head>
<body>

<div class="bandeau-dsi">
    Outil interne — DSI France Travail &nbsp;·&nbsp; Usage strictement réservé aux agents
</div>

<div class="header">
    <div class="logo-bar">
        <img src="/frontend/fonts/logo_ft.svg" alt="France Travail" height="40" style="height:40px;width:auto;">
        <div class="logo-separateur"></div>
        <span class="logo-text">Ma Connexion</span>
    </div>
</div>

<div class="erreur-page">
    <div class="erreur-code"><?= $code ?></div>
    <div class="erreur-icone"><?= $msg['icone'] ?></div>
    <div class="erreur-titre"><?= htmlspecialchars($msg['titre']) ?></div>
    <div class="erreur-divider"></div>
    <div class="erreur-desc"><?= htmlspecialchars($msg['desc']) ?></div>
    <div class="erreur-conseil"><?= htmlspecialchars($msg['conseil']) ?></div>

    <div class="erreur-liens">
        <a href="index.php" class="erreur-btn erreur-btn--primary">
            ← Accueil
        </a>
        <?php if ($estConnecte): ?>
        <a href="/frontend/recherche.php" class="erreur-btn erreur-btn--secondary">
            🔍 Recherche
        </a>
        <a href="/frontend/alertes.php" class="erreur-btn erreur-btn--secondary">
            ⚠ Alertes
        </a>
        <?php if ($estAdmin): ?>
        <a href="/frontend/admin/rapport_hebdo.php" class="erreur-btn erreur-btn--secondary">
            📅 Rapport
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/frontend/includes/footer.php'; ?>
</body>
</html>