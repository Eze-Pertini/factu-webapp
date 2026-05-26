<?php
// ================================================
// api/login.php — Endpoint de autenticación
// Factu
// ================================================
// Recibe: POST { email, password }
// Devuelve: JSON { ok, mensaje, [usuario] }
// ================================================

// ── 1. Headers ─────────────────────────────────
// Le decimos al navegador que vamos a devolver JSON
header('Content-Type: application/json; charset=utf-8');

// Permitir solo requests desde el mismo dominio (seguridad básica)
header('X-Content-Type-Options: nosniff');

// ── 2. Solo aceptar POST ───────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

// ── 3. Cargar dependencias ─────────────────────
// __DIR__ es la carpeta donde está este archivo (/api)
// Subimos un nivel con .. para llegar a /includes
require_once __DIR__ . '/../includes/auth.php';

// ── 4. Leer datos del POST ─────────────────────
// El formulario puede enviar los datos de dos maneras:
// a) Como form data clásico (application/x-www-form-urlencoded)
// b) Como JSON (application/json) — cuando usás fetch() con JSON.stringify

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'application/json')) {
    // Caso b: el frontend envía JSON
    $body     = file_get_contents('php://input');
    $datos    = json_decode($body, true);
    $email    = trim($datos['email']    ?? '');
    $password = trim($datos['password'] ?? '');
} else {
    // Caso a: form data clásico
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
}

// ── 5. Llamar a la función login() de auth.php ─
$resultado = login($email, $password);

// ── 6. Responder con el código HTTP apropiado ──
if ($resultado['ok']) {
    http_response_code(200);
} else {
    http_response_code(401); // Unauthorized
}

echo json_encode($resultado);
exit;
