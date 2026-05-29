<?php
session_set_cookie_params([
    'lifetime' => 0,          // expire à la fermeture du navigateur
    'path'     => '/',
    'secure'   => false,      // passer à true si TLS activé sur la VM
    'httponly' => true,       // inaccessible via JS (protection XSS)
    'samesite' => 'Strict',   // protection CSRF sur les cookies
]);
session_start();
$estConnecte = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// ── Expiration de session après 30 min d'inactivité ───────────────────
const SESSION_TIMEOUT = 1800; // 30 minutes en secondes

if ($estConnecte) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Session expirée — déconnexion propre
        session_unset();
        session_destroy();
        $estConnecte = false;
        // Redirection uniquement pour les requêtes HTML (pas les appels API)
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) && !str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Location: /login.php?error=session_expired');
            exit;
        }
    } else {
        // Mettre à jour le timestamp d'activité
        $_SESSION['last_activity'] = time();
    }
} elseif (isset($_SESSION['last_activity'])) {
    // Session non authentifiée mais avec activité — nettoyer
    session_unset();
    session_destroy();
}

// Génération du token CSRF — créé une seule fois par session
if ($estConnecte && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Vérifie le token CSRF d'une requête POST.
 * À appeler en début de chaque traitement POST sensible.
 */
function verifyCsrf(): void {
    $jeton = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $jeton)) {
        http_response_code(403);
        die(json_encode(['error' => 'Token CSRF invalide.']));
    }
}

/**
 * Retourne le champ hidden CSRF à insérer dans les formulaires HTML.
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

/**
 * Retourne le lien de retour en utilisant le Referer HTTP si disponible,
 * avec un fallback vers la page d'accueil selon le contexte (admin ou non).
 *
 * Le Referer est validé pour rester sur le même domaine (protection open redirect).
 */
function lien_retour(): string {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    // Valider que le referer appartient au même serveur (anti open-redirect)
    if ($referer !== '') {
        $host    = $_SERVER['HTTP_HOST'] ?? '';
        $parsed  = parse_url($referer);
        $refHost = ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

        if ($refHost === $host || $refHost === '') {
            return htmlspecialchars($referer, ENT_QUOTES);
        }
    }

    // Fallback selon le contexte
    $estAdmin = str_contains($_SERVER['PHP_SELF'], '/admin/');
    return $estAdmin ? '../../' : '../';
}
?>