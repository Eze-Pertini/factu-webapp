/* ==============================================
   Factu — app.js
   Lógica frontend: navegación, datos, interacciones
   ============================================== */

/* ── ESTADO GLOBAL EN MEMORIA ── */
const AppState = {
  user: {
    name: 'Ezequiel Rodríguez',
    initials: 'ER',
    cuit: '20-35421897-3',
    razonSocial: 'Rodríguez Ezequiel',
    puntoVenta: '0001',
    email: 'eze@example.com'
  },
  selectedPayments: new Set(),
  filters: {
    desde: '',
    hasta: ''
  },
  productos: [
    { id: 1, nombre: 'Servicios de desarrollo web', precio: 85000 },
    { id: 2, nombre: 'Consultoría tecnológica', precio: 60000 },
    { id: 3, nombre: 'Diseño UX/UI', precio: 45000 },
    { id: 4, nombre: 'Soporte técnico mensual', precio: 25000 }
  ]
};

/* ── DATOS DE EJEMPLO — OPERACIONES MERCADO PAGO ── */
const mockOperaciones = [
  {
    id: 'MP-84721034',
    fecha: '2025-06-14',
    cliente: 'facundo.garcia@gmail.com',
    detalle: 'Pago servicios web - Jun 2025',
    tipo: 'Pago recibido',
    monto: 85000.00,
    estado: 'Disponible',
    facturado: false
  },
  {
    id: 'MP-84718923',
    fecha: '2025-06-13',
    cliente: 'laura.martinez@empresa.com',
    detalle: 'Consultoría tecnológica',
    tipo: 'Pago recibido',
    monto: 60000.00,
    estado: 'Disponible',
    facturado: false
  },
  {
    id: 'MP-84715201',
    fecha: '2025-06-12',
    cliente: 'roberto.perez@yahoo.com',
    detalle: 'Diseño UI/UX - Proyecto app',
    tipo: 'Pago recibido',
    monto: 45000.00,
    estado: 'Disponible',
    facturado: false
  },
  {
    id: 'MP-84710087',
    fecha: '2025-06-11',
    cliente: 'empresa@mail.com',
    detalle: 'Soporte técnico Mayo 2025',
    tipo: 'Pago recibido',
    monto: 25000.00,
    estado: 'Disponible',
    facturado: true
  },
  {
    id: 'MP-84707654',
    fecha: '2025-06-10',
    cliente: 'ana.gonzalez@startup.io',
    detalle: 'Desarrollo módulo ecommerce',
    tipo: 'Pago recibido',
    monto: 130000.00,
    estado: 'Disponible',
    facturado: false
  },
  {
    id: 'MP-84699321',
    fecha: '2025-06-08',
    cliente: 'carlos.silva@pyme.ar',
    detalle: 'Migración base de datos',
    tipo: 'Pago recibido',
    monto: 38500.00,
    estado: 'Acreditado',
    facturado: true
  }
];

/* ── DATOS DE EJEMPLO — FACTURAS GENERADAS ── */
const mockFacturas = [
  {
    fecha: '2025-06-13',
    cliente: 'roberto.perez@yahoo.com',
    tipo: 'Factura C',
    numero: '0001-00000043',
    monto: 45000.00,
    estado: 'Emitida'
  },
  {
    fecha: '2025-06-11',
    cliente: 'empresa@mail.com',
    tipo: 'Factura C',
    numero: '0001-00000042',
    monto: 25000.00,
    estado: 'Emitida'
  },
  {
    fecha: '2025-06-08',
    cliente: 'carlos.silva@pyme.ar',
    tipo: 'Factura C',
    numero: '0001-00000041',
    monto: 38500.00,
    estado: 'Emitida'
  },
  {
    fecha: '2025-06-01',
    cliente: 'maria.torres@gmail.com',
    tipo: 'Factura C',
    numero: '0001-00000040',
    monto: 72000.00,
    estado: 'Emitida'
  },
  {
    fecha: '2025-05-28',
    cliente: 'lucas.fernandez@corp.com',
    tipo: 'Nota de crédito C',
    numero: '0001-00000039',
    monto: 15000.00,
    estado: 'Anulada'
  },
  {
    fecha: '2025-05-22',
    cliente: 'empresa@mail.com',
    tipo: 'Factura C',
    numero: '0001-00000038',
    monto: 95000.00,
    estado: 'Emitida'
  }
];

/* ── UTILIDADES ── */

/**
 * Formatea un número como moneda argentina
 * @param {number} amount
 * @returns {string}
 */
function formatCurrency(amount) {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2
  }).format(amount);
}

/**
 * Formatea una fecha ISO a formato legible
 * @param {string} dateStr
 * @returns {string}
 */
function formatDate(dateStr) {
  if (!dateStr) return '—';
  const [y, m, d] = dateStr.split('-');
  return `${d}/${m}/${y}`;
}

/**
 * Genera un HTML de badge según el estado
 * @param {string} estado
 * @returns {string}
 */
function getBadgeHTML(estado) {
  const map = {
    'Disponible': 'success',
    'Acreditado': 'info',
    'Pendiente': 'warning',
    'Rechazado': 'error',
    'Emitida': 'success',
    'Anulada': 'error',
    'Pendiente de envío': 'warning'
  };
  const cls = map[estado] || 'info';
  return `<span class="badge badge-${cls}">${estado}</span>`;
}

/* ── TOAST / NOTIFICACIONES ── */

/**
 * Muestra un toast de notificación
 * @param {string} message
 * @param {string} type - success | error | warning | info
 * @param {number} duration - ms
 */
function showToast(message, type = 'info', duration = 3500) {
  const icons = {
    success: 'fa-circle-check',
    error: 'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info: 'fa-circle-info'
  };

  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <i class="fas ${icons[type]} toast-icon"></i>
    <span class="toast-message">${message}</span>
    <i class="fas fa-xmark toast-close" onclick="this.parentElement.remove()"></i>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

/* ── SIDEBAR / NAVEGACIÓN ── */

/**
 * Inicializa el sidebar: toggle mobile, overlay, nav activo
 */
function initSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const menuToggle = document.getElementById('menu-toggle');

  if (!sidebar) return;

  // Toggle en mobile
  if (menuToggle) {
    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('open');
    });
  }

  // Cerrar con overlay
  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
  }

  // Marcar nav item activo según página actual
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  const navItems = document.querySelectorAll('.nav-item[data-page]');
  navItems.forEach(item => {
    item.classList.remove('active');
    if (item.dataset.page === currentPage) {
      item.classList.add('active');
    }
  });

  // El nombre y las iniciales los maneja PHP desde sidebar.php
  // No sobreescribir con datos hardcodeados de AppState

  // Rellenar header (solo el primer nombre — ya lo hace PHP pero por si acaso)
  // const headerUserEl = document.querySelector('.header-user-name');
  // if (headerUserEl) headerUserEl.textContent = AppState.user.name.split(' ')[0];
}

/* ── DASHBOARD ── */

/**
 * Inicializa el dashboard con métricas y tablas
 */
function initDashboard() {
  if (!document.getElementById('dashboard-page')) return;

  // Calcular métricas de ejemplo
  const hoy = mockOperaciones.filter(op => op.fecha === '2025-06-14');
  const totalHoy = hoy.reduce((sum, op) => sum + op.monto, 0);
  const totalMes = mockOperaciones.reduce((sum, op) => sum + op.monto, 0);
  const totalFacturas = mockFacturas.length;
  const pendientes = mockOperaciones.filter(op => !op.facturado).length;

  // Actualizar métricas en DOM
  setTextById('metric-hoy', formatCurrency(totalHoy));
  setTextById('metric-mes', formatCurrency(totalMes));
  setTextById('metric-facturas', totalFacturas);
  setTextById('metric-pendientes', pendientes);

  // Renderizar tabla últimas operaciones
  renderUltimasOperaciones();
}

function setTextById(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

/**
 * Renderiza las últimas operaciones en el dashboard
 */
function renderUltimasOperaciones() {
  const tbody = document.getElementById('tbody-ultimas');
  if (!tbody) return;

  const recientes = mockOperaciones.slice(0, 5);

  tbody.innerHTML = recientes.map(op => `
    <tr>
      <td>${formatDate(op.fecha)}</td>
      <td>
        <div class="truncate" style="max-width:180px" title="${op.cliente}">
          ${op.cliente}
        </div>
      </td>
      <td class="font-mono">${formatCurrency(op.monto)}</td>
      <td>${getBadgeHTML(op.facturado ? 'Emitida' : 'Pendiente')}</td>
    </tr>
  `).join('');
}

/* ── IMPORTAR PAGOS ── */

/**
 * Inicializa la página de importar pagos
 */
function initImportar() {
  if (!document.getElementById('importar-page')) return;

  renderTablaOperaciones(mockOperaciones);
  updateSelectionBar();

  // Filtros
  const btnImportar = document.getElementById('btn-importar');
  if (btnImportar) {
    btnImportar.addEventListener('click', () => {
      const desde = document.getElementById('filtro-desde')?.value;
      const hasta = document.getElementById('filtro-hasta')?.value;

      // Simular carga
      btnImportar.disabled = true;
      btnImportar.innerHTML = '<span class="spinner"></span> Importando...';

      setTimeout(() => {
        btnImportar.disabled = false;
        btnImportar.innerHTML = '<i class="fas fa-download"></i> Importar';

        let filtered = mockOperaciones;
        if (desde) filtered = filtered.filter(op => op.fecha >= desde);
        if (hasta) filtered = filtered.filter(op => op.fecha <= hasta);

        renderTablaOperaciones(filtered);
        AppState.selectedPayments.clear();
        updateSelectionBar();
        showToast(`${filtered.length} operaciones importadas`, 'success');
      }, 1200);
    });
  }

  // Seleccionar todo
  const selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.addEventListener('change', (e) => {
      const checkboxes = document.querySelectorAll('.row-checkbox');
      checkboxes.forEach(cb => {
        cb.checked = e.target.checked;
        const id = cb.dataset.id;
        if (e.target.checked) {
          AppState.selectedPayments.add(id);
          cb.closest('tr').classList.add('selected');
        } else {
          AppState.selectedPayments.delete(id);
          cb.closest('tr').classList.remove('selected');
        }
      });
      updateSelectionBar();
    });
  }

  // Botón facturar seleccionadas
  const btnFacturarSel = document.getElementById('btn-facturar-sel');
  if (btnFacturarSel) {
    btnFacturarSel.addEventListener('click', () => {
      if (AppState.selectedPayments.size === 0) {
        showToast('Seleccioná al menos una operación', 'warning');
        return;
      }
      // Redirigir a facturar con estado
      sessionStorage.setItem('selectedPayments', JSON.stringify([...AppState.selectedPayments]));
      window.location.href = 'facturar.html';
    });
  }
}

/**
 * Renderiza la tabla de operaciones de MP
 * @param {Array} operaciones
 */
function renderTablaOperaciones(operaciones) {
  const tbody = document.getElementById('tbody-operaciones');
  if (!tbody) return;

  if (operaciones.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No se encontraron operaciones para los filtros seleccionados</p>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = operaciones.map(op => `
    <tr data-id="${op.id}" class="${AppState.selectedPayments.has(op.id) ? 'selected' : ''}">
      <td>
        <input type="checkbox"
          class="checkbox-custom row-checkbox"
          data-id="${op.id}"
          ${AppState.selectedPayments.has(op.id) ? 'checked' : ''}
          ${op.facturado ? 'disabled title="Ya facturado"' : ''}
        >
      </td>
      <td>${formatDate(op.fecha)}</td>
      <td class="font-mono text-muted">${op.id}</td>
      <td>
        <div class="truncate" style="max-width:160px" title="${op.cliente}">
          ${op.cliente}
        </div>
      </td>
      <td>
        <div class="truncate" style="max-width:180px" title="${op.detalle}">
          ${op.detalle}
        </div>
      </td>
      <td>
        <span class="badge badge-info">${op.tipo}</span>
      </td>
      <td class="font-mono" style="text-align:right; font-weight:600">
        ${formatCurrency(op.monto)}
      </td>
    </tr>
  `).join('');

  // Event listeners en checkboxes
  document.querySelectorAll('.row-checkbox:not(:disabled)').forEach(cb => {
    cb.addEventListener('change', (e) => {
      const id = e.target.dataset.id;
      const row = e.target.closest('tr');
      if (e.target.checked) {
        AppState.selectedPayments.add(id);
        row.classList.add('selected');
      } else {
        AppState.selectedPayments.delete(id);
        row.classList.remove('selected');
      }
      updateSelectionBar();
    });
  });
}

/**
 * Actualiza la barra de selección
 */
function updateSelectionBar() {
  const bar = document.getElementById('selection-bar');
  const countEl = document.getElementById('selection-count');
  const totalEl = document.getElementById('selection-total');

  if (!bar) return;

  const count = AppState.selectedPayments.size;

  if (count === 0) {
    bar.classList.add('hidden');
    return;
  }

  bar.classList.remove('hidden');

  if (countEl) countEl.textContent = count;

  // Calcular total seleccionado
  const total = mockOperaciones
    .filter(op => AppState.selectedPayments.has(op.id))
    .reduce((sum, op) => sum + op.monto, 0);

  if (totalEl) totalEl.textContent = formatCurrency(total);
}

/* ── GENERAR FACTURAS ── */

/**
 * Inicializa la página de facturar
 */
function initFacturar() {
  if (!document.getElementById('facturar-page')) return;

  // Cargar operaciones seleccionadas desde sessionStorage
  const selected = JSON.parse(sessionStorage.getItem('selectedPayments') || '[]');

  // Poblar dropdown de productos
  const selectProducto = document.getElementById('select-producto');
  if (selectProducto) {
    AppState.productos.forEach(prod => {
      const opt = document.createElement('option');
      opt.value = prod.id;
      opt.textContent = `${prod.nombre} — ${formatCurrency(prod.precio)}`;
      selectProducto.appendChild(opt);
    });
  }

  // Calcular resumen
  let operacionesAFacturar;
  if (selected.length > 0) {
    operacionesAFacturar = mockOperaciones.filter(op => selected.includes(op.id));
  } else {
    // Si no hay selección, mostrar las no facturadas
    operacionesAFacturar = mockOperaciones.filter(op => !op.facturado);
  }

  renderResumenFacturar(operacionesAFacturar);

  // Botón generar
  const btnGenerar = document.getElementById('btn-generar');
  if (btnGenerar) {
    btnGenerar.addEventListener('click', () => {
      const producto = document.getElementById('select-producto')?.value;
      const fechaServicio = document.getElementById('fecha-servicio')?.value;
      const fechaCobro = document.getElementById('fecha-cobro')?.value;

      if (!producto) {
        showToast('Seleccioná un producto o servicio', 'warning');
        return;
      }

      if (!fechaServicio || !fechaCobro) {
        showToast('Completá las fechas requeridas', 'warning');
        return;
      }

      // Simular generación
      btnGenerar.disabled = true;
      btnGenerar.innerHTML = '<span class="spinner"></span> Generando en AFIP...';

      setTimeout(() => {
        btnGenerar.disabled = false;
        btnGenerar.innerHTML = '<i class="fas fa-file-invoice"></i> Generar comprobantes';
        showToast(
          `${operacionesAFacturar.length} comprobante(s) generados exitosamente en AFIP`,
          'success',
          5000
        );

        // Limpiar selección
        sessionStorage.removeItem('selectedPayments');
        AppState.selectedPayments.clear();

        // Redirigir al historial tras 2s
        setTimeout(() => {
          window.location.href = 'historial.html';
        }, 2000);
      }, 2500);
    });
  }
}

/**
 * Renderiza el resumen de operaciones a facturar
 * @param {Array} operaciones
 */
function renderResumenFacturar(operaciones) {
  const countEl = document.getElementById('resumen-cantidad');
  const totalEl = document.getElementById('resumen-total');
  const listaEl = document.getElementById('resumen-lista');

  const total = operaciones.reduce((sum, op) => sum + op.monto, 0);

  if (countEl) countEl.textContent = operaciones.length;
  if (totalEl) totalEl.textContent = formatCurrency(total);

  if (listaEl) {
    listaEl.innerHTML = operaciones.map(op => `
      <div class="mockup-table-row">
        <span class="mockup-row-name truncate" style="max-width:180px" title="${op.cliente}">
          ${op.cliente}
        </span>
        <span class="mockup-row-amount">${formatCurrency(op.monto)}</span>
      </div>
    `).join('') || '<p class="text-muted" style="font-size:0.85rem; padding:8px 0">Sin operaciones seleccionadas</p>';
  }
}

/* ── HISTORIAL ── */

/**
 * Inicializa la página de historial
 */
function initHistorial() {
  if (!document.getElementById('historial-page')) return;

  renderTablaHistorial(mockFacturas);

  // Filtro de búsqueda
  const filtroEstado = document.getElementById('filtro-estado');
  if (filtroEstado) {
    filtroEstado.addEventListener('change', () => {
      const val = filtroEstado.value;
      const filtered = val
        ? mockFacturas.filter(f => f.estado === val)
        : mockFacturas;
      renderTablaHistorial(filtered);
    });
  }
}

/**
 * Renderiza la tabla de facturas del historial
 * @param {Array} facturas
 */
function renderTablaHistorial(facturas) {
  const tbody = document.getElementById('tbody-historial');
  if (!tbody) return;

  if (facturas.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6">
          <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>No se encontraron facturas</p>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = facturas.map((f, i) => `
    <tr>
      <td>${formatDate(f.fecha)}</td>
      <td>
        <div class="truncate" style="max-width:160px" title="${f.cliente}">
          ${f.cliente}
        </div>
      </td>
      <td>
        <span class="badge ${f.tipo.includes('crédito') ? 'badge-warning' : 'badge-info'}">
          ${f.tipo}
        </span>
      </td>
      <td class="font-mono">${f.numero}</td>
      <td class="font-mono" style="text-align:right; font-weight:600">${formatCurrency(f.monto)}</td>
      <td>${getBadgeHTML(f.estado)}</td>
      <td>
        <div class="flex" style="gap:6px">
          <button class="btn btn-ghost btn-sm" onclick="verFactura(${i})" title="Ver factura">
            <i class="fas fa-eye"></i>
          </button>
          <button class="btn btn-ghost btn-sm" onclick="descargarFactura(${i})" title="Descargar PDF">
            <i class="fas fa-download"></i>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

/**
 * Simula la visualización de una factura
 * @param {number} index
 */
function verFactura(index) {
  const f = mockFacturas[index];
  showToast(`Abriendo ${f.numero} de ${f.cliente}`, 'info');
}

/**
 * Simula la descarga de una factura en PDF
 * @param {number} index
 */
function descargarFactura(index) {
  const f = mockFacturas[index];
  showToast(`Descargando PDF: ${f.numero}`, 'success');
}

/* ── CONFIGURACIÓN ── */

/**
 * Inicializa la página de configuración
 */
function initConfiguracion() {
  // Completamente reemplazado por productos.js
  // que maneja datos fiscales y productos con la DB real
  if (!document.getElementById('config-page')) return;

  // Solo mantener el listener de datos fiscales (no toca productos)
  const btnGuardar = document.getElementById('btn-guardar-fiscal');
  if (btnGuardar && !btnGuardar.dataset.listenerRegistrado) {
    btnGuardar.dataset.listenerRegistrado = 'true';
    btnGuardar.addEventListener('click', () => {
      const cuit = document.getElementById('config-cuit')?.value;
      const razon = document.getElementById('config-razon')?.value;

      if (!cuit || !razon) {
        showToast('Completá todos los campos requeridos', 'warning');
        return;
      }

      AppState.user.cuit = cuit;
      AppState.user.razonSocial = razon;
      AppState.user.puntoVenta = document.getElementById('config-pv')?.value || '0001';

      showToast('Datos fiscales guardados correctamente', 'success');
    });
  }
}

  // Agregar producto — manejado por productos.js
  // (El listener está en productos.js que guarda en la DB)
  // Este bloque de app.js queda deshabilitado para evitar doble registro
  /*
  const btnAgregarProducto = document.getElementById('btn-agregar-producto');
  if (btnAgregarProducto) {
    btnAgregarProducto.addEventListener('click', () => {
      const nombre = document.getElementById('nuevo-producto')?.value?.trim();
      const precioStr = document.getElementById('nuevo-precio')?.value;

      if (!nombre || !precioStr) {
        showToast('Completá nombre y precio del producto', 'warning');
        return;
      }

      const precio = parseFloat(precioStr);
      if (isNaN(precio) || precio <= 0) {
        showToast('El precio debe ser un número válido', 'error');
        return;
      }

      const newProd = {
        id: Date.now(),
        nombre,
        precio
      };

      AppState.productos.push(newProd);
      renderProductos();
      showToast(`"${nombre}" agregado correctamente`, 'success');

      // Limpiar campos
      document.getElementById('nuevo-producto').value = '';
      document.getElementById('nuevo-precio').value = '';
    });
  }
  */

/**
 * Renderiza la lista de productos en configuración
 */
function renderProductos() {
  const lista = document.getElementById('lista-productos');
  if (!lista) return;

  lista.innerHTML = AppState.productos.map((prod, i) => `
    <div class="product-item">
      <div>
        <div class="product-name">${prod.nombre}</div>
        <div class="text-muted" style="font-size:0.78rem">ID: ${prod.id}</div>
      </div>
      <div class="flex" style="gap:12px; align-items:center">
        <span class="product-price">${formatCurrency(prod.precio)}</span>
        <button class="btn btn-danger btn-sm" onclick="eliminarProducto(${i})">
          <i class="fas fa-trash"></i>
        </button>
      </div>
    </div>
  `).join('') || '<p class="text-muted" style="font-size:0.875rem; padding:8px 0">No hay productos cargados</p>';
}

/**
 * Elimina un producto de la lista
 * @param {number} index
 */
function eliminarProducto(index) {
  const prod = AppState.productos[index];
  if (confirm(`¿Eliminar "${prod.nombre}"?`)) {
    AppState.productos.splice(index, 1);
    renderProductos();
    showToast('Producto eliminado', 'info');
  }
}

/* ── LOGIN ── */

/**
 * Inicializa el formulario de login
 */
function initLogin() {
  const form = document.getElementById('login-form');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault(); // Evita que el form recargue la página
    e.preventDefault();

    const email = document.getElementById('login-email')?.value;
    const pass = document.getElementById('login-pass')?.value;
    const btn = document.getElementById('btn-login');

    if (!email || !pass) {
      showToast('Completá email y contraseña', 'warning');
      return;
    }

    // Login real → fetch al backend PHP
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Ingresando...';

    fetch('/mini-facturante/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password: pass })
    })
    .then(res => res.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Ingresar';

      if (data.ok) {
        // Login exitoso → ir al dashboard PHP
        window.location.href = '/mini-facturante/pages/dashboard.php';
      } else {
        // Mostrar el mensaje de error que devuelve el servidor
        showToast(data.mensaje, 'error');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Ingresar';
      showToast('Error de conexión. Revisá que XAMPP esté corriendo.', 'error');
    });
  });

  // Scroll suave a login
  const btnScrollLogin = document.getElementById('btn-go-login');
  if (btnScrollLogin) {
    btnScrollLogin.addEventListener('click', () => {
      document.getElementById('login-section')?.scrollIntoView({ behavior: 'smooth' });
    });
  }
}

/* ── INICIALIZACIÓN ── */
document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initLogin();
  // initDashboard()    → reemplazado por dashboard.js
  initImportar();
  initFacturar();
  initHistorial();
  // initConfiguracion() → reemplazado por configuracion.js + productos.js
});
