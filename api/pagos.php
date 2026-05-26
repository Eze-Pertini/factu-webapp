<?php
// ================================================
// api/pagos.php — API de pagos
// Factu
// ================================================
// GET  → devuelve lista de pagos (con filtros opcionales)
// POST → inserta un pago nuevo (para pruebas / futura integración MP)
// ================================================

// ── 1. Headers ─────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── 2. Dependencias ────────────────────────────
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// ── 3. Verificar sesión ────────────────────────
// Si no hay sesión activa → 401 y corta ejecución
if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado. Iniciá sesión.']);
    exit;
}

// ── 4. Rutear según método HTTP ────────────────
$metodo = $_SERVER['REQUEST_METHOD'];

match($metodo) {
    'GET'  => handleGet(),
    'POST' => handlePost(),
    default => responderError(405, 'Método no permitido.')
};


// ================================================
// GET — Listar pagos
// ================================================
// Parámetros opcionales en query string:
//   ?desde=2025-06-01&hasta=2025-06-30
//   ?estado=pendiente
// ================================================

function handleGet(): void {
    $db = getDB();

    // Filtros opcionales desde query string
    $desde  = $_GET['desde']  ?? null;
    $hasta  = $_GET['hasta']  ?? null;
    $estado = $_GET['estado'] ?? null;

    // Construir query dinámicamente con condiciones
    $sql        = "SELECT * FROM pagos WHERE 1=1";
    $parametros = [];

    if ($desde) {
        // Validar formato de fecha antes de usarla
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $sql         .= " AND fecha >= :desde";
            $parametros[':desde'] = $desde . ' 00:00:00';
        }
    }

    if ($hasta) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $sql         .= " AND fecha <= :hasta";
            $parametros[':hasta'] = $hasta . ' 23:59:59';
        }
    }

    if ($estado && in_array($estado, ['pendiente', 'facturado'])) {
        $sql         .= " AND estado = :estado";
        $parametros[':estado'] = $estado;
    }

    $sql .= " ORDER BY fecha DESC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($parametros);
        $pagos = $stmt->fetchAll(); // Array de arrays asociativos

        // Calcular totales para el frontend
        $totalMonto    = array_sum(array_column($pagos, 'monto'));
        $totalPendiente = array_sum(
            array_column(
                array_filter($pagos, fn($p) => $p['estado'] === 'pendiente'),
                'monto'
            )
        );

        echo json_encode([
            'ok'    => true,
            'datos' => $pagos,
            'meta'  => [
                'total'           => count($pagos),
                'total_monto'     => $totalMonto,
                'total_pendiente' => $totalPendiente,
            ]
        ]);

    } catch (PDOException $e) {
        responderError(500, 'Error al obtener pagos.');
    }
}


// ================================================
// POST — Insertar pago nuevo
// ================================================
// Body JSON esperado:
// {
//   "mp_id": "MP-12345",
//   "fecha": "2025-06-14 10:00:00",
//   "email_cliente": "cliente@email.com",
//   "detalle": "Descripción del pago",
//   "tipo": "Pago recibido",
//   "monto": 50000.00
// }
// ================================================

function handlePost(): void {
    $db = getDB();

    // Leer body JSON
    $body  = file_get_contents('php://input');
    $datos = json_decode($body, true);

    if (!$datos) {
        responderError(400, 'Body inválido. Enviá JSON.');
        return;
    }

    // Validar campos obligatorios
    $requeridos = ['mp_id', 'fecha', 'email_cliente', 'detalle', 'monto'];
    foreach ($requeridos as $campo) {
        if (empty($datos[$campo])) {
            responderError(400, "El campo '$campo' es obligatorio.");
            return;
        }
    }

    // Sanitizar y asignar valores con defaults
    $mp_id         = trim($datos['mp_id']);
    $fecha         = trim($datos['fecha']);
    $email_cliente = filter_var(trim($datos['email_cliente']), FILTER_SANITIZE_EMAIL);
    $detalle       = trim($datos['detalle']);
    $tipo          = trim($datos['tipo'] ?? 'Pago recibido');
    $monto         = (float) $datos['monto'];

    // Validaciones
    if (!filter_var($email_cliente, FILTER_VALIDATE_EMAIL)) {
        responderError(400, 'El email del cliente no es válido.');
        return;
    }

    if ($monto <= 0) {
        responderError(400, 'El monto debe ser mayor a cero.');
        return;
    }

    try {
        $stmt = $db->prepare(
            "INSERT INTO pagos (mp_id, fecha, email_cliente, detalle, tipo, monto, estado)
             VALUES (:mp_id, :fecha, :email_cliente, :detalle, :tipo, :monto, 'pendiente')"
        );

        $stmt->execute([
            ':mp_id'         => $mp_id,
            ':fecha'         => $fecha,
            ':email_cliente' => $email_cliente,
            ':detalle'       => $detalle,
            ':tipo'          => $tipo,
            ':monto'         => $monto,
        ]);

        http_response_code(201); // Created
        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Pago insertado correctamente.',
            'id'      => $db->lastInsertId()
        ]);

    } catch (PDOException $e) {
        // Error 23000 = clave duplicada (mp_id ya existe)
        if ($e->getCode() === '23000') {
            responderError(409, "El pago con ID '$mp_id' ya existe.");
        } else {
            responderError(500, 'Error al insertar pago.');
        }
    }
}


// ================================================
// Helper — Responder con error JSON
// ================================================

function responderError(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
    exit;
}
