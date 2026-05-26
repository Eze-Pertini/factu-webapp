<?php
// ================================================
// api/mp_pagos.php — Integración real con Mercado Pago
// Factu
// ================================================
// GET → consulta la API de MP y devuelve pagos recibidos
//
// Parámetros opcionales:
//   ?desde=2025-06-01   (fecha inicio, formato Y-m-d)
//   ?hasta=2025-06-30   (fecha fin,   formato Y-m-d)
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];

// ── Obtener token desde la DB ──
try {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT mp_access_token, mp_ambiente FROM configuracion WHERE usuario_id = :uid LIMIT 1"
    );
    $stmt->execute([':uid' => $usuarioId]);
    $config = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al obtener configuración.']);
    exit;
}

if (!$config || empty($config['mp_access_token'])) {
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'No hay token de Mercado Pago configurado. Configuralo en Configuración → Integración Mercado Pago.'
    ]);
    exit;
}

$token   = $config['mp_access_token'];
$baseUrl = 'https://api.mercadopago.com';

// Guardar email del usuario en sesión para filtrar pagos propios
if (empty($_SESSION['usuario_email'])) {
    // Obtenerlo de la DB si no está en sesión
    $stmtEmail = $db->prepare("SELECT email FROM usuarios WHERE id = :uid LIMIT 1");
    $stmtEmail->execute([':uid' => $usuarioId]);
    $_SESSION['usuario_email'] = $stmtEmail->fetchColumn() ?? '';
}

// ── Filtros de fecha ──
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// Validar formato de fechas
foreach (['desde' => $desde, 'hasta' => $hasta] as $campo => $fecha) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => "Formato de fecha inválido en '$campo'."]);
        exit;
    }
}

// Convertir a formato ISO 8601 que requiere MP
$desdeISO = $desde . 'T00:00:00.000-03:00';
$hastaISO = $hasta . 'T23:59:59.999-03:00';

// ── Traer pagos desde MP ──
// Usamos /v1/payments con filtros de fecha y estado "approved"
$pagosMP = consultarPagosMP($baseUrl, $token, $desdeISO, $hastaISO);

if ($pagosMP === null) {
    http_response_code(502);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Error al conectar con Mercado Pago. Verificá tu token en Configuración.'
    ]);
    exit;
}

// ── Obtener IDs ya importados en nuestra DB ──
// Para marcar cuáles ya fueron importados previamente
try {
    $stmtIds = $db->prepare("SELECT mp_id FROM pagos WHERE mp_id IS NOT NULL AND mp_id != ''");
    $stmtIds->execute();
    $idsImportados = $stmtIds->fetchAll(PDO::FETCH_COLUMN) ?? [];
    $idsImportados = array_flip($idsImportados); // para búsqueda O(1)
} catch (PDOException $e) {
    $idsImportados = [];
}

// ── Normalizar y enriquecer los pagos ──
$pagosNormalizados = [];

foreach ($pagosMP as $pago) {
    $mpId = (string)($pago['id'] ?? '');

    // ── Excluir solo pagos donde yo fui el pagador con tarjeta (compras propias) ──
    $payerEmail = strtolower($pago['payer']['email'] ?? '');
    $miEmail    = strtolower($_SESSION['usuario_email'] ?? '');
    $tipoPagoMP = $pago['payment_type_id'] ?? '';

    // Tipos de pago que indican que YO pagué (compras)
    $tiposPagoPropio = ['credit_card', 'debit_card', 'prepaid_card', 'digital_wallet', 'ticket'];

    // Si el payer soy yo Y es un pago con tarjeta/efectivo → compra mía, excluir
    if (!empty($miEmail) && $payerEmail === $miEmail && in_array($tipoPagoMP, $tiposPagoPropio)) {
        continue;
    }

    // Si payer.id === collector_id Y es pago propio → excluir
    $payerId     = (int)($pago['payer']['id'] ?? 0);
    $collectorId = (int)($pago['collector_id'] ?? 0);
    if ($payerId > 0 && $payerId === $collectorId && in_array($tipoPagoMP, $tiposPagoPropio)) {
        continue;
    }

    // ── Determinar email/nombre del pagador ──
    $tipoPagoMP  = $pago['payment_type_id'] ?? '';
    $esBankTransfer = $tipoPagoMP === 'bank_transfer';

    $nombrePagador = trim(
        ($pago['payer']['first_name'] ?? '') . ' ' .
        ($pago['payer']['last_name']  ?? '')
    );
    $nombrePagador = trim($nombrePagador);

    $emailPagador = $pago['payer']['email'] ?? '';

    // Para transferencias bancarias externas (CBU/CVU desde banco)
    if ($esBankTransfer) {
        // MP no expone datos del banco externo — usar nombre si existe, sino genérico
        if (!empty($nombrePagador) && $nombrePagador !== ' ') {
            $emailPagador = $nombrePagador;
        } else {
            $emailPagador = 'Cliente genérico';
        }
    } else {
        // Transferencias electrónicas MP y pagos online
        $esEmailGenerico = empty($emailPagador)
            || str_contains($emailPagador, 'bot@')
            || str_contains($emailPagador, '@mercadopago')
            || $emailPagador === $miEmail;

        if ($esEmailGenerico) {
            if (!empty($nombrePagador) && $nombrePagador !== ' ') {
                $emailPagador = $nombrePagador;
            } else {
                $emailPagador = 'Cliente genérico';
            }
        }
    }

    // ── Tipo legible ──
    $tipoMap = [
        'account_money'  => 'Transferencia Electrónica',
        'credit_card'    => 'Tarjeta de crédito',
        'debit_card'     => 'Tarjeta de débito',
        'ticket'         => 'Pago en efectivo',
        'bank_transfer'  => 'Transferencia bancaria',
        'digital_wallet' => 'Billetera digital',
        'prepaid_card'   => 'Tarjeta prepaga',
    ];
    $tipo = $tipoMap[$tipoPagoMP] ?? 'Pago Regular';

    // ── Detalle ──
    $detalle = $pago['description'] ?? $pago['statement_descriptor'] ?? '';
    if (empty(trim($detalle)) || strtolower(trim($detalle)) === 'var') {
        $detalle = $tipo;
    }

    // ── Fecha ──
    $fecha = $pago['date_approved'] ?? $pago['date_created'] ?? null;
    if ($fecha) {
        try {
            $dt    = new DateTime($fecha);
            $fecha = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $fecha = date('Y-m-d H:i:s');
        }
    }

    $yaImportado = isset($idsImportados[$mpId]);

    $pagosNormalizados[] = [
        'id'            => $mpId,
        'fecha'         => $fecha,
        'email_cliente' => $emailPagador,
        'nombre_pagador'=> $nombrePagador,
        'detalle'       => $detalle,
        'tipo'          => $tipo,
        'monto'         => (float)($pago['transaction_amount'] ?? 0),
        'estado_mp'     => $pago['status'] ?? '',
        'ya_importado'  => $yaImportado,
        'moneda'        => $pago['currency_id'] ?? 'ARS',
    ];
}

// Ordenar por fecha descendente
usort($pagosNormalizados, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

echo json_encode([
    'ok'    => true,
    'datos' => $pagosNormalizados,
    'meta'  => [
        'total'         => count($pagosNormalizados),
        'desde'         => $desde,
        'hasta'         => $hasta,
        'ya_importados' => count(array_filter($pagosNormalizados, fn($p) => $p['ya_importado'])),
    ]
]);


// ── Obtener user ID de MP para filtrar como collector ──
function obtenerMpUserId(string $baseUrl, string $token): ?int {
    $data = hacerRequestMP("$baseUrl/users/me", $token);
    return isset($data['id']) ? (int)$data['id'] : null;
}


// ================================================
// Función: consultar pagos aprobados en MP
// ================================================

function consultarPagosMP(string $baseUrl, string $token, string $desde, string $hasta): ?array {
    // Obtener el user ID del collector (yo como cobrador)
    $collectorId = obtenerMpUserId($baseUrl, $token);

    if (!$collectorId) return null;

    $pagos  = [];
    $offset = 0;
    $limit  = 100;

    do {
        $params = http_build_query([
            'sort'             => 'date_created',
            'criteria'         => 'desc',
            'begin_date'       => $desde,
            'end_date'         => $hasta,
            'status'           => 'approved',
            'collector.id'     => $collectorId,  // ← solo cobros recibidos por mí
            'offset'           => $offset,
            'limit'            => $limit,
        ]);

        $url = "$baseUrl/v1/payments/search?$params";

        $respuesta = hacerRequestMP($url, $token);

        if ($respuesta === null) return null;

        $results = $respuesta['results'] ?? [];
        $pagos   = array_merge($pagos, $results);

        $total   = $respuesta['paging']['total'] ?? 0;
        $offset += $limit;

    } while ($offset < $total && count($pagos) < 500);

    return $pagos;
}


// ================================================
// Función: hacer request HTTP a la API de MP
// ================================================

function hacerRequestMP(string $url, string $token): ?array {
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid('mf_', true),
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error || $response === false) return null;

    $data = json_decode($response, true);

    if ($httpCode === 401) {
        // Token inválido o expirado
        http_response_code(401);
        echo json_encode([
            'ok'      => false,
            'mensaje' => 'Token de Mercado Pago inválido o expirado. Actualizalo en Configuración.'
        ]);
        exit;
    }

    if ($httpCode !== 200) return null;

    return $data;
}