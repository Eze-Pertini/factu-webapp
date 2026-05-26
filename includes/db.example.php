<?php
// ================================================
// db.php — Conexión a la base de datos
// Factu
// ================================================

// ── CONFIGURACIÓN ──────────────────────────────
// Cambiá estos valores según tu entorno local

// URL base del proyecto.
// En local con XAMPP:   '/mini-facturante'
// En producción (dominio propio): '' (cadena vacía)
define('BASE_URL',  '/mini-facturante');

define('DB_HOST',   'localhost');
define('DB_NAME',   'mini_facturante');
define('DB_USER',   'root');
define('DB_PASS',   '');          // En local suele estar vacía
define('DB_CHARSET','utf8mb4');

// ── CONEXIÓN CON PDO ───────────────────────────
// Usamos PDO porque es más seguro y flexible que mysqli.
// Siempre usá prepared statements (nunca concatenes variables en SQL).

function getDB(): PDO {
    // "static" hace que la conexión se cree una sola vez por request
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST
             . ";dbname=" . DB_NAME
             . ";charset=" . DB_CHARSET;

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Lanza excepciones en errores
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Devuelve arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                   // Prepared statements reales
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        } catch (PDOException $e) {
            // En producción nunca muestres el error real al usuario
            // Acá lo mostramos porque estamos en desarrollo
            http_response_code(500);
            die(json_encode([
                'ok'      => false,
                'mensaje' => 'Error de conexión a la base de datos.',
                'detalle' => $e->getMessage() // ← quitá esta línea en producción
            ]));
        }
    }

    return $pdo;
}


// ── SQL PARA CREAR LA TABLA DE USUARIOS ────────
// Ejecutá esto una sola vez en tu base de datos (phpMyAdmin, TablePlus, etc.)
//
// CREATE TABLE usuarios (
//     id         INT AUTO_INCREMENT PRIMARY KEY,
//     nombre     VARCHAR(100)  NOT NULL,
//     email      VARCHAR(150)  NOT NULL UNIQUE,
//     password   VARCHAR(255)  NOT NULL,       -- siempre hasheado con password_hash()
//     activo     TINYINT(1)    NOT NULL DEFAULT 1,
//     created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
// );
//
// Insertar usuario de prueba (password: "password123"):
//
// INSERT INTO usuarios (nombre, email, password) VALUES (
//     'Ezequiel Rodríguez',
//     'eze@example.com',
//     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
// );
//
// Podés generar tu propio hash en PHP así:
// echo password_hash('tu_contraseña', PASSWORD_DEFAULT);
