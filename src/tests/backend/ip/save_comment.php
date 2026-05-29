<?php
// Enregistre un commentaire libre d'un agent après un test de débit.
// Pas d'authentification requise — accessible depuis la page publique index.php.
// La sécurité repose sur la fenêtre de 10 minutes : le commentaire est rattaché
// au dernier log de l'IP, ce qui empêche les soumissions sans test préalable.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$data    = json_decode(file_get_contents('php://input'), true);
$contenu = trim($data['contenu'] ?? '');

// Validation du contenu
if ($contenu === '') {
    echo json_encode(['success' => false, 'error' => 'Commentaire vide']); exit;
}
if (mb_strlen($contenu) > 250) {
    echo json_encode(['success' => false, 'error' => 'Commentaire trop long (250 caractères max)']); exit;
}
// Bloquer les caractères de contrôle (sauf \t, \n, \r)
if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $contenu)) {
    echo json_encode(['success' => false, 'error' => 'Caractères invalides']); exit;
}

require_once __DIR__ . '/getIP_util.php';
$ip = getClientIp();

// Recherche du dernier test de cette IP dans les 10 dernières minutes
$stmt = $pdo->prepare('
    SELECT ID_LOGS FROM FT_LOGS
    WHERE IP_CLIENT = ? AND DATE_LOGS > NOW() - INTERVAL 10 MINUTE
    ORDER BY DATE_LOGS DESC LIMIT 1
');
$stmt->execute([$ip]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Aucun test trouvé pour cette IP']); exit;
}

$stmt = $pdo->prepare('
    INSERT INTO FT_COMMENTAIRES (CONTENU_COMMENTAIRE, ID_LOGS, DATE_COMMENTAIRE)
    VALUES (?, ?, NOW())
');
$stmt->execute([mb_substr($contenu, 0, 250), $row['ID_LOGS']]);

echo json_encode(['success' => true]);