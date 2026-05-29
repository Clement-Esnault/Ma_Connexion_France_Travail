<?php
// Point d'entrée HTTP pour la file d'attente des tests de débit.
// Délègue toute la logique métier à QueueService.
// Actions GET : join (rejoindre), status (vérifier son tour), done (libérer).

header('Content-Type: application/json');

// Vérification de l'origine — rejette les requêtes cross-site
$hoteAutorise = $_SERVER['HTTP_HOST'] ?? '';
$origine      = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';

if ($origine !== '') {
    $origineAnalysee = parse_url($origine, PHP_URL_HOST);
    if ($origineAnalysee !== $hoteAutorise) {
        http_response_code(403);
        echo json_encode(['error' => 'Origine non autorisée']);
        exit;
    }
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/QueueService.php';

$service = new QueueService($pdo);
$action  = $_GET['action'] ?? '';

switch ($action) {

    // ── Rejoindre la file ─────────────────────────────────────────────
    case 'join':
        try {
            $result = $service->rejoindre();
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ── Vérifier son statut dans la file ─────────────────────────────
    case 'status':
        $jeton = $_GET['token'] ?? '';
        if ($jeton === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Token manquant']);
            break;
        }
        try {
            $result = $service->statut($jeton);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ── Libérer la file ───────────────────────────────────────────────
    case 'done':
        $jeton = $_GET['token'] ?? '';
        if ($jeton === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Token manquant']);
            break;
        }
        try {
            $result = $service->terminer($jeton);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // ── Action inconnue ───────────────────────────────────────────────
    default:
        http_response_code(400);
        echo json_encode(['error' => "Action '$action' inconnue. Actions valides : join, status, done"]);
        break;
}