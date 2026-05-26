<?php
// ================================================
// api/productos.php — API de productos/servicios
// Factu
// ================================================
// GET    → lista productos del usuario logueado
// POST   → crea un producto nuevo
// DELETE → elimina un producto (?id=X)
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
    'GET'    => handleGet($usuarioId),
    'POST'   => handlePost($usuarioId),
    'DELETE' => handleDelete($usuarioId),
    default  => responderError(405, 'Método no permitido.')
};


// ── GET — Listar productos activos del usuario ──

function handleGet(int $usuarioId): void {
    $db = getDB();

    try {
        $stmt = $db->prepare(
            "SELECT id, nombre, precio
             FROM productos
             WHERE usuario_id = :uid AND activo = 1
             ORDER BY nombre ASC"
        );
        $stmt->execute([':uid' => $usuarioId]);
        $productos = $stmt->fetchAll();

        echo json_encode([
            'ok'    => true,
            'datos' => $productos,
            'meta'  => ['total' => count($productos)]
        ]);

    } catch (PDOException $e) {
        responderError(500, 'Error al obtener productos.');
    }
}


// ── POST — Crear producto nuevo ──

function handlePost(int $usuarioId): void {
    $db = getDB();

    $body  = file_get_contents('php://input');
    $datos = json_decode($body, true);

    if (!$datos) {
        responderError(400, 'Body inválido. Enviá JSON.');
        return;
    }

    $nombre = trim($datos['nombre'] ?? '');
    $precio = (float)($datos['precio'] ?? 0);

    if (empty($nombre)) {
        responderError(400, 'El nombre del producto es obligatorio.');
        return;
    }

    if ($precio < 0) {
        responderError(400, 'El precio no puede ser negativo.');
        return;
    }

    try {
        $stmt = $db->prepare(
            "INSERT INTO productos (usuario_id, nombre, precio)
             VALUES (:uid, :nombre, :precio)"
        );
        $stmt->execute([
            ':uid'    => $usuarioId,
            ':nombre' => $nombre,
            ':precio' => $precio,
        ]);

        http_response_code(201);
        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Producto creado correctamente.',
            'id'      => (int)$db->lastInsertId(),
            'nombre'  => $nombre,
            'precio'  => $precio,
        ]);

    } catch (PDOException $e) {
        responderError(500, 'Error al crear el producto.');
    }
}


// ── DELETE — Eliminar producto (soft delete) ──

function handleDelete(int $usuarioId): void {
    $db = getDB();

    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        responderError(400, 'ID de producto inválido.');
        return;
    }

    try {
        // Soft delete: marcar como inactivo en lugar de borrar
        // Así no rompemos facturas históricas que referencian este producto
        $stmt = $db->prepare(
            "UPDATE productos SET activo = 0
             WHERE id = :id AND usuario_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $usuarioId]);

        if ($stmt->rowCount() === 0) {
            responderError(404, 'Producto no encontrado.');
            return;
        }

        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Producto eliminado correctamente.'
        ]);

    } catch (PDOException $e) {
        responderError(500, 'Error al eliminar el producto.');
    }
}


// ── Helper ──

function responderError(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
    exit;
}
