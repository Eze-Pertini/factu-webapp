<?php
require_once __DIR__ . '/../includes/auth.php';
verificarSesion();
$usuario = getSesionUsuario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Historial — Factu</title>
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
            <div class="header-title">Historial</div>
            <div class="header-subtitle">Comprobantes emitidos</div>
          </div>
        </div>
        <div class="header-right">
          <button class="header-action-btn" onclick="showToast('Sin nuevas notificaciones', 'info')">
            <i class="fas fa-bell"></i>
          </button>
          <div class="header-user">
            <div class="user-avatar" style="width:30px;height:30px;font-size:0.72rem"><?= htmlspecialchars($iniciales) ?></div>
            <span class="header-user-name"><?= $primerNombre ?></span>
          </div>
          <a href="<?= $logoutUrl ?>" class="btn btn-ghost btn-sm" title="Cerrar sesión">
            <i class="fas fa-right-from-bracket"></i>
          </a>
        </div>
      </header>

      <div class="page-content" id="historial-page">

        <div class="page-header">
          <div class="page-header-info">
            <h2>Historial de facturas</h2>
            <p>Todos los comprobantes generados en AFIP/ARCA</p>
          </div>
          <div class="page-header-actions">
            <button class="btn btn-secondary" onclick="showToast('Exportando historial...', 'info')">
              <i class="fas fa-file-excel"></i> Exportar Excel
            </button>
            <a href="facturar.php" class="btn btn-primary">
              <i class="fas fa-plus"></i> Nueva factura
            </a>
          </div>
        </div>

        <div class="metrics-grid" style="margin-bottom:22px">
          <div class="metric-card blue">
            <div class="metric-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="metric-value" id="metric-total-comp">—</div>
            <div class="metric-label">Total de comprobantes</div>
          </div>
          <div class="metric-card green">
            <div class="metric-icon green"><i class="fas fa-circle-check"></i></div>
            <div class="metric-value" id="metric-emitidas">—</div>
            <div class="metric-label">Facturas emitidas</div>
          </div>
          <div class="metric-card red">
            <div class="metric-icon red"><i class="fas fa-ban"></i></div>
            <div class="metric-value" id="metric-anuladas">—</div>
            <div class="metric-label">Anuladas</div>
          </div>
          <div class="metric-card orange">
            <div class="metric-icon orange"><i class="fas fa-dollar-sign"></i></div>
            <div class="metric-value" id="metric-monto-total">—</div>
            <div class="metric-label">Total facturado</div>
          </div>
        </div>

        <div class="filters-bar" style="margin-bottom:18px">
          <div class="form-group">
            <label class="form-label">Estado</label>
            <select class="form-select" id="filtro-estado">
              <option value="">Todos los estados</option>
              <option value="emitida">Emitida</option>
              <option value="anulada">Anulada</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tipo</label>
            <select class="form-select">
              <option value="">Todos los tipos</option>
              <option>Factura C</option>
              <option>Nota de crédito C</option>
            </select>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-clock-rotate-left" style="color:var(--brand-primary);margin-right:8px"></i>Comprobantes emitidos</h3>
            <span class="badge badge-success">AFIP conectado</span>
          </div>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Cliente</th>
                  <th>Tipo</th>
                  <th>Número</th>
                  <th style="text-align:right">Monto</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody id="tbody-historial"></tbody>
            </table>
          </div>
          <div class="card-footer">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
              <span class="text-muted" style="font-size:0.82rem">Mostrando 6 de 6 comprobantes</span>
              <div style="display:flex;gap:6px">
                <button class="btn btn-ghost btn-sm" disabled><i class="fas fa-chevron-left"></i></button>
                <button class="btn btn-secondary btn-sm">1</button>
                <button class="btn btn-ghost btn-sm" disabled><i class="fas fa-chevron-right"></i></button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/historial.js"></script>
</body>
</html>
