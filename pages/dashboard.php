<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
verificarSesion();
$usuario    = getSesionUsuario();

// DEBUG TEMPORAL
//echo "<pre style='position:fixed;top:0;right:0;background:red;color:white;z-index:9999;padding:10px'>";
//echo "nombre: " . $usuario['nombre'] . "\n";
//echo "id: " . $usuario['id'] . "\n";
//echo "</pre>";

$puntoVenta = $usuario['puntoVenta'] ?? '0001';

// Saludo dinámico según la hora
$hora = (int)date('H');
if ($hora >= 6  && $hora < 12) $saludo = 'Buenos días';
elseif ($hora >= 12 && $hora < 19) $saludo = 'Buenas tardes';
else $saludo = 'Buenas noches';

// Mes actual en español
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
          'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$mesActual = $meses[(int)date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Factu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

  <div class="app-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
      <header class="app-header">
        <div class="header-left">
          <button class="btn-menu-toggle" id="menu-toggle"><i class="fas fa-bars"></i></button>
          <div>
            <div class="header-title">Dashboard</div>
            <div class="header-subtitle">Resumen de actividad</div>
          </div>
        </div>
        <div class="header-right">
          <button class="header-action-btn" title="Notificaciones"
            onclick="showToast('Revisá los pagos pendientes de facturar', 'info')">
            <i class="fas fa-bell"></i>
            <span class="notification-dot" id="notif-dot" style="display:none"></span>
          </button>
          <div class="header-user">
            <div class="user-avatar" style="width:30px;height:30px;font-size:0.72rem">
              <?= htmlspecialchars($iniciales) ?>
            </div>
            <span class="header-user-name"><?= $primerNombre ?></span>
          </div>
          <a href="<?= $logoutUrl ?>" class="btn btn-ghost btn-sm" title="Cerrar sesión">
            <i class="fas fa-right-from-bracket"></i>
          </a>
        </div>
      </header>

      <div class="page-content" id="dashboard-page">

        <div class="page-header">
          <div class="page-header-info">
            <h2><?= $saludo ?>, <?= $primerNombre ?> 👋</h2>
            <p>Punto de venta: <?= htmlspecialchars($puntoVenta) ?> · <?= $mesActual ?></p>
          </div>
          <div class="page-header-actions">
            <a href="importar.php" class="btn btn-secondary">
              <i class="fas fa-cloud-download-alt"></i> Importar pagos
            </a>
            <a href="facturar.php" class="btn btn-primary">
              <i class="fas fa-file-invoice"></i> Generar facturas
            </a>
          </div>
        </div>

        <!-- MÉTRICAS -->
        <div class="metrics-grid">
          <div class="metric-card blue">
            <div class="metric-icon blue"><i class="fas fa-dollar-sign"></i></div>
            <div class="metric-value" id="metric-hoy">
              <span class="spinner" style="width:16px;height:16px;border-width:2px"></span>
            </div>
            <div class="metric-label">Facturado hoy</div>
            <div class="metric-change up"><i class="fas fa-calendar-day"></i> Hoy</div>
          </div>

          <div class="metric-card green">
            <div class="metric-icon green"><i class="fas fa-chart-line"></i></div>
            <div class="metric-value" id="metric-mes">
              <span class="spinner" style="width:16px;height:16px;border-width:2px"></span>
            </div>
            <div class="metric-label">Total del mes</div>
            <div class="metric-change up">
              <i class="fas fa-calendar"></i>
              <span id="metric-mes-label"><?= $mesActual ?></span>
            </div>
          </div>

          <div class="metric-card orange">
            <div class="metric-icon orange"><i class="fas fa-file-invoice"></i></div>
            <div class="metric-value" id="metric-facturas">
              <span class="spinner" style="width:16px;height:16px;border-width:2px"></span>
            </div>
            <div class="metric-label">Facturas emitidas</div>
            <div class="metric-change up"><i class="fas fa-check"></i> Este mes</div>
          </div>

          <div class="metric-card red">
            <div class="metric-icon red"><i class="fas fa-clock"></i></div>
            <div class="metric-value" id="metric-pendientes">
              <span class="spinner" style="width:16px;height:16px;border-width:2px"></span>
            </div>
            <div class="metric-label">Pendientes de facturar</div>
            <div class="metric-change down"><i class="fas fa-exclamation"></i> Requieren acción</div>
          </div>
        </div>

        <!-- CONTENT GRID -->
        <div class="content-grid">

          <!-- Tabla últimas operaciones -->
          <div class="card">
            <div class="card-header">
              <h3>
                <i class="fas fa-list" style="color:var(--brand-primary);margin-right:8px"></i>
                Últimas operaciones
              </h3>
              <a href="importar.php" class="btn btn-ghost btn-sm">
                Ver todas <i class="fas fa-arrow-right"></i>
              </a>
            </div>
            <div class="table-wrapper">
              <table class="table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Monto</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody id="tbody-ultimas">
                  <tr>
                    <td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">
                      <span class="spinner"></span> Cargando...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="card-footer">
              <a href="importar.php" class="btn btn-primary btn-sm">
                <i class="fas fa-cloud-download-alt"></i> Importar nuevos pagos
              </a>
            </div>
          </div>

          <!-- Panel lateral -->
          <div style="display:flex; flex-direction:column; gap:18px">

            <!-- Alertas dinámicas -->
            <div class="card">
              <div class="card-header">
                <h3>
                  <i class="fas fa-triangle-exclamation" style="color:var(--status-warning);margin-right:8px"></i>
                  Alertas
                </h3>
              </div>
              <div class="card-body" id="alertas-container">
                <div style="text-align:center;padding:12px;color:var(--text-muted)">
                  <span class="spinner"></span>
                </div>
              </div>
            </div>

            <!-- Actividad reciente dinámica -->
            <div class="card">
              <div class="card-header">
                <h3>
                  <i class="fas fa-bolt" style="color:var(--brand-accent);margin-right:8px"></i>
                  Actividad reciente
                </h3>
                <a href="historial.php" class="btn btn-ghost btn-sm">
                  Ver historial <i class="fas fa-arrow-right"></i>
                </a>
              </div>
              <div class="card-body" id="actividad-container">
                <div style="text-align:center;padding:12px;color:var(--text-muted)">
                  <span class="spinner"></span>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/dashboard.js"></script>
</body>
</html>
