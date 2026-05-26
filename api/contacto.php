<?php
// ================================================
// api/contacto.php — Endpoint público de contacto
// Factu
// ================================================
// POST → recibe mensaje de contacto y lo guarda en DB
// NO requiere sesión — es un endpoint público
// ================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

// db.php no requiere sesión — solo la conexión PDO
require_once __DIR__ . '/../includes/db.php';

// ── Leer body ──
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $body  = file_get_contents('php://input');
    $datos = json_decode($body, true) ?? [];
} else {
    $datos = $_POST;
}

// ── Leer y sanitizar campos ──
$nombre  = trim(htmlspecialchars($datos['nombre']  ?? '', ENT_QUOTES, 'UTF-8'));
$email   = trim($datos['email']   ?? '');
$asunto  = trim(htmlspecialchars($datos['asunto']  ?? '', ENT_QUOTES, 'UTF-8'));
$mensaje = trim(htmlspecialchars($datos['mensaje'] ?? '', ENT_QUOTES, 'UTF-8'));

// ── Validaciones backend ──
$errores = [];

if (empty($nombre)) {
    $errores[] = 'El nombre es obligatorio.';
} elseif (mb_strlen($nombre) > 100) {
    $errores[] = 'El nombre no puede superar los 100 caracteres.';
}

if (empty($email)) {
    $errores[] = 'El email es obligatorio.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El email no tiene un formato válido.';
} elseif (mb_strlen($email) > 150) {
    $errores[] = 'El email es demasiado largo.';
}

if (empty($mensaje)) {
    $errores[] = 'El mensaje es obligatorio.';
} elseif (mb_strlen($mensaje) < 10) {
    $errores[] = 'El mensaje debe tener al menos 10 caracteres.';
} elseif (mb_strlen($mensaje) > 2000) {
    $errores[] = 'El mensaje no puede superar los 2000 caracteres.';
}

if (!empty($errores)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => implode(' ', $errores)
    ]);
    exit;
}

// ── Rate limiting básico por IP ──
// Evita spam: máximo 3 mensajes por IP en los últimos 10 minutos
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', $ip)[0]); // tomar solo la primera IP si hay proxy

try {
    $db = getDB();

    $stmtCheck = $db->prepare("
        SELECT COUNT(*) FROM contactos
        WHERE ip = :ip AND fecha >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmtCheck->execute([':ip' => $ip]);
    $intentos = (int)$stmtCheck->fetchColumn();

    if ($intentos >= 3) {
        http_response_code(429);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Demasiados mensajes en poco tiempo. Esperá unos minutos.'
        ]);
        exit;
    }

    // ── Insertar en DB ──
    $stmt = $db->prepare("
        INSERT INTO contactos (nombre, email, asunto, mensaje, ip)
        VALUES (:nombre, :email, :asunto, :mensaje, :ip)
    ");

    $stmt->execute([
        ':nombre'  => $nombre,
        ':email'   => $email,
        ':asunto'  => $asunto ?: 'Sin asunto',
        ':mensaje' => $mensaje,
        ':ip'      => $ip,
    ]);

    http_response_code(201);
    echo json_encode([
        'status'  => 'ok',
        'message' => '¡Mensaje enviado! Te responderemos a la brevedad.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error interno. Intentá de nuevo más tarde.'
    ]);
}
