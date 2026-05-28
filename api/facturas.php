<?php
// ================================================
// api/facturas.php — API de facturas
// Factu
// ================================================
// GET             → lista de facturas del usuario logueado
// GET ?action=pdf → genera y descarga el PDF del comprobante
// POST            → genera facturas y emite CAE en ARCA
// ================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/arca/ArcaService.php';
require_once __DIR__ . '/../includes/arca/fpdf.php';
require_once __DIR__ . '/../includes/arca/ComprobantePDF.php';

if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$metodo    = $_SERVER['REQUEST_METHOD'];
$action    = $_GET['action'] ?? '';

// Accion PDF: no devuelve JSON sino el binario del PDF
if ($metodo === 'GET' && $action === 'pdf') {
    handleGetPdf($usuarioId);
    exit;
}

match($metodo) {
    'GET'  => handleGet($usuarioId),
    'POST' => handlePost($usuarioId),
    default => responderError(405, 'Metodo no permitido.')
};


// ── GET PDF — Generar y descargar PDF del comprobante ──────────────

function handleGetPdf(int $usuarioId): void {
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'ID de factura invalido.']);
        return;
    }

    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, numero, tipo, fecha_emision, fecha_servicio, fecha_cobro,
               concepto, producto, monto_total, estado,
               cae, cae_vencimiento, numero_arca,
               cliente_nombre, cliente_email, cliente_doc_tipo, cliente_doc_nro,
               forma_pago
        FROM facturas
        WHERE id = :id AND usuario_id = :uid
        LIMIT 1
    ");
    $stmt->execute([':id' => $id, ':uid' => $usuarioId]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada.']);
        return;
    }

    $stmtCfg = $db->prepare("
        SELECT razon_social, cuit, punto_venta, condicion_fiscal,
               categoria_mono, inicio_actividades, email_contacto,
               domicilio, piso_dpto, ciudad, provincia, codigo_postal,
               telefono, iibb
        FROM configuracion
        WHERE usuario_id = :uid
        LIMIT 1
    ");
    $stmtCfg->execute([':uid' => $usuarioId]);
    $config = $stmtCfg->fetch(PDO::FETCH_ASSOC) ?: [];

    // Nombre del archivo
    $pto      = str_pad($config['punto_venta'] ?? '1', 4, '0', STR_PAD_LEFT);
    $numArca  = str_pad($factura['numero_arca'] ?? 0, 8, '0', STR_PAD_LEFT);
    $cuit     = preg_replace('/[^0-9]/', '', $config['cuit'] ?? '0');
    $filename = "{$cuit}_FC_{$pto}_{$numArca}_{$factura['cae']}.pdf";

    if (ob_get_level()) ob_end_clean();

    $pdf = new ComprobantePDF($factura, $config);
    $pdf->descargar($filename);
}


// ── GET — Listar facturas ──────────────────────────────────────────

function handleGet(int $usuarioId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare("
            SELECT
                f.id,
                f.numero,
                f.tipo,
                f.fecha_emision,
                f.producto,
                f.monto_total,
                f.estado,
                f.cae,
                f.cae_vencimiento,
                f.cliente_nombre,
                f.cliente_email,
                COUNT(fp.pago_id) AS cantidad_pagos
            FROM facturas f
            LEFT JOIN factura_pagos fp ON fp.factura_id = f.id
            WHERE f.usuario_id = :uid
            GROUP BY f.id
            ORDER BY f.fecha_emision DESC
        ");

        $stmt->execute([':uid' => $usuarioId]);
        $facturas = $stmt->fetchAll();

        $totalEmitidas = count(array_filter($facturas, fn($f) => $f['estado'] === 'emitida'));
        $totalAnuladas = count(array_filter($facturas, fn($f) => $f['estado'] === 'anulada'));
        $totalMonto    = array_sum(array_column(
            array_filter($facturas, fn($f) => $f['estado'] === 'emitida'),
            'monto_total'
        ));

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
        error_log('[handleGet] ' . $e->getMessage());
        responderError(500, 'Error al obtener facturas.');
    }
}


// ── POST ──────────────────────────────────────────────────────────

function handlePost(int $usuarioId): void {
    $db = getDB();

    $body  = file_get_contents('php://input');
    $datos = json_decode($body, true);

    if (!$datos) { responderError(400, 'Body invalido.'); return; }

    $esManual     = !empty($datos['manual']) && $datos['manual'] === true;
    $tienePagosMP = !empty($datos['pagos_mp']) && is_array($datos['pagos_mp']);

    if ($esManual) {
        handlePostManual($db, $usuarioId, $datos);
    } elseif ($tienePagosMP) {
        handlePostDesdePagosMP($db, $usuarioId, $datos);
    } else {
        handlePostDesdePagos($db, $usuarioId, $datos);
    }
}


// ── Emitir CAE para facturas generadas ───────────────────────────

function emitirCaes(PDO $db, int $usuarioId, array $facturasGeneradas): array {
    $arca    = new ArcaService($db, $usuarioId);
    $results = [];

    foreach ($facturasGeneradas as $f) {
        $stmt = $db->prepare("SELECT * FROM facturas WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $f['id']]);
        $factura = $stmt->fetch();

        if (!$factura) {
            $results[] = array_merge($f, ['cae' => null, 'cae_error' => 'Factura no encontrada.']);
            continue;
        }

        if (!$arca->estaListo()) {
            $results[] = array_merge($f, ['cae' => null, 'cae_error' => $arca->getError()]);
            continue;
        }

        $caeResult = $arca->emitirCae($factura);

        if ($caeResult['ok']) {
            try {
                $vtoStr  = $caeResult['cae_vencimiento'];
                $vtoDate = strlen($vtoStr) === 8
                    ? substr($vtoStr, 0, 4) . '-' . substr($vtoStr, 4, 2) . '-' . substr($vtoStr, 6, 2)
                    : date('Y-m-d', strtotime($vtoStr));

                $db->prepare("
                    UPDATE facturas SET cae = :cae, cae_vencimiento = :vto, numero_arca = :num
                    WHERE id = :id
                ")->execute([
                    ':cae' => $caeResult['cae'],
                    ':vto' => $vtoDate,
                    ':num' => $caeResult['numero_arca'],
                    ':id'  => $f['id'],
                ]);

            } catch (PDOException $e) {
                error_log('[emitirCaes UPDATE] ' . $e->getMessage());
            }

            $results[] = array_merge($f, [
                'cae'             => $caeResult['cae'],
                'cae_vencimiento' => $vtoDate ?? null,
                'numero_arca'     => $caeResult['numero_arca'],
            ]);
        } else {
            $results[] = array_merge($f, [
                'cae'       => null,
                'cae_error' => $caeResult['error'],
            ]);
        }
    }

    return $results;
}


// ── Factura desde pagos internos (tabla pagos) ────────────────────

function handlePostDesdePagos(PDO $db, int $usuarioId, array $datos): void {
    $requeridos = ['pago_ids', 'concepto', 'fecha_servicio', 'fecha_cobro'];
    foreach ($requeridos as $campo) {
        if (empty($datos[$campo])) { responderError(400, "El campo '$campo' es obligatorio."); return; }
    }

    $pagoIds       = $datos['pago_ids'];
    $tipo          = trim($datos['tipo']     ?? 'Factura C');
    $concepto      = trim($datos['concepto']);
    $producto      = trim($datos['producto'] ?? '');
    $fechaServicio = trim($datos['fecha_servicio']);
    $fechaCobro    = trim($datos['fecha_cobro']);

    $conceptosValidos = ['Servicios', 'Productos', 'Productos y Servicios'];
    if (!in_array($concepto, $conceptosValidos)) { responderError(400, 'Concepto invalido.'); return; }
    if (!is_array($pagoIds) || count($pagoIds) === 0) { responderError(400, 'Selecciona al menos un pago.'); return; }

    foreach ([$fechaServicio, $fechaCobro] as $fecha) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { responderError(400, 'Formato de fecha invalido.'); return; }
    }

    $pagoIds = array_values(array_filter(array_map('intval', $pagoIds), fn($id) => $id > 0));

    try {
        $placeholders     = implode(',', array_fill(0, count($pagoIds), '?'));
        $stmt             = $db->prepare("SELECT id, monto, email_cliente, detalle, estado FROM pagos WHERE id IN ($placeholders)");
        $stmt->execute($pagoIds);
        $pagosEncontrados = $stmt->fetchAll();

        foreach ($pagosEncontrados as $pago) {
            if ($pago['estado'] === 'facturado') {
                responderError(409, "El pago de {$pago['email_cliente']} ya fue facturado."); return;
            }
        }

        $db->beginTransaction();
        $facturasGeneradas = generarFacturasIndividuales($db, $usuarioId, $pagosEncontrados, $tipo, $concepto, $producto, $fechaServicio, $fechaCobro);

        $stmtUpdate = $db->prepare("UPDATE pagos SET estado = 'facturado' WHERE id = :id");
        foreach ($pagosEncontrados as $pago) { $stmtUpdate->execute([':id' => $pago['id']]); }
        $db->commit();

        $facturasConCae = emitirCaes($db, $usuarioId, $facturasGeneradas);
        responderFacturas($facturasConCae);

    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[handlePostDesdePagos] ' . $e->getMessage());
        responderError(500, 'Error al generar las facturas.');
    }
}


// ── Factura desde objetos MP (importados en el momento) ───────────

function handlePostDesdePagosMP(PDO $db, int $usuarioId, array $datos): void {
    $pagosMP       = $datos['pagos_mp'];
    $tipo          = trim($datos['tipo']     ?? 'Factura C');
    $concepto      = trim($datos['concepto'] ?? '');
    $producto      = trim($datos['producto'] ?? '');
    $fechaServicio = trim($datos['fecha_servicio'] ?? '');
    $fechaCobro    = trim($datos['fecha_cobro']    ?? '');

    if (empty($concepto)) { responderError(400, 'El concepto es obligatorio.'); return; }
    if (empty($fechaServicio) || empty($fechaCobro)) { responderError(400, 'Las fechas son obligatorias.'); return; }

    $conceptosValidos = ['Servicios', 'Productos', 'Productos y Servicios'];
    if (!in_array($concepto, $conceptosValidos)) { responderError(400, 'Concepto invalido.'); return; }

    try {
        $db->beginTransaction();

        $stmtCheck  = $db->prepare("SELECT id FROM pagos WHERE mp_id = :mp_id LIMIT 1");
        $stmtInsert = $db->prepare("
            INSERT INTO pagos (mp_id, fecha, email_cliente, detalle, tipo, monto, estado)
            VALUES (:mp_id, :fecha, :email, :detalle, :tipo, :monto, 'facturado')
        ");

        $pagosParaFacturar = [];

        foreach ($pagosMP as $pago) {
            $mpId     = (string)($pago['mp_id'] ?? $pago['id'] ?? '');
            $monto    = (float)($pago['monto'] ?? 0);
            $email    = trim($pago['email_cliente'] ?? '');
            $det      = trim($pago['detalle'] ?? $concepto);
            $tipoPago = trim($pago['tipo'] ?? 'Pago recibido');
            $fecha    = trim($pago['fecha'] ?? date('Y-m-d H:i:s'));

            if ($monto <= 0) continue;

            $stmtCheck->execute([':mp_id' => $mpId]);
            $existente = $stmtCheck->fetch();

            if ($existente) {
                $pagoId = (int)$existente['id'];
            } else {
                $stmtInsert->execute([
                    ':mp_id' => $mpId, ':fecha' => $fecha,
                    ':email' => $email ?: 'Sin datos', ':detalle' => $det,
                    ':tipo'  => $tipoPago, ':monto' => $monto,
                ]);
                $pagoId = (int)$db->lastInsertId();
            }

            $pagosParaFacturar[] = [
                'id'              => $pagoId,
                'monto'           => $monto,
                'email_cliente'   => $email ?: 'Sin datos',
                'cliente_nombre'  => null,
                'cliente_doc_tipo'=> null,
                'cliente_doc_nro' => null,
                'detalle'         => $det,
                'forma_pago'      => $tipoPago, // "Transferencia Electronica", "Tarjeta de credito", etc.
            ];
        }

        if (empty($pagosParaFacturar)) {
            $db->rollBack();
            responderError(400, 'No hay pagos validos para facturar.');
            return;
        }

        $facturasGeneradas = generarFacturasIndividuales($db, $usuarioId, $pagosParaFacturar, $tipo, $concepto, $producto, $fechaServicio, $fechaCobro);

        $stmtUpdate = $db->prepare("UPDATE pagos SET estado = 'facturado' WHERE id = :id");
        foreach ($pagosParaFacturar as $p) { $stmtUpdate->execute([':id' => $p['id']]); }

        $db->commit();

        $facturasConCae = emitirCaes($db, $usuarioId, $facturasGeneradas);
        responderFacturas($facturasConCae);

    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[handlePostDesdePagosMP] ' . $e->getMessage());
        responderError(500, 'Error al generar las facturas.');
    }
}


// ── Factura manual ────────────────────────────────────────────────

function handlePostManual(PDO $db, int $usuarioId, array $datos): void {
    $requeridos = ['email_cliente', 'monto', 'concepto', 'fecha_servicio', 'fecha_cobro'];
    foreach ($requeridos as $campo) {
        if (empty($datos[$campo])) { responderError(400, "El campo '$campo' es obligatorio."); return; }
    }

    $email      = trim($datos['email_cliente']);
    $nombre     = trim($datos['nombre_cliente'] ?? '');
    $docTipo    = trim($datos['doc_tipo']   ?? '');
    $docNro     = trim($datos['doc_nro']    ?? '');
    $formaPago  = trim($datos['forma_pago'] ?? '');
    $monto      = (float)$datos['monto'];
    $tipo       = trim($datos['tipo']       ?? 'Factura C');
    $concepto   = trim($datos['concepto']);
    $producto   = trim($datos['producto']   ?? '');
    $detalle    = trim($datos['detalle']    ?? '');
    $fechaServ  = trim($datos['fecha_servicio']);
    $fechaCobro = trim($datos['fecha_cobro']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { responderError(400, 'Email invalido.'); return; }
    if ($monto <= 0) { responderError(400, 'El monto debe ser mayor a cero.'); return; }

    $conceptosValidos = ['Servicios', 'Productos', 'Productos y Servicios'];
    if (!in_array($concepto, $conceptosValidos)) { responderError(400, 'Concepto invalido.'); return; }

    $nombreProducto = $producto !== '' ? $producto : ($detalle !== '' ? $detalle : $concepto);

    try {
        $db->beginTransaction();

        // Numero correlativo usando MAX para evitar duplicados por COUNT
        $stmtMax = $db->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)) 
            FROM facturas WHERE usuario_id = :uid
        ");
        $stmtMax->execute([':uid' => $usuarioId]);
        $maxNum = (int)$stmtMax->fetchColumn();

        // Punto de venta del usuario
        $stmtPto = $db->prepare("SELECT punto_venta FROM configuracion WHERE usuario_id = :uid LIMIT 1");
        $stmtPto->execute([':uid' => $usuarioId]);
        $pto = str_pad((int)($stmtPto->fetchColumn() ?: 1), 4, '0', STR_PAD_LEFT);

        $numero = $pto . '-' . str_pad($maxNum + 1, 8, '0', STR_PAD_LEFT);

        $stmtF = $db->prepare("
            INSERT INTO facturas
                (numero, tipo, fecha_emision, fecha_servicio, fecha_cobro,
                 concepto, producto, monto_total, usuario_id,
                 cliente_nombre, cliente_email, cliente_doc_tipo, cliente_doc_nro,
                 forma_pago)
            VALUES
                (:numero, :tipo, NOW(), :fecha_servicio, :fecha_cobro,
                 :concepto, :producto, :monto, :uid,
                 :cli_nombre, :cli_email, :cli_doc_tipo, :cli_doc_nro,
                 :forma_pago)
        ");

        $stmtF->execute([
            ':numero'         => $numero,
            ':tipo'           => $tipo,
            ':fecha_servicio' => $fechaServ,
            ':fecha_cobro'    => $fechaCobro,
            ':concepto'       => $concepto,
            ':producto'       => $nombreProducto,
            ':monto'          => $monto,
            ':uid'            => $usuarioId,
            ':cli_nombre'     => $nombre    ?: null,
            ':cli_email'      => $email,
            ':cli_doc_tipo'   => $docTipo   ?: null,
            ':cli_doc_nro'    => $docNro    ?: null,
            ':forma_pago'     => $formaPago ?: null,
        ]);

        $facturaId = (int)$db->lastInsertId();
        $db->commit();

        $facturasConCae = emitirCaes($db, $usuarioId, [
            ['id' => $facturaId, 'numero' => $numero, 'email_cliente' => $email, 'monto' => $monto]
        ]);

        responderFacturas($facturasConCae);

    } catch (PDOException $e) {
        $db->rollBack();
        error_log('[handlePostManual] ' . $e->getMessage());
        responderError(500, 'Error al generar la factura.');
    }
}


// ── Generar facturas individuales en DB ───────────────────────────

function generarFacturasIndividuales(
    PDO    $db,
    int    $usuarioId,
    array  $pagos,
    string $tipo,
    string $concepto,
    string $producto,
    string $fechaServicio,
    string $fechaCobro
): array {

    // Numero correlativo usando MAX para evitar duplicados
    $stmtMax = $db->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED))
        FROM facturas WHERE usuario_id = :uid
    ");
    $stmtMax->execute([':uid' => $usuarioId]);
    $maxNum = (int)$stmtMax->fetchColumn();

    // Punto de venta del usuario
    $stmtPto = $db->prepare("SELECT punto_venta FROM configuracion WHERE usuario_id = :uid LIMIT 1");
    $stmtPto->execute([':uid' => $usuarioId]);
    $pto = str_pad((int)($stmtPto->fetchColumn() ?: 1), 4, '0', STR_PAD_LEFT);

    $stmtF = $db->prepare("
        INSERT INTO facturas
            (numero, tipo, fecha_emision, fecha_servicio, fecha_cobro,
             concepto, producto, monto_total, usuario_id,
             cliente_nombre, cliente_email, cliente_doc_tipo, cliente_doc_nro,
             forma_pago)
        VALUES
            (:numero, :tipo, NOW(), :fecha_servicio, :fecha_cobro,
             :concepto, :producto, :monto, :uid,
             :cli_nombre, :cli_email, :cli_doc_tipo, :cli_doc_nro,
             :forma_pago)
    ");

    $stmtPivot = $db->prepare("INSERT INTO factura_pagos (factura_id, pago_id) VALUES (:fid, :pid)");
    $generadas = [];

    foreach ($pagos as $pago) {
        $maxNum++;
        $numero         = $pto . '-' . str_pad($maxNum, 8, '0', STR_PAD_LEFT);
        $nombreProducto = $producto !== '' ? $producto : ($pago['detalle'] ?? $concepto);

        $stmtF->execute([
            ':numero'         => $numero,
            ':tipo'           => $tipo,
            ':fecha_servicio' => $fechaServicio,
            ':fecha_cobro'    => $fechaCobro,
            ':concepto'       => $concepto,
            ':producto'       => $nombreProducto,
            ':monto'          => $pago['monto'],
            ':uid'            => $usuarioId,
            ':cli_nombre'     => $pago['cliente_nombre']   ?? null,
            ':cli_email'      => $pago['email_cliente']    ?? null,
            ':cli_doc_tipo'   => $pago['cliente_doc_tipo'] ?? null,
            ':cli_doc_nro'    => $pago['cliente_doc_nro']  ?? null,
            ':forma_pago'     => $pago['forma_pago']       ?? null,
        ]);

        $facturaId = (int)$db->lastInsertId();
        if (!empty($pago['id'])) {
            $stmtPivot->execute([':fid' => $facturaId, ':pid' => $pago['id']]);
        }

        $generadas[] = [
            'id'           => $facturaId,
            'numero'       => $numero,
            'email_cliente'=> $pago['email_cliente'] ?? '',
            'monto'        => $pago['monto'],
        ];
    }

    return $generadas;
}


// ── Responder con facturas + estado CAE ───────────────────────────

function responderFacturas(array $facturas): void {
    $conCae   = array_filter($facturas, fn($f) => !empty($f['cae']));
    $sinCae   = array_filter($facturas, fn($f) => empty($f['cae']));
    $cantidad = count($facturas);

    $mensaje = count($conCae) === $cantidad
        ? "$cantidad comprobante(s) generado(s) con CAE."
        : count($conCae) . " de $cantidad con CAE. " . count($sinCae) . " sin CAE (ver errores).";

    http_response_code(201);
    echo json_encode([
        'ok'       => true,
        'mensaje'  => $mensaje,
        'facturas' => array_values($facturas),
        'con_cae'  => count($conCae),
        'sin_cae'  => count($sinCae),
    ]);
}


// ── Helper error ──────────────────────────────────────────────────

function responderError(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
    exit;
}
