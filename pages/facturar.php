<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
verificarSesion();
$usuario    = getSesionUsuario();
$cuit       = '—';
$puntoVenta = $usuario['puntoVenta'] ?? '0001';

// Obtener CUIT desde configuracion
$db      = getDB();
$stmtCfg = $db->prepare("SELECT cuit FROM configuracion WHERE usuario_id = :uid LIMIT 1");
$stmtCfg->execute([':uid' => $usuario['id']]);
$config  = $stmtCfg->fetch();
$cuit    = $config['cuit'] ?? '—';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Generar Facturas — Factu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <style>
    /* ── TABS ── */
    .facturar-tabs {
      display: flex;
      gap: 0;
      border-bottom: 2px solid var(--border-color);
      margin-bottom: 24px;
    }

    .facturar-tab {
      padding: 12px 24px;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--text-secondary);
      cursor: pointer;
      border: none;
      background: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: var(--font-body);
    }

    .facturar-tab:hover {
      color: var(--text-primary);
      background: var(--bg-app);
    }

    .facturar-tab.active {
      color: var(--brand-primary);
      border-bottom-color: var(--brand-primary);
      background: transparent;
    }

    .facturar-panel.hidden {
      display: none;
    }
  </style>
</head>
<body>

  <div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
      <header class="app-header">
        <div class="header-left">
          <button class="btn-menu-toggle" id="menu-toggle"><i class="fas fa-bars"></i></button>
          <div>
            <div class="header-title">Generar Facturas</div>
            <div class="header-subtitle">Emitir comprobantes en AFIP</div>
          </div>
        </div>
        <div class="header-right">
          <button class="header-action-btn" onclick="showToast('Notificaciones', 'info')">
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

      <div class="page-content" id="facturar-page">

        <div class="page-header">
          <div class="page-header-info">
            <h2>Generar comprobantes</h2>
            <p>Generá facturas desde tus pagos de Mercado Pago o cargá una manualmente</p>
          </div>
          <div class="page-header-actions">
            <a href="importar.php" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> Volver a importar
            </a>
          </div>
        </div>

        <!-- TABS -->
        <div class="facturar-tabs">
          <button class="facturar-tab active" data-target="panel-mp">
            <i class="fas fa-cloud-download-alt"></i>
            Desde Mercado Pago
          </button>
          <button class="facturar-tab" data-target="panel-manual">
            <i class="fas fa-pen"></i>
            Carga manual
          </button>
        </div>

        <!-- ══════════════════════════════════
             TAB 1 — DESDE MERCADO PAGO
        ══════════════════════════════════ -->
        <div class="facturar-panel" id="panel-mp">
          <div class="content-grid">

            <!-- Formulario izquierda -->
            <div style="display:flex; flex-direction:column; gap:22px">

              <div class="card">
                <div class="card-header">
                  <h3><i class="fas fa-sliders" style="color:var(--brand-primary);margin-right:8px"></i>Configuración del comprobante</h3>
                </div>
                <div class="card-body">
                  <div style="display:flex; flex-direction:column; gap:20px">

                    <div class="form-group">
                      <label class="form-label">Tipo de comprobante</label>
                      <select class="form-select" id="select-tipo">
                        <option value="factura_c" selected>Factura C (Monotributista)</option>
                        <option value="nota_credito">Nota de crédito C</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Concepto <span style="color:var(--status-error)">*</span></label>
                      <select class="form-select" id="select-concepto">
                        <option value="">— Seleccioná un concepto —</option>
                        <option value="Servicios">Servicios</option>
                        <option value="Productos">Productos</option>
                        <option value="Productos y Servicios">Productos y Servicios</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">
                        Producto / Servicio
                        <span class="text-muted" style="font-size:0.75rem;font-weight:400">(opcional)</span>
                      </label>
                      <select class="form-select" id="select-producto">
                        <option value="">— Sin especificar (usa el detalle del pago) —</option>
                      </select>
                      <span class="text-muted" style="font-size:0.78rem;margin-top:4px">
                        Administrá tus productos en
                        <a href="configuracion.php" style="color:var(--brand-primary)">Configuración</a>
                      </span>
                    </div>

                    <div class="form-row">
                      <div class="form-group">
                        <label class="form-label">Fecha de servicio <span style="color:var(--status-error)">*</span></label>
                        <input type="date" class="form-input" id="fecha-servicio">
                      </div>
                      <div class="form-group">
                        <label class="form-label">Fecha de cobro <span style="color:var(--status-error)">*</span></label>
                        <input type="date" class="form-input" id="fecha-cobro">
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Punto de venta</label>
                      <input type="text" class="form-input" value="<?= htmlspecialchars($puntoVenta) ?>" readonly style="background:var(--bg-app);cursor:default">
                    </div>

                  </div>
                </div>
              </div>

              <div class="card" style="border:1px solid rgba(26,86,219,0.2);background:var(--brand-primary-light)">
                <div class="card-body">
                  <div class="flex" style="gap:14px;align-items:flex-start">
                    <div style="width:36px;height:36px;background:var(--brand-primary);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                      <i class="fas fa-shield-halved" style="color:white;font-size:0.9rem"></i>
                    </div>
                    <div>
                      <div style="font-weight:600;color:var(--brand-primary);margin-bottom:4px">Conexión con AFIP/ARCA</div>
                      <div class="text-muted" style="font-size:0.83rem;line-height:1.6">
                        Los comprobantes se generarán con tus credenciales fiscales.
                        CUIT: <strong style="color:var(--text-primary)"><?= htmlspecialchars($cuit) ?></strong>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Panel resumen derecha -->
            <div style="display:flex; flex-direction:column; gap:22px">

              <div class="summary-box">
                <h3>Total a facturar</h3>
                <div class="summary-amount" id="resumen-total">$0,00</div>
                <div class="summary-detail">
                  <i class="fas fa-file-invoice"></i>
                  <span id="resumen-cantidad">0</span> operaciones seleccionadas
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <h3><i class="fas fa-list-check" style="color:var(--brand-accent);margin-right:8px"></i>Operaciones a facturar</h3>
                  <a href="importar.php" class="btn btn-ghost btn-sm">
                    <i class="fas fa-pencil"></i> Editar
                  </a>
                </div>
                <div class="card-body" id="resumen-lista" style="display:flex;flex-direction:column;gap:0"></div>
              </div>

              <button class="btn btn-accent btn-full btn-lg" id="btn-generar">
                <i class="fas fa-file-invoice"></i> Generar comprobantes
              </button>

              <p class="text-muted text-center" style="font-size:0.78rem">
                <i class="fas fa-lock"></i>
                La generación es irreversible. Revisá los datos antes de continuar.
              </p>

            </div>
          </div>
        </div><!-- /panel-mp -->


        <!-- ══════════════════════════════════
             TAB 2 — CARGA MANUAL
        ══════════════════════════════════ -->
        <div class="facturar-panel hidden" id="panel-manual">
          <div class="content-grid">

            <!-- Formulario manual izquierda -->
            <div style="display:flex; flex-direction:column; gap:22px">

              <!-- Datos del cliente -->
              <div class="card">
                <div class="card-header">
                  <h3><i class="fas fa-user" style="color:var(--brand-primary);margin-right:8px"></i>Datos del cliente</h3>
                </div>
                <div class="card-body">
                  <div style="display:flex; flex-direction:column; gap:18px">

                    <div class="form-group">
                      <label class="form-label">Email <span style="color:var(--status-error)">*</span></label>
                      <input type="email" class="form-input" id="manual-email" placeholder="cliente@email.com">
                    </div>

                    <div class="form-row">
                      <div class="form-group">
                        <label class="form-label">
                          Nombre / Razón social
                          <span class="text-muted" style="font-size:0.75rem;font-weight:400">(opcional)</span>
                        </label>
                        <input type="text" class="form-input" id="manual-razon" placeholder="Juan García">
                      </div>
                      <div class="form-group">
                        <label class="form-label">
                          DNI / CUIT
                          <span class="text-muted" style="font-size:0.75rem;font-weight:400">(opcional)</span>
                        </label>
                        <input type="text" class="form-input" id="manual-dni" placeholder="20-12345678-9">
                      </div>
                    </div>

                  </div>
                </div>
              </div>

              <!-- Datos del comprobante -->
              <div class="card">
                <div class="card-header">
                  <h3><i class="fas fa-file-invoice" style="color:var(--brand-primary);margin-right:8px"></i>Datos del comprobante</h3>
                </div>
                <div class="card-body">
                  <div style="display:flex; flex-direction:column; gap:18px">

                    <div class="form-group">
                      <label class="form-label">Tipo de comprobante</label>
                      <select class="form-select" id="manual-tipo">
                        <option value="factura_c" selected>Factura C (Monotributista)</option>
                        <option value="nota_credito">Nota de crédito C</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Concepto <span style="color:var(--status-error)">*</span></label>
                      <select class="form-select" id="manual-concepto">
                        <option value="">— Seleccioná un concepto —</option>
                        <option value="Servicios">Servicios</option>
                        <option value="Productos">Productos</option>
                        <option value="Productos y Servicios">Productos y Servicios</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">
                        Producto / Servicio
                        <span class="text-muted" style="font-size:0.75rem;font-weight:400">(opcional)</span>
                      </label>
                      <select class="form-select" id="manual-producto">
                        <option value="">— Sin especificar —</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">
                        Detalle / Descripción
                        <span class="text-muted" style="font-size:0.75rem;font-weight:400">(opcional)</span>
                      </label>
                      <input type="text" class="form-input" id="manual-detalle" placeholder="Ej: Desarrollo de sitio web - Junio 2025">
                    </div>

                    <div class="form-row">
                      <div class="form-group">
                        <label class="form-label">Monto <span style="color:var(--status-error)">*</span></label>
                        <input type="number" class="form-input" id="manual-monto" placeholder="50000" min="0.01" step="0.01">
                      </div>
                      <div class="form-group">
                        <label class="form-label">
                          Forma de pago
                          <span class="text-muted" style="font-size:0.75rem;font-weight:400">(opcional)</span>
                        </label>
                        <select class="form-select" id="manual-forma-pago">
                          <option value="">— Sin especificar —</option>
                          <option value="Efectivo">Efectivo</option>
                          <option value="Transferencia bancaria">Transferencia bancaria</option>
                          <option value="Cheque">Cheque</option>
                          <option value="Mercado Pago">Mercado Pago</option>
                          <option value="Tarjeta de crédito">Tarjeta de crédito</option>
                          <option value="Tarjeta de débito">Tarjeta de débito</option>
                          <option value="Cripto">Cripto</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group">
                        <label class="form-label">Fecha de servicio <span style="color:var(--status-error)">*</span></label>
                        <input type="date" class="form-input" id="manual-fecha-servicio">
                      </div>
                      <div class="form-group">
                        <label class="form-label">Fecha de cobro <span style="color:var(--status-error)">*</span></label>
                        <input type="date" class="form-input" id="manual-fecha-cobro">
                      </div>
                    </div>

                  </div>
                </div>
              </div>

            </div>

            <!-- Panel acción derecha -->
            <div style="display:flex; flex-direction:column; gap:22px">

              <!-- Info -->
              <div class="card" style="border:1px solid rgba(15,191,127,0.2);background:var(--brand-accent-light)">
                <div class="card-body">
                  <div class="flex" style="gap:14px;align-items:flex-start">
                    <div style="width:36px;height:36px;background:var(--brand-accent);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                      <i class="fas fa-pen" style="color:white;font-size:0.9rem"></i>
                    </div>
                    <div>
                      <div style="font-weight:600;color:var(--brand-accent-dark);margin-bottom:4px">Factura manual</div>
                      <div class="text-muted" style="font-size:0.83rem;line-height:1.6">
                        Usá esta opción para cobros recibidos en efectivo, transferencia u otros medios fuera de Mercado Pago.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Resumen manual -->
              <div class="card">
                <div class="card-header">
                  <h3><i class="fas fa-receipt" style="color:var(--brand-accent);margin-right:8px"></i>Resumen</h3>
                </div>
                <div class="card-body">
                  <div style="display:flex;flex-direction:column;gap:10px;font-size:0.875rem">
                    <div style="display:flex;justify-content:space-between">
                      <span class="text-muted">Cliente:</span>
                      <span id="preview-email" style="font-weight:500">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                      <span class="text-muted">Concepto:</span>
                      <span id="preview-concepto" style="font-weight:500">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                      <span class="text-muted">Forma de pago:</span>
                      <span id="preview-forma" style="font-weight:500">—</span>
                    </div>
                    <hr style="border:none;border-top:1px solid var(--border-color);margin:4px 0">
                    <div style="display:flex;justify-content:space-between;font-size:1.1rem">
                      <span style="font-weight:600">Total:</span>
                      <span id="preview-monto" style="font-weight:700;color:var(--brand-primary)">$0,00</span>
                    </div>
                  </div>
                </div>
              </div>

              <button class="btn btn-accent btn-full btn-lg" id="btn-generar-manual">
                <i class="fas fa-file-invoice"></i> Generar comprobante
              </button>

              <p class="text-muted text-center" style="font-size:0.78rem">
                <i class="fas fa-lock"></i>
                La generación es irreversible. Revisá los datos antes de continuar.
              </p>

            </div>
          </div>
        </div><!-- /panel-manual -->

      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/productos.js"></script>
  <script src="../assets/js/facturar.js"></script>
  <script>
    // Preview en tiempo real del formulario manual
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fecha-servicio').value         = hoy;
    document.getElementById('fecha-cobro').value            = hoy;
    document.getElementById('manual-fecha-servicio').value  = hoy;
    document.getElementById('manual-fecha-cobro').value     = hoy;

    const fmt_prev = (n) => new Intl.NumberFormat('es-AR', {
      style: 'currency', currency: 'ARS', minimumFractionDigits: 2
    }).format(n || 0);

    function actualizarPreview() {
      const email    = document.getElementById('manual-email')?.value || '—';
      const concepto = document.getElementById('manual-concepto')?.value || '—';
      const forma    = document.getElementById('manual-forma-pago')?.value || '—';
      const monto    = parseFloat(document.getElementById('manual-monto')?.value) || 0;

      document.getElementById('preview-email').textContent   = email || '—';
      document.getElementById('preview-concepto').textContent = concepto || '—';
      document.getElementById('preview-forma').textContent   = forma || '—';
      document.getElementById('preview-monto').textContent   = fmt_prev(monto);
    }

    ['manual-email','manual-concepto','manual-forma-pago','manual-monto'].forEach(id => {
      document.getElementById(id)?.addEventListener('input', actualizarPreview);
      document.getElementById(id)?.addEventListener('change', actualizarPreview);
    });
  </script>
</body>
</html>
