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
  <title>Configuración — Factu</title>
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
            <div class="header-title">Configuración</div>
            <div class="header-subtitle">Datos fiscales y preferencias</div>
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

      <div class="page-content" id="config-page">

        <div class="page-header">
          <div class="page-header-info">
            <h2>Configuración</h2>
            <p>Administrá tus datos fiscales, productos y preferencias del sistema</p>
          </div>
        </div>

        <div class="content-grid">

          <!-- Columna principal -->
          <div style="display:flex; flex-direction:column; gap:22px">

            <div class="card">
              <div class="card-header">
                <h3><i class="fas fa-building" style="color:var(--brand-primary);margin-right:8px"></i>Datos fiscales</h3>
                <span class="badge badge-success">AFIP conectado</span>
              </div>
              <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:18px">
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">CUIT <span style="color:var(--status-error)">*</span></label>
                      <input type="text" class="form-input" id="config-cuit" placeholder="20-00000000-0">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Punto de venta <span style="color:var(--status-error)">*</span></label>
                      <input type="text" class="form-input" id="config-pv" placeholder="0001" maxlength="4">
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Razón social / Nombre y apellido <span style="color:var(--status-error)">*</span></label>
                    <input type="text" class="form-input" id="config-razon" placeholder="Tu nombre completo o razón social">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Email de contacto</label>
                    <input type="email" class="form-input" id="config-email" placeholder="tu@email.com">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Condición fiscal</label>
                    <select class="form-select">
                      <option selected>Monotributista</option>
                      <option>Responsable Inscripto</option>
                      <option>Exento</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Categoría de monotributo</label>
                    <select class="form-select">
                      <option>Categoría A</option>
                      <option>Categoría B</option>
                      <option>Categoría C</option>
                      <option selected>Categoría D</option>
                      <option>Categoría E</option>
                      <option>Categoría F</option>
                    </select>
                  </div>
                  <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button class="btn btn-secondary" onclick="showToast('Cambios descartados', 'info')">Cancelar</button>
                    <button class="btn btn-primary" id="btn-guardar-fiscal">
                      <i class="fas fa-save"></i> Guardar cambios
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <h3><i class="fas fa-credit-card" style="color:#009ee3;margin-right:8px"></i>Integración Mercado Pago</h3>
                <span class="badge badge-warning">Por configurar</span>
              </div>
              <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:18px">
                  <div class="form-group">
                    <label class="form-label">Access Token</label>
                    <input type="password" class="form-input" id="mp-token"
                      placeholder="APP_USR-xxxx-xxxx-xxxx-xxxx"
                      value="APP_USR-1234567890-demo-token">
                    <span class="text-muted" style="font-size:0.78rem;margin-top:4px">
                      Obtené tu Access Token desde el panel de
                      <a href="#" style="color:var(--brand-primary)" onclick="showToast('Abriendo Mercado Pago Developers...', 'info')">
                        Mercado Pago Developers
                      </a>
                    </span>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Ambiente</label>
                    <select class="form-select">
                      <option>Producción</option>
                      <option selected>Sandbox (pruebas)</option>
                    </select>
                  </div>
                  <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button class="btn btn-secondary" onclick="showToast('Conexión con Mercado Pago exitosa ✓', 'success')">
                      <i class="fas fa-plug"></i> Probar conexión
                    </button>
                    <button class="btn btn-primary" onclick="showToast('Token guardado', 'success')">
                      <i class="fas fa-save"></i> Guardar token
                    </button>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Columna lateral -->
          <div style="display:flex; flex-direction:column; gap:22px">

            <div class="card">
              <div class="card-header">
                <h3><i class="fas fa-box" style="color:var(--brand-accent);margin-right:8px"></i>Productos / Servicios</h3>
              </div>
              <div class="card-body">
                <div class="config-section">
                  <div class="config-section-title">Tus productos</div>
                  <div id="lista-productos"></div>
                </div>
                <div class="config-section">
                  <div class="config-section-title">Agregar producto</div>
                  <div style="display:flex;flex-direction:column;gap:12px">
                    <div class="form-group">
                      <label class="form-label">Nombre del producto/servicio</label>
                      <input type="text" class="form-input" id="nuevo-producto" placeholder="Ej: Desarrollo web">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Precio (ARS)</label>
                      <input type="number" class="form-input" id="nuevo-precio" placeholder="50000" min="0" step="100">
                    </div>
                    <button class="btn btn-accent btn-full" id="btn-agregar-producto">
                      <i class="fas fa-plus"></i> Agregar producto
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <h3><i class="fas fa-key" style="color:var(--status-warning);margin-right:8px"></i>Certificado AFIP</h3>
              </div>
              <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:14px">
                  <div style="display:flex;align-items:center;gap:10px;padding:12px;background:var(--status-success-bg);border-radius:var(--radius-sm);border:1px solid rgba(15,191,127,0.2)">
                    <i class="fas fa-circle-check" style="color:var(--status-success)"></i>
                    <span style="font-size:0.85rem;font-weight:500;color:var(--status-success)">Certificado activo</span>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Vencimiento del certificado</label>
                    <input type="text" class="form-input" value="14/06/2026" readonly style="background:var(--bg-app);cursor:default">
                  </div>
                  <button class="btn btn-secondary" onclick="showToast('Función disponible próximamente', 'info')">
                    <i class="fas fa-upload"></i> Renovar certificado
                  </button>
                </div>
              </div>
            </div>

            <div class="card" style="border-color:rgba(239,68,68,0.2)">
              <div class="card-header" style="background:var(--status-error-bg)">
                <h3 style="color:var(--status-error)">
                  <i class="fas fa-triangle-exclamation" style="margin-right:8px"></i>Zona peligrosa
                </h3>
              </div>
              <div class="card-body">
                <p style="font-size:0.85rem;margin-bottom:14px">Estas acciones son permanentes e irreversibles.</p>
                <button class="btn btn-danger btn-full"
                  onclick="if(confirm('¿Seguro? Esta acción es irreversible')) showToast('Datos borrados', 'error')">
                  <i class="fas fa-trash"></i> Borrar historial local
                </button>
              </div>
            </div>

          </div>

        </div>

      </div>
    </main>
  </div>

  <div class="toast-container"></div>
  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/productos.js"></script>
  <script src="../assets/js/configuracion.js"></script>
</body>
</html>
