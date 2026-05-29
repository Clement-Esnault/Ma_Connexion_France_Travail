<?php
// Lecture et mise à jour des seuils de qualité réseau (FT_SEUILS) — réservé à l'admin.
// Les seuils (ping, download, upload) définissent les niveaux Confort / Fonctionnel / Insuffisant
// affichés sur la page de test, dans les logs et dans les statistiques.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/CacheService.php';
requireAdmin();

$messageOk = '';
$messageErreur   = '';

// ── Mise à jour des seuils ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $stmt = $pdo->prepare('UPDATE FT_SEUILS SET VALEUR_BONNE = ?, VALEUR_MAUVAISE = ? WHERE NOM_SEUIL = ?');
        foreach (['ping', 'download', 'upload'] as $seuil) {
            $stmt->execute([
                floatval($_POST["bonne_$seuil"]    ?? 0),
                floatval($_POST["mauvaise_$seuil"] ?? 0),
                $seuil,
            ]);
        }
        $messageOk = 'Seuils mis à jour avec succès.';
        // Invalider le cache — les verdicts et alertes dépendent des seuils
        (new CacheService())->flush();
    } catch (PDOException $e) {
        $messageErreur = 'Erreur : ' . $e->getMessage();
    }
}

// ── Chargement pour affichage dans le formulaire ──────────────────────

$seuils = [];
foreach ($pdo->query('SELECT NOM_SEUIL, VALEUR_BONNE, VALEUR_MAUVAISE FROM FT_SEUILS')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $seuils[$row['NOM_SEUIL']] = $row;
}