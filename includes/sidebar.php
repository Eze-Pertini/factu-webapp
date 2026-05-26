<?php
// ================================================
// includes/sidebar.php — Sidebar reutilizable
// Incluir en cada página con:
//   require_once __DIR__ . '/../includes/sidebar.php';
// Requiere que $usuario esté definido antes de incluirlo.
// ================================================

// DEBUG TEMPORAL
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
//var_dump($usuario);
//die();

$partes         = explode(' ', $usuario['nombre']);
$iniciales      = strtoupper(substr($partes[0], 0, 1) . (isset($partes[1]) ? substr($partes[1], 0, 1) : ''));
$primerNombre   = htmlspecialchars($partes[0]);
$nombreCompleto = htmlspecialchars($usuario['nombre']);
$paginaActual   = basename($_SERVER['PHP_SELF']);

// Helper: devuelve 'active' si la página coincide
function navActivo(string $pagina, string $actual): string {
    return $pagina === $actual ? 'active' : '';
}

// Ruta al logout relativa desde /pages/
$logoutUrl = BASE_URL . '/api/logout.php';
?>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-logo">
      <i class="fas fa-file-invoice-dollar"></i>
    </div>
    <div>
      <div class="sidebar-brand-name">Factu</div>
      <div class="sidebar-brand-tagline">Facturación electrónica</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <span class="nav-section-title">Menú principal</span>
    <a href="dashboard.php"     class="nav-item <?= navActivo('dashboard.php',     $paginaActual) ?>" data-page="dashboard.php">
      <i class="fas fa-gauge-high"></i> Dashboard
    </a>
    <a href="importar.php"      class="nav-item <?= navActivo('importar.php',      $paginaActual) ?>" data-page="importar.php">
      <i class="fas fa-cloud-download-alt"></i> Importar pagos
    </a>
    <a href="facturar.php"      class="nav-item <?= navActivo('facturar.php',      $paginaActual) ?>" data-page="facturar.php">
      <i class="fas fa-file-invoice"></i> Generar facturas
    </a>
    <a href="historial.php"     class="nav-item <?= navActivo('historial.php',     $paginaActual) ?>" data-page="historial.php">
      <i class="fas fa-clock-rotate-left"></i> Historial
    </a>
    <span class="nav-section-title">Sistema</span>
    <a href="configuracion.php" class="nav-item <?= navActivo('configuracion.php', $paginaActual) ?>" data-page="configuracion.php">
      <i class="fas fa-gear"></i> Configuración
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= htmlspecialchars($iniciales) ?></div>
      <div class="user-info">
        <div class="user-name"><?= $nombreCompleto ?></div>
        <div class="user-role">Administrador</div>
      </div>
    </div>
  </div>
</aside>