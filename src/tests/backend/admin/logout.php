<?php
// Déconnexion : détruit la session et redirige vers login.php

session_start();
session_destroy();
header('Location: /login.php?disconnected=1');
exit;
?>