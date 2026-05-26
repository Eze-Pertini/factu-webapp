/* ==============================================
   productos.js — Módulo de productos/servicios
   Factu
   ============================================== */

const fmt_prod = {
  moneda: (n) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', minimumFractionDigits: 2
  }).format(n)
};

/* ══════════════════════════════════════════════
   CONFIGURACIÓN — gestión de productos
   ══════════════════════════════════════════════ */

function initProductosConfig() {
  if (!document.getElementById('config-page')) return;
  if (window._productosConfigIniciado) return; // evita doble registro de listeners
  window._productosConfigIniciado = true;

  // Cargar lista inicial
  cargarProductosConfig();

  // Registrar listener del botón una sola vez
  const btnAgregar = document.getElementById('btn-agregar-producto');
  if (btnAgregar) {
    btnAgregar.dataset.listenerRegistrado = 'true';
    btnAgregar.addEventListener('click', agregarProducto);
  }
}

async function cargarProductosConfig() {
  const lista = document.getElementById('lista-productos');
  if (!lista) return;

  lista.innerHTML = '<p class="text-muted" style="font-size:0.85rem">Cargando...</p>';

  try {
    const res  = await fetch('/mini-facturante/api/productos.php', { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.ok) throw new Error(data.mensaje);
    renderizarListaProductos(data.datos);
  } catch (err) {
    const lista = document.getElementById('lista-productos');
    if (lista) lista.innerHTML = `<p style="font-size:0.85rem;color:var(--status-error)">Error al cargar: ${err.message}</p>`;
  }
}

function renderizarListaProductos(productos) {
  const lista = document.getElementById('lista-productos');
  if (!lista) return;

  if (productos.length === 0) {
    lista.innerHTML = '<p class="text-muted" style="font-size:0.875rem;padding:8px 0">No hay productos cargados aún.</p>';
    return;
  }

  lista.innerHTML = productos.map(prod => `
    <div class="product-item" id="prod-${prod.id}">
      <div>
        <div class="product-name">${htmlEscP(prod.nombre)}</div>
        <div class="text-muted" style="font-size:0.78rem">ID: ${prod.id}</div>
      </div>
      <div style="display:flex;gap:12px;align-items:center">
        <span class="product-price">${fmt_prod.moneda(prod.precio)}</span>
        <button class="btn btn-danger btn-sm" onclick="eliminarProducto(${prod.id}, '${htmlEscP(prod.nombre)}')">
          <i class="fas fa-trash"></i>
        </button>
      </div>
    </div>
  `).join('');
}

async function agregarProducto() {
  const nombreInput = document.getElementById('nuevo-producto');
  const precioInput = document.getElementById('nuevo-precio');
  const btnAgregar  = document.getElementById('btn-agregar-producto');

  const nombre     = nombreInput?.value?.trim() || '';
  const precioRaw  = precioInput?.value?.trim() || '';
  // ── FIX: validar NaN explícitamente ──
  const precio     = precioRaw !== '' ? parseFloat(precioRaw) : 0;

  if (!nombre) {
    showToast('Ingresá el nombre del producto', 'warning');
    nombreInput?.focus();
    return;
  }

  if (precioRaw !== '' && (isNaN(precio) || precio < 0)) {
    showToast('El precio debe ser un número válido mayor o igual a cero', 'warning');
    precioInput?.focus();
    return;
  }

  btnAgregar.disabled = true;
  btnAgregar.innerHTML = '<span class="spinner"></span> Guardando...';

  try {
    const res = await fetch('/mini-facturante/api/productos.php', {
      method:      'POST',
      headers:     { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body:        JSON.stringify({ nombre, precio })
    });

    const data = await res.json();
    if (!data.ok) throw new Error(data.mensaje);

    showToast(`"${nombre}" agregado correctamente`, 'success');
    if (nombreInput) nombreInput.value = '';
    if (precioInput) precioInput.value = '';

    // Recargar la lista desde la API para confirmar que se guardó
    await cargarProductosConfig();

  } catch (err) {
    showToast(err.message || 'Error al agregar el producto', 'error');
  } finally {
    btnAgregar.disabled = false;
    btnAgregar.innerHTML = '<i class="fas fa-plus"></i> Agregar producto';
  }
}

async function eliminarProducto(id, nombre) {
  if (!confirm(`¿Eliminar "${nombre}"?\nLas facturas existentes no se verán afectadas.`)) return;

  try {
    const res  = await fetch(`/mini-facturante/api/productos.php?id=${id}`, {
      method: 'DELETE', credentials: 'same-origin'
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.mensaje);

    const el = document.getElementById(`prod-${id}`);
    if (el) {
      el.style.transition = 'opacity 0.3s';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 300);
    }
    showToast(`"${nombre}" eliminado`, 'info');
  } catch (err) {
    showToast(err.message || 'Error al eliminar', 'error');
  }
}


/* ══════════════════════════════════════════════
   FACTURAR — dropdown de productos
   ══════════════════════════════════════════════ */

async function cargarProductosDropdown() {
  // Pobla AMBOS selects: el de factura desde pagos y el de factura manual
  const selects = [
    document.getElementById('select-producto'),
    document.getElementById('manual-producto')
  ].filter(Boolean);

  if (selects.length === 0) return;

  selects.forEach(s => {
    s.innerHTML = '<option value="">Cargando...</option>';
    s.disabled  = true;
  });

  try {
    const res  = await fetch('/mini-facturante/api/productos.php', { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.ok) throw new Error(data.mensaje);

    const opcionBase = '<option value="">— Sin especificar —</option>';
    const opciones   = data.datos.map(prod => `
      <option value="${prod.id}" data-nombre="${htmlEscP(prod.nombre)}" data-precio="${prod.precio}">
        ${htmlEscP(prod.nombre)} — ${fmt_prod.moneda(prod.precio)}
      </option>
    `).join('');

    selects.forEach(s => {
      s.innerHTML = opcionBase + opciones;
      s.disabled  = false;
    });

  } catch (err) {
    selects.forEach(s => {
      s.innerHTML = '<option value="">Error al cargar</option>';
      s.disabled  = false;
    });
    showToast('No se pudieron cargar los productos', 'error');
  }
}


/* ── HELPER XSS ── */
function htmlEscP(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', () => {
  initProductosConfig();
  if (document.getElementById('facturar-page')) {
    cargarProductosDropdown();
  }
});
