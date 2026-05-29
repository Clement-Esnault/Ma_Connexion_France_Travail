<?php
// Gestion des comptes techniciens (CRUD) — réservé à l'administrateur.
// Actions POST : create, update (mot de passe), delete.
// Pattern Post/Redirect/Get : après chaque action réussie, redirige en GET
// pour éviter le "Confirmer le renvoi du formulaire" au rechargement.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$messageOk = '';
$messageErreur   = '';

// Récupère le message flash transmis via GET après redirection
if (!empty($_GET['ok'])) {
    $messageOk = match($_GET['ok']) {
        'created' => 'Technicien créé avec succès.',
        'updated' => 'Mot de passe mis à jour.',
        'deleted' => 'Technicien supprimé.',
        default   => '',
    };
}

// ── Créer un technicien ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf();
    $alias = trim($_POST['alias'] ?? '');
    $mdp   = trim($_POST['mdp']   ?? '');
    if (!$alias || !$mdp) {
        $messageErreur = 'Alias et mot de passe obligatoires.';
    } else {
        try {
            $pdo->prepare('INSERT INTO FT_COMPTES (ALIAS_COMPTE, MDP_COMPTE, IS_ADMIN) VALUES (?, ?, 0)')
                ->execute([$alias, password_hash($mdp, PASSWORD_BCRYPT)]);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=created');
            exit;
        } catch (PDOException $e) {
            $messageErreur = 'Alias déjà utilisé ou erreur : ' . $e->getMessage();
        }
    }
}

// ── Modifier le mot de passe ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verifyCsrf();
    $id  = intval($_POST['id']  ?? 0);
    $mdp = trim($_POST['mdp']   ?? '');
    if (!$id || !$mdp) {
        $messageErreur = 'Données invalides.';
    } else {
        // AND IS_ADMIN = 0 empêche de modifier le mot de passe admin via ce formulaire
        $pdo->prepare('UPDATE FT_COMPTES SET MDP_COMPTE = ? WHERE ID_COMPTE = ? AND IS_ADMIN = 0')
            ->execute([password_hash($mdp, PASSWORD_BCRYPT), $id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=updated');
        exit;
    }
}

// ── Supprimer un technicien ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        $messageErreur = 'ID invalide.';
    } else {
        // AND IS_ADMIN = 0 empêche la suppression accidentelle du compte admin
        $pdo->prepare('DELETE FROM FT_COMPTES WHERE ID_COMPTE = ? AND IS_ADMIN = 0')
            ->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=deleted');
        exit;
    }
}

// ── Liste des techniciens ─────────────────────────────────────────────
$techniciens = $pdo->query('
    SELECT ID_COMPTE, ALIAS_COMPTE FROM FT_COMPTES
    WHERE IS_ADMIN = 0 ORDER BY ALIAS_COMPTE
')->fetchAll(PDO::FETCH_ASSOC);