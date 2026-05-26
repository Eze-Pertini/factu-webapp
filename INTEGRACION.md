# Guía de integración — Backend PHP con Factu

## Estructura final de archivos

```
/mini-facturante
├── index.html                ← landing (no cambia)
├── /api
│   ├── login.php             ← endpoint POST que recibe el formulario
│   └── logout.php            ← cierra sesión y redirige
├── /includes
│   ├── db.php                ← conexión PDO a MySQL
│   └── auth.php              ← login(), logout(), verificarSesion()
└── /pages
    ├── dashboard.php         ← renombrado de .html, ahora protegido
    ├── importar.php          ← ídem
    ├── facturar.php          ← ídem
    ├── historial.php         ← ídem
    └── configuracion.php     ← ídem
```

---

## Paso 1 — Crear la base de datos

En phpMyAdmin (o tu cliente SQL favorito):

```sql
CREATE DATABASE mini_facturante CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mini_facturante;

CREATE TABLE usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    activo     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- Usuario de prueba (password: "password123")
INSERT INTO usuarios (nombre, email, password) VALUES (
    'Ezequiel Rodríguez',
    'eze@example.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);
```

---

## Paso 2 — Configurar db.php

Abrí `/includes/db.php` y ajustá las constantes según tu entorno:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mini_facturante');
define('DB_USER', 'root');
define('DB_PASS', '');   // tu contraseña de MySQL
```

---

## Paso 3 — Modificar el formulario de login en index.html

Encontrá el `<form>` del login (cerca de la línea 983) y hacé **dos cambios**:

### Antes (login actual):
```html
<form class="login-form" id="login-form">
```

### Después (conectado al backend):
```html
<form class="login-form" id="login-form">
```
El HTML del form **no cambia**. Solo cambia el JavaScript que lo maneja.

---

## Paso 4 — Modificar app.js para apuntar al backend

Encontrá la función `initLogin()` en `/assets/js/app.js` (cerca de la línea 310)
y reemplazá el bloque del `setTimeout` simulado por un `fetch` real:

### Antes (simulado):
```javascript
setTimeout(() => {
    btn.disabled = false;
    btn.innerHTML = 'Ingresar';
    window.location.href = 'pages/dashboard.html';
}, 1500);
```

### Después (real, apunta al backend):
```javascript
fetch('/api/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
})
.then(res => res.json())
.then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Ingresar';

    if (data.ok) {
        // Login exitoso → redirigir al dashboard PHP
        window.location.href = '/pages/dashboard.php';
    } else {
        // Mostrar error devuelto por el servidor
        showToast(data.mensaje, 'error');
    }
})
.catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Ingresar';
    showToast('Error de conexión. Revisá tu servidor.', 'error');
});
```

---

## Paso 5 — Proteger cada página interna

Al renombrar `dashboard.html` → `dashboard.php`, agregás estas **2 líneas al principio**, antes de cualquier HTML:

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
verificarSesion();
?>
<!DOCTYPE html>
...
```

Eso es todo. Si el usuario no está logueado, `verificarSesion()` lo redirige al login automáticamente.

Para el resto de páginas (importar, facturar, etc.) es exactamente igual:
```php
<?php
require_once __DIR__ . '/../includes/auth.php';
verificarSesion();
?>
```

---

## Paso 6 — Mostrar datos del usuario en el template

Una vez que tenés PHP en la página, podés usar los datos de sesión:

```php
<?php $usuario = getSesionUsuario(); ?>

<!-- En el sidebar -->
<div class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>

<!-- En el header -->
Hola, <?= htmlspecialchars(explode(' ', $usuario['nombre'])[0]) ?> 👋
```

Usá siempre `htmlspecialchars()` para evitar XSS al mostrar datos del usuario.

---

## Resumen del flujo completo

```
Usuario llena el form
        ↓
fetch POST → /api/login.php
        ↓
login.php llama a login() de auth.php
        ↓
auth.php busca en DB y verifica el hash
        ↓
    ¿OK?
   /    \
 SÍ      NO
  ↓       ↓
$_SESSION  JSON { ok: false, mensaje: "..." }
  ↓       ↓
redirect  showToast() con el error
  ↓
/pages/dashboard.php
  ↓
verificarSesion() → OK, continúa
  ↓
Muestra el dashboard con datos reales
```

---

## Servidor local recomendado

Para probar PHP localmente tenés varias opciones:
- **XAMPP** (Windows/Mac/Linux) — el más común para principiantes
- **Laragon** (Windows) — más moderno y fácil
- **MAMP** (Mac)
- `php -S localhost:8000` — servidor built-in de PHP (sin MySQL, solo para probar PHP puro)

Con XAMPP: copiá `/mini-facturante` dentro de `C:/xampp/htdocs/` y accedé desde `http://localhost/mini-facturante/`.
