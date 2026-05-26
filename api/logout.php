<?php
// ================================================
// api/logout.php — Cerrar sesión
// Factu
// ================================================

require_once __DIR__ . '/../includes/auth.php';

logout();

header('Location: ' . BASE_URL . '/index.html');
exit;
