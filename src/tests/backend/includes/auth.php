<?php
// Fonctions de contrôle d'accès centralisées.
// À inclure dans tous les fichiers backend et frontend protégés.

require_once __DIR__ . '/../../frontend/includes/session.php';
require_once __DIR__ . '/../config.php';
function requireLogin(): void {
    global $estConnecte;
    if (!$estConnecte) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }
        // Sauvegarder la page demandee avant redirect
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php?error=session_expired');
        exit;
    }
}
function requireAdmin(): void {
    global $estConnecte;
    if (!$estConnecte || !($_SESSION['is_admin'] ?? false)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }
        header('Location: /erreur.php?code=403');
        exit;
    }
}