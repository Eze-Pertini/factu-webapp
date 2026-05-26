<?php
// ================================================
// api/configuracion.php — Datos fiscales del usuario
// Factu
// ================================================
// GET  → devuelve la configuración del usuario logueado
// POST → guarda/actualiza la configuración
// ================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$metodo    = $_SERVER['REQUEST_METHOD'];

match($metodo) {
    'GET'  => handleGet($usuarioId),
    'POST' => handlePost($usuarioId),
    default => responderError(405, 'Método no permitido.')
};


// ── GET — Obtener configuración ──

function handleGet(int $usuarioId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare(
            "SELECT * FROM configuracion WHERE usuario_id = :uid LIMIT 1"
        );
        $stmt->execute([':uid' => $usuarioId]);
        $config = $stmt->fetch();

        if (!$config) {
            // Si no existe, devolver valores vacíos
            $config = [
                'cuit'             => '',
                'razon_social'     => '',
                'punto_venta'      => '0001',
                'email_contacto'   => '',
                'condicion_fiscal' => 'Monotributista',
                'categoria_mono'   => '',
                'mp_access_token'  => '',
                'mp_ambiente'      => 'sandbox',
            ];
        }

        // No devolver el token completo por seguridad — solo si existe
        $config['mp_token_guardado'] = !empty($config['mp_access_token']);
        unset($config['mp_access_token']); // nunca exponer el token en GET

        echo json_encode(['ok' => true, 'datos' => $config]);

    } catch (PDOException $e) {
        responderError(500, 'Error al obtener la configuración.');
    }
}


// ── POST — Guardar configuración ──

function handlePost(int $usuarioId): void {
    $db = getDB();

    $body  = file_get_contents('php://input');
    $datos = json_decode($body, true);

    if (!$datos) {
        responderError(400, 'Body inválido.');
        return;
    }

    // Determinar qué sección se está guardando
    $seccion = $datos['seccion'] ?? 'fiscal';

    if ($seccion === 'fiscal') {
        guardarFiscal($db, $usuarioId, $datos);
    } elseif ($seccion === 'mercadopago') {
        guardarMercadoPago($db, $usuarioId, $datos);
    } else {
        responderError(400, 'Sección inválida.');
    }
}


// ── Guardar datos fiscales ──

function guardarFiscal(PDO $db, int $usuarioId, array $datos): void {
    $cuit            = trim($datos['cuit']             ?? '');
    $razonSocial     = trim($datos['razon_social']     ?? '');
    $puntoVenta      = trim($datos['punto_venta']      ?? '0001');
    $emailContacto   = trim($datos['email_contacto']   ?? '');
    $condicionFiscal = trim($datos['condicion_fiscal'] ?? 'Monotributista');
    $categoriaMono   = trim($datos['categoria_mono']   ?? '');

    if (empty($cuit) || empty($razonSocial)) {
        responderError(400, 'CUIT y razón social son obligatorios.');
        return;
    }

    // Validar formato CUIT básico
    if (!preg_match('/^\d{2}-\d{8}-\d{1}$/', $cuit)) {
        responderError(400, 'El CUIT debe tener el formato 20-12345678-9.');
        return;
    }

    // Validar punto de venta (1-4 dígitos)
    if (!preg_match('/^\d{1,4}$/', $puntoVenta)) {
        responderError(400, 'El punto de venta debe ser numérico (ej: 0001).');
        return;
    }

    // Formatear punto de venta con ceros a la izquierda
    $puntoVenta = str_pad($puntoVenta, 4, '0', STR_PAD_LEFT);

    try {
        // INSERT ... ON DUPLICATE KEY UPDATE → upsert
        $stmt = $db->prepare("
            INSERT INTO configuracion
                (usuario_id, cuit, razon_social, punto_venta, email_contacto, condicion_fiscal, categoria_mono)
            VALUES
                (:uid, :cuit, :razon, :pv, :email, :condicion, :categoria)
            ON DUPLICATE KEY UPDATE
                cuit             = VALUES(cuit),
                razon_social     = VALUES(razon_social),
                punto_venta      = VALUES(punto_venta),
                email_contacto   = VALUES(email_contacto),
                condicion_fiscal = VALUES(condicion_fiscal),
                categoria_mono   = VALUES(categoria_mono)
        ");

        $stmt->execute([
            ':uid'      => $usuarioId,
            ':cuit'     => $cuit,
            ':razon'    => $razonSocial,
            ':pv'       => $puntoVenta,
            ':email'    => $emailContacto,
            ':condicion'=> $condicionFiscal,
            ':categoria'=> $categoriaMono,
        ]);

        // Actualizar también en la sesión para uso inmediato
        $_SESSION['usuario_pv']   = $puntoVenta;
        $_SESSION['usuario_cuit'] = $cuit;

        echo json_encode([
            'ok'          => true,
            'mensaje'     => 'Datos fiscales guardados correctamente.',
            'punto_venta' => $puntoVenta,
        ]);

    } catch (PDOException $e) {
        responderError(500, 'Error al guardar los datos fiscales.');
    }
}


// ── Guardar Mercado Pago token ──

function guardarMercadoPago(PDO $db, int $usuarioId, array $datos): void {
    $token   = trim($datos['mp_access_token'] ?? '');
    $ambiente= trim($datos['mp_ambiente']     ?? 'sandbox');

    if (empty($token)) {
        responderError(400, 'El Access Token es obligatorio.');
        return;
    }

    $ambientesValidos = ['sandbox', 'produccion'];
    if (!in_array($ambiente, $ambientesValidos)) {
        responderError(400, 'Ambiente inválido.');
        return;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO configuracion (usuario_id, mp_access_token, mp_ambiente)
            VALUES (:uid, :token, :ambiente)
            ON DUPLICATE KEY UPDATE
                mp_access_token = VALUES(mp_access_token),
                mp_ambiente     = VALUES(mp_ambiente)
        ");

        $stmt->execute([
            ':uid'     => $usuarioId,
            ':token'   => $token,
            ':ambiente'=> $ambiente,
        ]);

        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Token de Mercado Pago guardado correctamente.',
        ]);

    } catch (PDOException $e) {
        responderError(500, 'Error al guardar el token.');
    }
}


// ── Helper ──

function responderError(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
    exit;
}
