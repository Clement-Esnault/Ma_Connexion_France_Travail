<?php
/**
 * Bootstrap.php — PHPUnit pour Ma Connexion
 * Lancé depuis la racine : C:\xampp\htdocs\speedtest
 *
 * Les fichiers de test font require_once __DIR__ . '/../backend/...'
 * __DIR__ = tests/php  →  /../backend = tests/backend
 * Ce bootstrap crée la junction tests/backend → backend/ (pas de droits admin requis)
 */

$lien  = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'backend';
$cible = realpath(__DIR__ . '/../../backend');

if (!file_exists($lien) && $cible) {
    shell_exec('cmd /c mklink /J "' . $lien . '" "' . $cible . '" 2>&1');
}