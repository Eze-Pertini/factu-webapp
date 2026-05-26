<?php
// ================================================
// api/facturas.php — API de facturas
// Factu
// ================================================
// GET  → lista de facturas del usuario logueado
// POST → genera una factura nueva desde pagos seleccionados
// ================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Verificar sesión
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


// ================================================
// GET — Listar facturas del usuario
// ================================================

function handleGet(int $usuarioId): void {
    $db = getDB();

    try {
        // Traer facturas con el total de pagos que incluye cada una
        $stmt = $db->prepare("
            SELECT
                f.id,
                f.numero,
                f.tipo,
                f.fecha_emision,
                f.producto,
                f.monto_total,
                f.estado,
                COUNT(fp.pago_id) AS cantidad_pagos
            FROM facturas f
            LEFT JOIN factura_pagos fp ON fp.factura_id = f.id
            WHERE f.usuario_id = :uid
            GROUP BY f.id
            ORDER BY f.fecha_emision DESC
        ");

        $stmt->execute([':uid' => $usuarioId]);
        $facturas = $stmt->fetchAll();

        // Métricas para el historial
        $totalEmitidas = count(array_filter($facturas, fn($f) => $f['estado'] === 'emitida'));
        $totalAnuladas = count(array_filter($facturas, fn($f) => $f['estado'] === 'anulada'));
        $totalMonto    = array_sum(
            array_column(
                array_filter($facturas, fn($f) => $f['estado'] === 'emitida'),
                'monto_total'
            )
        );

        echo json_encode([
            'ok'    => true,
            'datos' => $facturas,
            'meta'  => [
                'total'          => count($facturas),
                'total_emitidas' => $totalEmitidas,
                'total_anuladas' => $totalAnuladas,
                'total_monto'    => $totalMonto,
            ]
        ]);

    } catch (PDOException $e) {
        responderError(500, 'Error al obtener facturas.');
    }
}



// ================================================
// POST — Generar facturas
// ================================================
// Modo 1a — Desde pagos MP (objetos completos):
// { pagos_mp: [{id, email_cliente, monto, detalle, fecha, tipo},...],
//   tipo, concepto, producto?, fecha_servicio, fecha_cobro }
//
// Modo 1b — Desde pagos DB (IDs):
// { pago_ids, tipo, concepto, producto?, fecha_servicio, fecha_cobro }
//
// Modo 2 — Manual:
// { manual:true, email_cliente, ... }
// ================================================

function handlePost(int $usuarioId): void {
    $db = getDB();

    $body  = file_get_contents('php://input');
    $datos = json_decode($body, true);

    if (!$datos) {
        responderError(400, 'Body inválido. Enviá JSON.');
        return;
    }

    $esManual = !empty($datos['manual']) && $datos['manual'] === true;
    $tienePagosMP = !empty($datos['pagos_mp']) && is_array($datos['pagos_mp']);

    if ($esManual) {
        handlePostManual($db, $usuarioId, $datos);
    } elseif ($tienePagosMP) {
        handlePostDesdePagosMP($db, $usuarioId, $datos);
    } else {
        handlePostDesdePagos($db, $usuarioId, $datos);
    }
}


// ── Factura desde pagos MP ──

function handlePostDesdePagos(PDO $db, int $usuarioId, array $datos): void {

    $requeridos = ['pago_ids', 'concepto', 'fecha_servicio', 'fecha_cobro'];
    foreach ($requeridos as $campo) {
        if (empty($datos[$campo])) { responderError(400, "El campo '$campo' es obligatorio."); return; }
    }

    $pagoIds       = $datos['pago_ids'];
    $tipo          = trim($datos['tipo']          ?? 'Factura C');
    $concepto      = trim($datos['concepto']);
    $producto      = trim($datos['producto']      ?? '');
    $fechaServicio = trim($datos['fecha_servicio']);
    $fechaCobro    = trim($datos['fecha_cobro']);

    $conceptosValidos = ['Servicios', 'Productos', 'Productos y Servicios'];
    if (!in_array($concepto, $conceptosValidos)) { responderError(400, 'Concepto inválido.'); return; }

    if (!is_array($pagoIds) || count($pagoIds) === 0) { responderError(400, 'Seleccioná al menos un pago.'); return; }

    foreach ([$fechaServicio, $fechaCobro] as $fecha) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { responderError(400, 'Formato de fecha inválido.'); return; }
    }

    $pagoIds = array_values(array_filter(array_map('intval', $pagoIds), fn($id) => $id > 0));
    if (empty($pagoIds)) { responderError(400, 'IDs de pagos inválidos.'); return; }

    try {
        $placeholders     = implode(',', array_fill(0, count($pagoIds), '?'));
        $stmt             = $db->prepare("SELECT id, monto, email_cliente, detalle, estado FROM pagos WHERE id IN ($placeholders)");
        $stmt->execute($pagoIds);
        $pagosEncontrados = $stmt->fetchAll();

        if (count($pagosEncontrados) !== count($pagoIds)) { responderError(400, 'Uno o más pagos no existen.'); return; }

        foreach ($pagosEncontrados as $pago) {
            if ($pago['estado'] === 'facturado') {
                responderError(409, "El pago del cliente {$pago['email_cliente']} ya fue facturado."); return;
            }
        }

        $db->beginTransaction();
        $facturasGeneradas = generarFacturasIndividuales($db, $usuarioId, $pagosEncontrados, $tipo, $concepto, $producto, $fechaServicio, $fechaCobro);

        $stmtUpdate = $db->prepare("UPDATE pagos SET estado = 'facturado' WHERE id = :id");
        foreach ($pagosEncontrados as $pago) { $stmtUpdate->execute([':id' => $pago['id']]); }

        $db->commit();

        $cantidad = count($facturasGeneradas);
        http_response_code(201);
        echo json_encode(['ok' => true, 'mensaje' => "$cantidad comprobante(s) generado(s) correctamente.", 'facturas' => $facturasGeneradas]);

    } catch (PDOException $e) {
        $db->rollBack();
        responderError(500, 'Error al generar las facturas. Ningún dato fue modificado.');
    }
}


// ── Factura manual ──

function handlePostManual(PDO $db, int $usuarioId, array $datos): void {

    $requeridos = ['email_cliente', 'monto', 'concepto', 'fecha_servicio', 'fecha_cobro'];
    foreach ($requeridos as $campo) {
        if (empty($datos[$campo])) { responderError(400, "El campo '$campo' es obligatorio."); return; }
    }

    $email        = trim($datos['email_cliente']);
    $razonSocial  = trim($datos['razon_social'] ?? '');
    $dniCuit      = trim($datos['dni_cuit']     ?? '');
    $detalle      = trim($datos['detalle']      ?? '');
    $formaPago    = trim($datos['forma_pago']   ?? '');
    $monto        = (float)$datos['monto'];
    $tipo         = trim($datos['tipo']         ?? 'Factura C');
    $concepto     = trim($datos['concepto']);
    $producto     = trim($datos['producto']     ?? '');
    $fechaServ    = trim($datos['fecha_servicio']);
    $fechaCobro   = trim($datos['fecha_cobro']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { responderError(400, 'El email del cliente no es válido.'); return; }
    if ($monto <= 0) { responderError(400, 'El monto debe ser mayor a cero.'); return; }

    $conceptosValidos = ['Servicios', 'Productos', 'Productos y Servicios'];
    if (!in_array($concepto, $conceptosValidos)) { responderError(400, 'Concepto inválido.'); return; }

    foreach ([$fechaServ, $fechaCobro] as $fecha) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { responderError(400, 'Formato de fecha inválido.'); return; }
    }

    $nombreProducto = $producto !== '' ? $producto : ($detalle !== '' ? $detalle : $concepto);

    try {
        $db->beginTransaction();

        $stmtCount = $db->prepare("SELECT COUNT(*) FROM facturas WHERE usuario_id = :uid");
        $stmtCount->execute([':uid' => $usuarioId]);
        $correlativo = (int)$stmtCount->fetchColumn() + 1;
        $numero      = '0001-' . str_pad($correlativo, 8, '0', STR_PAD_LEFT);

        $stmtF = $db->prepare("
            INSERT INTO facturas (numero, tipo, fecha_emision, fecha_servicio, fecha_cobro, concepto, producto, monto_total, usuario_id)
            VALUES (:numero, :tipo, NOW(), :fecha_servicio, :fecha_cobro, :concepto, :producto, :monto, :uid)
        ");

        $stmtF->execute([
            ':numero' => $numero, ':tipo' => $tipo,
            ':fecha_servicio' => $fechaServ, ':fecha_cobro' => $fechaCobro,
            ':concepto' => $concepto, ':producto' => $nombreProducto,
            ':monto' => $monto, ':uid' => $usuarioId,
        ]);

        $facturaId = (int)$db->lastInsertId();
        $db->commit();

        http_response_code(201);
        echo json_encode([
            'ok' => true,
            'mensaje' => "Comprobante $numero generado correctamente.",
            'facturas' => [['id' => $facturaId, 'numero' => $numero, 'email_cliente' => $email, 'monto' => $monto]]
        ]);

    } catch (PDOException $e) {
        $db->rollBack();
        responderError(500, 'Error al generar la factura.');
    }
}


// ── Función compartida ──

function generarFacturasIndividuales(PDO $db, int $usuarioId, array $pagos, string $tipo, string $concepto, string $producto, string $fechaServicio, string $fechaCobro): array {

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM facturas WHERE usuario_id = :uid");
    $stmtCount->execute([':uid' => $usuarioId]);
    $correlativos = (int)$stmtCount->fetchColumn();

    $stmtF = $db->prepare("
        INSERT INTO facturas (numero, tipo, fecha_emision, fecha_servicio, fecha_cobro, concepto, producto, monto_total, usuario_id)
        VALUES (:numero, :tipo, NOW(), :fecha_servicio, :fecha_cobro, :concepto, :producto, :monto, :uid)
    ");

    $stmtPivot = $db->prepare("INSERT INTO factura_pagos (factura_id, pago_id) VALUES (:fid, :pid)");
    $generadas = [];

    foreach ($pagos as $pago) {
        $correlativos++;
        $numero         = '0001-' . str_pad($correlativos, 8, '0', STR_PAD_LEFT);
        $nombreProducto = $producto !== '' ? $producto : $pago['detalle'];

        $stmtF->execute([
            ':numero' => $numero, ':tipo' => $tipo,
            ':fecha_servicio' => $fechaServicio, ':fecha_cobro' => $fechaCobro,
            ':concepto' => $concepto, ':producto' => $nombreProducto,
            ':monto' => $pago['monto'], ':uid' => $usuarioId,
        ]);

        $facturaId = (int)$db->lastInsertId();
        if ($pago['id'] !== null) { $stmtPivot->execute([':fid' => $facturaId, ':pid' => $pago['id']]); }

        $generadas[] = ['id' => $facturaId, 'numero' => $numero, 'email_cliente' => $pago['email_cliente'], 'monto' => $pago['monto']];
    }

    return $generadas;
}


// ── Factura desde objetos de pago MP (sin ID en DB) ──

function handlePostDesdePagosMP(PDO $db, int $usuarioId, array $datos): void {
    $pagosMP       = $datos['pagos_mp'];
    $tipo          = trim($datos['tipo']          ?? 'Factura C');
    $concepto      = trim($datos['concepto']      ?? '');
    $producto      = trim($datos['producto']      ?? '');
    $fechaServicio = trim($datos['fecha_servicio'] ?? '');
    $fechaCobro    = trim($datos['fecha_cobro']    ?? '');

    if (empty($concepto)) { responderError(400, 'El concepto es obligatorio.'); return; }
    if (empty($fechaServicio) || empty($fechaCobro)) { responderError(400, 'Las fechas son obligatorias.'); return; }

    $conceptosValidos = ['Servicios', 'Productos', 'Productos y Servicios'];
    if (!in_array($concepto, $conceptosValidos)) { responderError(400, 'Concepto inválido.'); return; }

    try {
        $db->beginTransaction();

        // 1. Insertar cada pago de MP en la tabla pagos (si no existe)
        $stmtCheck = $db->prepare("SELECT id FROM pagos WHERE mp_id = :mp_id LIMIT 1");
        $stmtInsert = $db->prepare("
            INSERT INTO pagos (mp_id, fecha, email_cliente, detalle, tipo, monto, estado)
            VALUES (:mp_id, :fecha, :email, :detalle, :tipo, :monto, 'facturado')
        ");

        $pagosParaFacturar = [];

        foreach ($pagosMP as $pago) {
            $mpId  = (string)($pago['mp_id'] ?? $pago['id'] ?? '');
            $monto = (float)($pago['monto'] ?? 0);
            $email = trim($pago['email_cliente'] ?? '');
            $det   = trim($pago['detalle'] ?? $concepto);
            $tipoPago = trim($pago['tipo'] ?? 'Pago recibido');
            $fecha = trim($pago['fecha'] ?? date('Y-m-d H:i:s'));

            if ($monto <= 0) continue;

            // Verificar si ya existe en DB
            $stmtCheck->execute([':mp_id' => $mpId]);
            $existente = $stmtCheck->fetch();

            if ($existente) {
                $pagoId = (int)$existente['id'];
            } else {
                // Insertar pago nuevo ya como facturado
                $stmtInsert->execute([
                    ':mp_id'   => $mpId,
                    ':fecha'   => $fecha,
                    ':email'   => $email ?: 'Sin datos',
                    ':detalle' => $det,
                    ':tipo'    => $tipoPago,
                    ':monto'   => $monto,
                ]);
                $pagoId = (int)$db->lastInsertId();
            }

            $pagosParaFacturar[] = [
                'id'            => $pagoId,
                'monto'         => $monto,
                'email_cliente' => $email ?: 'Sin datos',
                'detalle'       => $det,
            ];
        }

        if (empty($pagosParaFacturar)) {
            $db->rollBack();
            responderError(400, 'No hay pagos válidos para facturar.');
            return;
        }

        // 2. Generar facturas individuales
        $facturasGeneradas = generarFacturasIndividuales(
            $db, $usuarioId, $pagosParaFacturar,
            $tipo, $concepto, $producto, $fechaServicio, $fechaCobro
        );

        // 3. Marcar pagos existentes como facturados
        $stmtUpdate = $db->prepare("UPDATE pagos SET estado = 'facturado' WHERE id = :id");
        foreach ($pagosParaFacturar as $p) {
            $stmtUpdate->execute([':id' => $p['id']]);
        }

        $db->commit();

        $cantidad = count($facturasGeneradas);
        http_response_code(201);
        echo json_encode([
            'ok'       => true,
            'mensaje'  => "$cantidad comprobante(s) generado(s) correctamente.",
            'facturas' => $facturasGeneradas,
        ]);

    } catch (PDOException $e) {
        $db->rollBack();
        responderError(500, 'Error al generar las facturas. Ningún dato fue modificado.');
    }
}


// ── Helper ──

function responderError(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
    exit;
}
