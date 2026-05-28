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
            $config = [
                'cuit'              => '',
                'razon_social'      => '',
                'punto_venta'       => '0001',
                'email_contacto'    => '',
                'condicion_fiscal'  => 'Monotributista',
                'categoria_mono'    => '',
                'domicilio'         => '',
                'piso_dpto'         => '',
                'ciudad'            => '',
                'provincia'         => '',
                'codigo_postal'     => '',
                'telefono'          => '',
                'iibb'              => '',
                'inicio_actividades'=> '',
                'mp_access_token'   => '',
                'mp_ambiente'       => 'sandbox',
            ];
        }

        $config['mp_token_guardado'] = !empty($config['mp_access_token']);
        unset($config['mp_access_token']);

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
    $cuit            = trim($datos['cuit']               ?? '');
    $razonSocial     = trim($datos['razon_social']        ?? '');
    $puntoVenta      = trim($datos['punto_venta']         ?? '0001');
    $emailContacto   = trim($datos['email_contacto']      ?? '');
    $condicionFiscal = trim($datos['condicion_fiscal']    ?? 'Monotributista');
    $categoriaMono   = trim($datos['categoria_mono']      ?? '');
    $domicilio       = trim($datos['domicilio']           ?? '');
    $pisoDpto        = trim($datos['piso_dpto']           ?? '');
    $ciudad          = trim($datos['ciudad']              ?? '');
    $provincia       = trim($datos['provincia']           ?? '');
    $codigoPostal    = trim($datos['codigo_postal']       ?? '');
    $telefono        = trim($datos['telefono']            ?? '');
    $iibb            = trim($datos['iibb']                ?? '');
    $inicioAct       = trim($datos['inicio_actividades']  ?? '');

    if (empty($cuit) || empty($razonSocial)) {
        responderError(400, 'CUIT y razón social son obligatorios.');
        return;
    }

    if (!preg_match('/^\d{2}-\d{8}-\d{1}$/', $cuit)) {
        responderError(400, 'El CUIT debe tener el formato 20-12345678-9.');
        return;
    }

    if (!preg_match('/^\d{1,4}$/', $puntoVenta)) {
        responderError(400, 'El punto de venta debe ser numérico (ej: 0001).');
        return;
    }

    $puntoVenta = str_pad($puntoVenta, 4, '0', STR_PAD_LEFT);

    // Validar fecha inicio actividades si viene
    $inicioActDb = null;
    if ($inicioAct) {
        // Acepta DD/MM/YYYY o YYYY-MM-DD
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $inicioAct, $m)) {
            $inicioActDb = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicioAct)) {
            $inicioActDb = $inicioAct;
        }
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO configuracion
                (usuario_id, cuit, razon_social, punto_venta, email_contacto,
                 condicion_fiscal, categoria_mono, domicilio, piso_dpto,
                 ciudad, provincia, codigo_postal, telefono, iibb, inicio_actividades)
            VALUES
                (:uid, :cuit, :razon, :pv, :email,
                 :condicion, :categoria, :domicilio, :piso_dpto,
                 :ciudad, :provincia, :cp, :telefono, :iibb, :inicio_act)
            ON DUPLICATE KEY UPDATE
                cuit              = VALUES(cuit),
                razon_social      = VALUES(razon_social),
                punto_venta       = VALUES(punto_venta),
                email_contacto    = VALUES(email_contacto),
                condicion_fiscal  = VALUES(condicion_fiscal),
                categoria_mono    = VALUES(categoria_mono),
                domicilio         = VALUES(domicilio),
                piso_dpto         = VALUES(piso_dpto),
                ciudad            = VALUES(ciudad),
                provincia         = VALUES(provincia),
                codigo_postal     = VALUES(codigo_postal),
                telefono          = VALUES(telefono),
                iibb              = VALUES(iibb),
                inicio_actividades= VALUES(inicio_actividades)
        ");

        $stmt->execute([
            ':uid'         => $usuarioId,
            ':cuit'        => $cuit,
            ':razon'       => $razonSocial,
            ':pv'          => $puntoVenta,
            ':email'       => $emailContacto,
            ':condicion'   => $condicionFiscal,
            ':categoria'   => $categoriaMono,
            ':domicilio'   => $domicilio   ?: null,
            ':piso_dpto'   => $pisoDpto    ?: null,
            ':ciudad'      => $ciudad      ?: null,
            ':provincia'   => $provincia   ?: null,
            ':cp'          => $codigoPostal?: null,
            ':telefono'    => $telefono    ?: null,
            ':iibb'        => $iibb        ?: null,
            ':inicio_act'  => $inicioActDb,
        ]);

        $_SESSION['usuario_pv']   = $puntoVenta;
        $_SESSION['usuario_cuit'] = $cuit;

        echo json_encode([
            'ok'          => true,
            'mensaje'     => 'Datos fiscales guardados correctamente.',
            'punto_venta' => $puntoVenta,
        ]);

    } catch (PDOException $e) {
        error_log('[guardarFiscal] ' . $e->getMessage());
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
        error_log('[guardarMercadoPago] ' . $e->getMessage());
        responderError(500, 'Error al guardar el token.');
    }
}


// ── Helper ──

function responderError(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
    exit;
}
