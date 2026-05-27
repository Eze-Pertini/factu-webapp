<?php
// ================================================
// api/arca.php — Integración con ARCA/AFIP
// Factu — Facturación Electrónica
// ================================================
// GET  → consulta el último número de comprobante
// POST → emite un CAE para una factura existente
// ================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/arca/Wsaa.php';
require_once __DIR__ . '/../includes/arca/Wsfe.php';

// Verificar sesión
if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit;
}

$usuarioId  = $_SESSION['usuario_id'];
$metodo     = $_SERVER['REQUEST_METHOD'];
$produccion = true; // true = producción, false = homologación

// ── Rutas de certificados ──
$certsDir = realpath(__DIR__ . '/../certs') . DIRECTORY_SEPARATOR;
$certFile = $certsDir . 'factu.crt';
$keyFile  = $certsDir . 'factu.key';

if (!file_exists($certFile) || !file_exists($keyFile)) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Certificados no encontrados en /certs/'
    ]);
    exit;
}

// ── Obtener datos fiscales ──
try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT cuit, punto_venta FROM configuracion WHERE usuario_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $usuarioId]);
    $config = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener configuración fiscal.']);
    exit;
}

if (!$config || empty($config['cuit'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'CUIT no configurado en Configuración.']);
    exit;
}

$cuit       = (int)preg_replace('/[^0-9]/', '', $config['cuit']);
$puntoVenta = (int)$config['punto_venta'];

// ── Autenticar con WSAA ──
try {
    $wsaa   = new Wsaa($certFile, $keyFile, $certsDir, $produccion);
    $ticket = $wsaa->obtenerTicket();
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'Error autenticando con ARCA: ' . $e->getMessage()]);
    exit;
}

// ── Inicializar WSFE ──
try {
    $wsfe = new Wsfe($ticket['token'], $ticket['sign'], $cuit, $produccion);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'Error conectando con WSFE: ' . $e->getMessage()]);
    exit;
}

match($metodo) {
    'GET'  => handleGet($wsfe, $puntoVenta),
    'POST' => handlePost($wsfe, $puntoVenta, $db, $usuarioId),
    default => responderError(405, 'Método no permitido.')
};


// ── GET: último comprobante ──

function handleGet(Wsfe $wsfe, int $puntoVenta): void {
    try {
        $ultimo  = $wsfe->ultimoComprobante($puntoVenta, 11); // 11 = Factura C

        echo json_encode([
            'ok'     => true,
            'ultimo' => $ultimo,
            'proximo'=> $ultimo + 1,
        ]);
    } catch (Exception $e) {
        responderError(500, 'Error consultando ARCA: ' . $e->getMessage());
    }
}


// ── POST: emitir CAE ──

function handlePost(Wsfe $wsfe, int $puntoVenta, PDO $db, int $usuarioId): void {
    $body  = file_get_contents('php://input');
    $datos = json_decode($body, true);

    if (empty($datos['factura_id'])) {
        responderError(400, 'El campo factura_id es obligatorio.');
        return;
    }

    $facturaId = (int)$datos['factura_id'];

    // Obtener factura
    try {
        $stmt = $db->prepare("
            SELECT * FROM facturas
            WHERE id = :id AND usuario_id = :uid
            LIMIT 1
        ");
        $stmt->execute([':id' => $facturaId, ':uid' => $usuarioId]);
        $factura = $stmt->fetch();
    } catch (PDOException $e) {
        responderError(500, 'Error al obtener la factura.');
        return;
    }

    if (!$factura) {
        responderError(404, 'Factura no encontrada.');
        return;
    }

    // Verificar que no tenga CAE ya
    try {
        $stmtCae = $db->prepare("SELECT cae FROM facturas WHERE id = :id");
        $stmtCae->execute([':id' => $facturaId]);
        $row = $stmtCae->fetch();
        if (!empty($row['cae'])) {
            responderError(409, 'Esta factura ya tiene CAE: ' . $row['cae']);
            return;
        }
    } catch (PDOException $e) {
        // columna cae puede no existir aún — continuar
    }

    // Mapear concepto
    $conceptoMap = [
        'Productos'             => 1,
        'Servicios'             => 2,
        'Productos y Servicios' => 3,
    ];
    $conceptoCodigo = $conceptoMap[$factura['concepto']] ?? 2;

    // Obtener próximo número
    try {
        $ultimo  = $wsfe->ultimoComprobante($puntoVenta, 11);
        $proximo = $ultimo + 1;
    } catch (Exception $e) {
        responderError(500, 'Error obteniendo último comprobante: ' . $e->getMessage());
        return;
    }

    // Preparar fechas
    $fechaCbte     = date('Ymd');
    $fechaServDesde= date('Ymd', strtotime($factura['fecha_servicio']));
    $fechaServHasta= date('Ymd', strtotime($factura['fecha_servicio']));
    $fechaVtoPago  = date('Ymd', strtotime($factura['fecha_cobro']));

    // Emitir
    try {
        $resultado = $wsfe->emitirComprobante([
            'punto_venta'      => $puntoVenta,
            'tipo_cbte'        => 11, // Factura C
            'concepto'         => $conceptoCodigo,
            'doc_tipo'         => 99, // Consumidor final
            'doc_nro'          => 0,
            'cbte_numero'      => $proximo,
            'fecha_cbte'       => $fechaCbte,
            'importe_total'    => (float)$factura['monto_total'],
            'fecha_serv_desde' => $fechaServDesde,
            'fecha_serv_hasta' => $fechaServHasta,
            'fecha_vto_pago'   => $fechaVtoPago,
        ]);
    } catch (Exception $e) {
        responderError(500, 'Error emitiendo CAE: ' . $e->getMessage());
        return;
    }

    $cae            = $resultado['cae'];
    $caeVencimiento = $resultado['cae_vencimiento'];
    $numero         = $resultado['numero'];

    // Guardar CAE en DB
    try {
        // Agregar columnas si no existen
        $db->exec("ALTER TABLE facturas
            ADD COLUMN IF NOT EXISTS cae              VARCHAR(20) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS cae_vencimiento  DATE        DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS numero_arca      INT         DEFAULT NULL
        ");

        $stmtUpd = $db->prepare("
            UPDATE facturas
            SET cae = :cae, cae_vencimiento = :vto, numero_arca = :num
            WHERE id = :id
        ");
        $stmtUpd->execute([
            ':cae' => $cae,
            ':vto' => date('Y-m-d', strtotime(
                substr($caeVencimiento, 0, 4) . '-' .
                substr($caeVencimiento, 4, 2) . '-' .
                substr($caeVencimiento, 6, 2)
            )),
            ':num' => $numero,
            ':id'  => $facturaId,
        ]);
    } catch (PDOException $e) {
        error_log('Error guardando CAE en DB: ' . $e->getMessage());
    }

    http_response_code(201);
    echo json_encode([
        'ok'              => true,
        'mensaje'         => "CAE emitido correctamente.",
        'cae'             => $cae,
        'cae_vencimiento' => $caeVencimiento,
        'numero'          => $numero,
        'factura_id'      => $facturaId,
    ]);
}


// ── Helper ──

function responderError(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
    exit;
}
