<?php
// ================================================
// auth.php — Funciones de autenticación
// Factu
// ================================================

require_once __DIR__ . '/db.php';

// Iniciamos sesión una sola vez (si no está iniciada ya)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ── login() ────────────────────────────────────
// Recibe email y contraseña en texto plano.
// Busca el usuario en la DB y verifica el hash.
// Devuelve array con 'ok' => true/false y 'mensaje'.

function login(string $email, string $password): array {

    // 1. Validaciones básicas antes de tocar la DB
    if (empty($email) || empty($password)) {
        return ['ok' => false, 'mensaje' => 'Email y contraseña son obligatorios.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensaje' => 'El email no tiene un formato válido.'];
    }

    // 2. Buscar usuario en la DB por email
    //    Nunca concatenes $email directo en el SQL → siempre prepared statements
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, nombre, email, password, activo FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch(); // Devuelve array asociativo o false si no existe
    } catch (PDOException $e) {
        return ['ok' => false, 'mensaje' => 'Error interno. Intentá de nuevo.'];
    }

    // 3. Verificar que el usuario exista y esté activo
    if (!$usuario) {
        // Mismo mensaje que "contraseña incorrecta" → no reveles cuál de los dos falló
        return ['ok' => false, 'mensaje' => 'Email o contraseña incorrectos.'];
    }

    if (!$usuario['activo']) {
        return ['ok' => false, 'mensaje' => 'Tu cuenta está desactivada.'];
    }

    // 4. Verificar contraseña con password_verify()
    //    password_verify() compara el texto plano con el hash almacenado
    if (!password_verify($password, $usuario['password'])) {
        return ['ok' => false, 'mensaje' => 'Email o contraseña incorrectos.'];
    }

    // 5. Login correcto → guardar datos en $_SESSION
    //    Nunca guardes la contraseña en sesión
    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_email']  = $usuario['email'];
    $_SESSION['logueado']       = true;

    // Regenerar el ID de sesión previene ataques de fijación de sesión
    session_regenerate_id(true);

    return [
        'ok'      => true,
        'mensaje' => 'Login exitoso.',
        'usuario' => [
            'nombre' => $usuario['nombre'],
            'email'  => $usuario['email'],
        ]
    ];
}


// ── logout() ───────────────────────────────────
// Destruye la sesión completamente.

function logout(): void {
    // Limpiar el array de sesión
    $_SESSION = [];

    // Destruir la cookie de sesión del navegador
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    // Destruir la sesión del servidor
    session_destroy();
}


// ── verificarSesion() ──────────────────────────
// Verificar si hay un usuario logueado.
// Si no hay sesión activa, redirige al login.
// Usala al inicio de cada página protegida.

function verificarSesion(): void {
    if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
        header('Location: ' . BASE_URL . '/index.html?sesion=expirada');
        exit;
    }
}


// ── getSesionUsuario() ─────────────────────────
// Devuelve los datos del usuario logueado.
// Lee desde la DB para garantizar datos siempre frescos.

function getSesionUsuario(): array {
    if (empty($_SESSION['usuario_id'])) {
        return ['id' => null, 'nombre' => '', 'email' => '', 'puntoVenta' => '0001'];
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.nombre, u.email,
                   COALESCE(c.punto_venta, '0001') as puntoVenta
            FROM usuarios u
            LEFT JOIN configuracion c ON c.usuario_id = u.id
            WHERE u.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $_SESSION['usuario_id']]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return ['id' => null, 'nombre' => '', 'email' => '', 'puntoVenta' => '0001'];
        }

        // Actualizar sesión con datos frescos
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_email']  = $usuario['email'];

        return [
            'id'          => (int)$usuario['id'],
            'nombre'      => $usuario['nombre'],
            'email'       => $usuario['email'],
            'puntoVenta'  => $usuario['puntoVenta'],
        ];

    } catch (PDOException $e) {
        // DEBUG TEMPORAL — borrá esto después
        error_log("getSesionUsuario ERROR: " . $e->getMessage());
        file_put_contents('C:/xampp/htdocs/mini-facturante/debug.txt', $e->getMessage());
        
        return [
            'id'         => $_SESSION['usuario_id']     ?? null,
            'nombre'     => $_SESSION['usuario_nombre'] ?? '',
            'email'      => $_SESSION['usuario_email']  ?? '',
            'puntoVenta' => '0001',
        ];
    }
}
