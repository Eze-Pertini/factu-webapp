<?php
// ================================================
// api/dashboard.php — Métricas y datos del dashboard
// Factu
// ================================================
// GET → devuelve métricas, últimas operaciones y actividad reciente

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$db        = getDB();

try {

    // ── Fecha de hoy y del mes actual ──
    $hoy       = date('Y-m-d');
    $mesInicio = date('Y-m-01');
    $mesFin    = date('Y-m-t');

    // ── 1. Total facturado HOY ──
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(monto_total), 0) as total
        FROM facturas
        WHERE usuario_id = :uid
          AND estado = 'emitida'
          AND DATE(fecha_emision) = :hoy
    ");
    $stmt->execute([':uid' => $usuarioId, ':hoy' => $hoy]);
    $totalHoy = (float)$stmt->fetchColumn();

    // ── 2. Total facturado este MES ──
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(monto_total), 0) as total
        FROM facturas
        WHERE usuario_id = :uid
          AND estado = 'emitida'
          AND DATE(fecha_emision) BETWEEN :inicio AND :fin
    ");
    $stmt->execute([':uid' => $usuarioId, ':inicio' => $mesInicio, ':fin' => $mesFin]);
    $totalMes = (float)$stmt->fetchColumn();

    // ── 3. Cantidad de facturas emitidas este mes ──
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM facturas
        WHERE usuario_id = :uid
          AND estado = 'emitida'
          AND DATE(fecha_emision) BETWEEN :inicio AND :fin
    ");
    $stmt->execute([':uid' => $usuarioId, ':inicio' => $mesInicio, ':fin' => $mesFin]);
    $cantFacturas = (int)$stmt->fetchColumn();

    // ── 4. Pagos pendientes de facturar ──
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM pagos
        WHERE estado = 'pendiente'
    ");
    $stmt->execute();
    $pendientes = (int)$stmt->fetchColumn();

    // ── 5. Últimas 5 operaciones (pagos) ──
    $stmt = $db->prepare("
        SELECT id, fecha, email_cliente, monto, estado
        FROM pagos
        ORDER BY fecha DESC
        LIMIT 5
    ");
    $stmt->execute();
    $ultimasOps = $stmt->fetchAll();

    // ── 6. Actividad reciente (últimas 5 facturas generadas) ──
    $stmt = $db->prepare("
        SELECT
            f.numero,
            f.fecha_emision,
            f.monto_total,
            f.tipo,
            COUNT(fp.pago_id) as cantidad_pagos,
            -- Si es manual no tiene pagos asociados
            CASE WHEN COUNT(fp.pago_id) = 0 THEN 'manual' ELSE 'mp' END as origen
        FROM facturas f
        LEFT JOIN factura_pagos fp ON fp.factura_id = f.id
        WHERE f.usuario_id = :uid
        GROUP BY f.id
        ORDER BY f.fecha_emision DESC
        LIMIT 5
    ");
    $stmt->execute([':uid' => $usuarioId]);
    $actividad = $stmt->fetchAll();

    // ── 7. Alertas dinámicas ──
    $alertas = [];

    // Alerta: pagos pendientes
    if ($pendientes > 0) {
        $alertas[] = [
            'tipo'  => 'warning',
            'icono' => 'fa-clock',
            'texto' => "<strong>$pendientes pago(s) sin facturar</strong>",
            'tiempo'=> 'Pendiente'
        ];
    }

    // Alerta: última factura emitida
    $stmt = $db->prepare("
        SELECT numero, fecha_emision
        FROM facturas
        WHERE usuario_id = :uid AND estado = 'emitida'
        ORDER BY fecha_emision DESC
        LIMIT 1
    ");
    $stmt->execute([':uid' => $usuarioId]);
    $ultimaFactura = $stmt->fetch();

    if ($ultimaFactura) {
        $alertas[] = [
            'tipo'  => 'success',
            'icono' => 'fa-check',
            'texto' => "<strong>{$ultimaFactura['numero']}</strong> emitida correctamente",
            'tiempo'=> formatTiempo($ultimaFactura['fecha_emision'])
        ];
    }

    // Alerta informativa fija
    $alertas[] = [
        'tipo'  => 'info',
        'icono' => 'fa-info',
        'texto' => 'Recordá facturar antes del <strong>vencimiento mensual</strong>',
        'tiempo'=> ''
    ];

    // ── Respuesta ──
    echo json_encode([
        'ok'     => true,
        'metricas' => [
            'total_hoy'      => $totalHoy,
            'total_mes'      => $totalMes,
            'cant_facturas'  => $cantFacturas,
            'pendientes'     => $pendientes,
            'mes_label'      => mesEnEspanol(date('n')) . ' ' . date('Y'),
        ],
        'ultimas_ops' => $ultimasOps,
        'actividad'   => $actividad,
        'alertas'     => $alertas,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener datos del dashboard.']);
}


// ── Helpers ──

function formatTiempo(string $fechaStr): string {
    $fecha = new DateTime($fechaStr);
    $ahora = new DateTime();
    $diff  = $ahora->diff($fecha);

    if ($diff->days === 0)      return 'Hoy';
    if ($diff->days === 1)      return 'Ayer';
    if ($diff->days < 7)        return "Hace {$diff->days} días";
    if ($diff->days < 30)       return 'Hace ' . floor($diff->days / 7) . ' semana(s)';
    return $fecha->format('d/m/Y');
}

function mesEnEspanol(int $mes): string {
    $meses = [
        1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril',
        5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto',
        9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'
    ];
    return $meses[$mes] ?? '';
}
