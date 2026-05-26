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
  <title>Importar Pagos — Factu</title>
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
            <div class="header-title">Importar Pagos</div>
            <div class="header-subtitle">Mercado Pago</div>
          </div>
        </div>
        <div class="header-right">
          <button class="header-action-btn" onclick="showToast('Tenés 3 pagos pendientes de facturar', 'info')">
            <i class="fas fa-bell"></i>
            <span class="notification-dot"></span>
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

      <div class="page-content" id="importar-page">

        <div class="page-header">
          <div class="page-header-info">
            <h2>Importar pagos</h2>
            <p>Seleccioná el período y traé tus cobros de Mercado Pago para facturar</p>
          </div>
        </div>

        <div class="filters-bar">
          <div class="form-group">
            <label class="form-label">Fecha desde</label>
            <input type="date" class="form-input" id="filtro-desde" value="2025-06-01">
          </div>
          <div class="form-group">
            <label class="form-label">Fecha hasta</label>
            <input type="date" class="form-input" id="filtro-hasta" value="2025-06-14">
          </div>
          <div class="form-group" style="flex:0">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-primary" id="btn-importar">
              <i class="fas fa-download"></i> Importar
            </button>
          </div>
          <div class="form-group" style="flex:0">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-secondary" onclick="showToast('Filtros limpiados', 'info')">
              <i class="fas fa-xmark"></i> Limpiar
            </button>
          </div>
        </div>

        <div class="selection-bar hidden" id="selection-bar">
          <span class="selection-info">
            <i class="fas fa-check-square"></i>
            <span id="selection-count">0</span> operaciones seleccionadas ·
            Total: <strong id="selection-total">$0</strong>
          </span>
          <div class="selection-actions">
            <button class="btn btn-ghost btn-sm" onclick="clearSelection()">
              <i class="fas fa-times"></i> Limpiar
            </button>
            <a href="facturar.php" class="btn btn-accent btn-sm">
              <i class="fas fa-file-invoice"></i> Facturar seleccionadas
            </a>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-list" style="color:var(--brand-primary);margin-right:8px"></i>Operaciones importadas</h3>
            <div class="flex" style="gap:8px; align-items:center">
              <span class="badge badge-info">Mercado Pago</span>
              <span class="text-muted" style="font-size:0.8rem">Jun 2025</span>
            </div>
          </div>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr>
                  <th style="width:40px">
                    <input type="checkbox" class="checkbox-custom" id="select-all" title="Seleccionar todo">
                  </th>
                  <th>Fecha</th>
                  <th>ID Operación</th>
                  <th>Cliente (email)</th>
                  <th>Detalle</th>
                  <th>Tipo</th>
                  <th style="text-align:right">Monto</th>
                </tr>
              </thead>
              <tbody id="tbody-operaciones"></tbody>
            </table>
          </div>
          <div class="card-footer">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px">
              <span class="text-muted" style="font-size:0.82rem">
                <i class="fas fa-info-circle"></i>
                Las filas en gris ya fueron facturadas y no se pueden seleccionar
              </span>
              <a href="facturar.php" class="btn btn-primary btn-sm">
                <i class="fas fa-file-invoice"></i> Facturar seleccionadas
              </a>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/app.js"></script>
  <!-- pagos.js: consume /api/pagos.php y maneja la tabla de operaciones -->
  <script src="../assets/js/pagos.js"></script>
</body>
</html>
