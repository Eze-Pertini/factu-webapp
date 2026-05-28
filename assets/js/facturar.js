/* ==============================================
   facturar.js — Módulo de generación de facturas
   Factu
   Maneja: factura desde pagos MP + factura manual
   ============================================== */

const fmt = {
  moneda: (n) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', minimumFractionDigits: 2
  }).format(n),
  fecha: (str) => {
    if (!str) return '—';
    const parte = (str.includes('T') ? str.split('T')[0] : str.split(' ')[0]);
    const [y, m, d] = parte.split('-');
    return `${d}/${m}/${y}`;
  }
};

/* ── ESTADO ── */
const FacturarState = {
  pagosSeleccionados: [],
  cargando: false
};

/* ── TABS ── */
function initTabs() {
  const tabs    = document.querySelectorAll('.facturar-tab');
  const panels  = document.querySelectorAll('.facturar-panel');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t   => t.classList.remove('active'));
      panels.forEach(p => p.classList.add('hidden'));
      tab.classList.add('active');
      const target = document.getElementById(tab.dataset.target);
      if (target) target.classList.remove('hidden');
    });
  });
}

/* ══════════════════════════════════════════════
   TAB 1 — FACTURA DESDE PAGOS MP
   ══════════════════════════════════════════════ */

async function cargarPagosDesdeStorage() {
  const raw = sessionStorage.getItem('pagosSeleccionados');

  if (!raw) { mostrarSinSeleccion(); return; }

  let seleccionados;
  try { seleccionados = JSON.parse(raw); } catch(e) { mostrarSinSeleccion(); return; }

  if (!Array.isArray(seleccionados) || seleccionados.length === 0) {
    mostrarSinSeleccion(); return;
  }

  // Si son objetos completos (vienen de MP via pagos.js), usarlos directamente
  if (seleccionados[0] && typeof seleccionados[0] === 'object' && seleccionados[0].monto) {
    FacturarState.pagosSeleccionados = seleccionados.filter(p => p && parseFloat(p.monto) > 0);
  } else {
    // Fallback: son IDs de la DB local
    try {
      const res  = await fetch('/mini-facturante/api/pagos.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje);
      FacturarState.pagosSeleccionados = data.datos.filter(p =>
        seleccionados.includes(p.id) && p.estado === 'pendiente'
      );
    } catch(err) { mostrarSinSeleccion(); return; }
  }

  if (FacturarState.pagosSeleccionados.length === 0) { mostrarSinSeleccion(); return; }

  renderizarResumen();
}

function renderizarResumen() {
  const lista   = document.getElementById('resumen-lista');
  const totalEl = document.getElementById('resumen-total');
  const cantEl  = document.getElementById('resumen-cantidad');
  const btnGen  = document.getElementById('btn-generar');

  const pagos = FacturarState.pagosSeleccionados;
  const total = pagos.reduce((sum, p) => sum + parseFloat(p.monto), 0);

  if (cantEl)  cantEl.textContent  = pagos.length;
  if (totalEl) totalEl.textContent = fmt.moneda(total);
  if (btnGen)  btnGen.disabled     = false;

  if (!lista) return;

  lista.innerHTML = pagos.map(p => `
    <div style="display:flex;justify-content:space-between;align-items:center;
                padding:10px 0;border-bottom:1px solid var(--border-color)">
      <div>
        <div style="font-size:0.85rem;font-weight:500;color:var(--text-primary)">
          ${htmlEscF(p.email_cliente)}
        </div>
        <div style="font-size:0.78rem;color:var(--text-muted)">
          ${htmlEscF(p.detalle)} · ${fmt.fecha(p.fecha?.split(' ')[0] || p.fecha)}
        </div>
      </div>
      <div style="font-weight:600;color:var(--brand-primary);font-size:0.9rem;white-space:nowrap;margin-left:12px">
        ${fmt.moneda(p.monto)}
      </div>
    </div>
  `).join('');
}

function mostrarSinSeleccion() {
  const lista   = document.getElementById('resumen-lista');
  const totalEl = document.getElementById('resumen-total');
  const cantEl  = document.getElementById('resumen-cantidad');
  const btnGen  = document.getElementById('btn-generar');

  if (cantEl)  cantEl.textContent  = '0';
  if (totalEl) totalEl.textContent = fmt.moneda(0);
  if (btnGen)  btnGen.disabled     = true;

  if (lista) {
    lista.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-hand-pointer"></i>
        <p>No hay pagos seleccionados.<br>
          <a href="importar.php" style="color:var(--brand-primary)">
            Volvé a importar pagos
          </a> y seleccioná los que querés facturar.
        </p>
      </div>`;
  }
}

function configurarBotonGenerar() {
  const btnGenerar = document.getElementById('btn-generar');
  if (!btnGenerar || btnGenerar.dataset.listenerRegistrado) return;
  btnGenerar.dataset.listenerRegistrado = 'true'; // guard

  btnGenerar.addEventListener('click', async () => {
    if (FacturarState.cargando) return;

    const concepto      = document.getElementById('select-concepto')?.value;
    const tipo          = document.getElementById('select-tipo')?.value;
    const fechaServicio = document.getElementById('fecha-servicio')?.value;
    const fechaCobro    = document.getElementById('fecha-cobro')?.value;
    const selectProd    = document.getElementById('select-producto');
    const productoId    = selectProd?.value || '';
    const nombreProd    = productoId
      ? (selectProd?.selectedOptions[0]?.dataset.nombre || '')
      : '';

    if (!concepto) {
      showToast('Seleccioná un concepto', 'warning');
      document.getElementById('select-concepto')?.focus();
      return;
    }

    if (!fechaServicio || !fechaCobro) {
      showToast('Completá las fechas requeridas', 'warning');
      return;
    }

    if (FacturarState.pagosSeleccionados.length === 0) {
      showToast('No hay pagos seleccionados. Usá la carga manual.', 'warning');
      return;
    }

    const cantidad = FacturarState.pagosSeleccionados.length;
    const total    = FacturarState.pagosSeleccionados.reduce((s, p) => s + parseFloat(p.monto), 0);

    if (!confirm(`¿Confirmás la generación de ${cantidad} comprobante(s) por un total de ${fmt.moneda(total)}?\n\nSe generará una factura individual por cada pago seleccionado.\nEsta acción es irreversible.`)) return;

    FacturarState.cargando = true;
    btnGenerar.disabled    = true;
    btnGenerar.innerHTML   = `<span class="spinner"></span> Generando ${cantidad} comprobante(s)...`;

    try {
      const res  = await fetch('/mini-facturante/api/facturas.php', {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          pagos_mp:       FacturarState.pagosSeleccionados, // objetos completos de MP
          tipo:           mapearTipo(tipo),
          concepto,
          producto:       nombreProd,
          fecha_servicio: fechaServicio,
          fecha_cobro:    fechaCobro,
        })
      });

      const data = await res.json();

      if (data.ok) {
        sessionStorage.removeItem('pagosSeleccionados');
        const conCae = data.con_cae ?? data.facturas.length;
        const total  = data.facturas.length;
        const msg    = conCae === total
          ? `✓ ${total} comprobante(s) con CAE emitido en ARCA`
          : `✓ ${conCae}/${total} con CAE. Revisá el historial para ver los errores.`;
        showToast(msg, conCae > 0 ? 'success' : 'warning', 7000);
        setTimeout(() => window.location.href = 'historial.php', 2500);
      } else {
        showToast(data.mensaje, 'error');
        btnGenerar.disabled  = false;
        btnGenerar.innerHTML = '<i class="fas fa-file-invoice"></i> Generar comprobantes';
      }
    } catch (err) {
      showToast('Error de conexión.', 'error');
      btnGenerar.disabled  = false;
      btnGenerar.innerHTML = '<i class="fas fa-file-invoice"></i> Generar comprobantes';
    } finally {
      FacturarState.cargando = false;
    }
  });
}


/* ══════════════════════════════════════════════
   TAB 2 — FACTURA MANUAL
   ══════════════════════════════════════════════ */

function configurarFacturaManual() {
  const btnManual = document.getElementById('btn-generar-manual');
  if (!btnManual || btnManual.dataset.listenerRegistrado) return;
  btnManual.dataset.listenerRegistrado = 'true'; // guard: evita registrar dos veces

  // Autocompletar precio si seleccionan un producto
  const selectProdManual = document.getElementById('manual-producto');
  if (selectProdManual) {
    selectProdManual.addEventListener('change', () => {
      const precio = selectProdManual.selectedOptions[0]?.dataset.precio;
      const montoInput = document.getElementById('manual-monto');
      if (precio && montoInput && !montoInput.value) {
        montoInput.value = precio;
      }
    });
  }

  btnManual.addEventListener('click', async () => {
    // Leer campos
    const email        = document.getElementById('manual-email')?.value?.trim();
    const razonSocial  = document.getElementById('manual-razon')?.value?.trim() || '';
    const dniCuit      = document.getElementById('manual-dni')?.value?.trim() || '';
    const detalle      = document.getElementById('manual-detalle')?.value?.trim() || '';
    const montoRaw     = document.getElementById('manual-monto')?.value?.trim();
    const formaPago    = document.getElementById('manual-forma-pago')?.value || '';
    const concepto     = document.getElementById('manual-concepto')?.value;
    const tipo         = document.getElementById('manual-tipo')?.value;
    const fechaServ    = document.getElementById('manual-fecha-servicio')?.value;
    const fechaCobro   = document.getElementById('manual-fecha-cobro')?.value;
    const selectProd   = document.getElementById('manual-producto');
    const nombreProd   = selectProd?.value
      ? (selectProd?.selectedOptions[0]?.dataset.nombre || '')
      : '';

    // Validaciones
    if (!email) {
      showToast('El email del cliente es obligatorio', 'warning');
      document.getElementById('manual-email')?.focus();
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showToast('El email no tiene un formato válido', 'warning');
      return;
    }

    const monto = parseFloat(montoRaw);
    if (!montoRaw || isNaN(monto) || monto <= 0) {
      showToast('Ingresá un monto válido mayor a cero', 'warning');
      document.getElementById('manual-monto')?.focus();
      return;
    }

    if (!concepto) {
      showToast('Seleccioná un concepto', 'warning');
      document.getElementById('manual-concepto')?.focus();
      return;
    }

    if (!fechaServ || !fechaCobro) {
      showToast('Completá las fechas requeridas', 'warning');
      return;
    }

    if (!confirm(`¿Confirmás la generación de 1 comprobante por ${fmt.moneda(monto)} para ${email}?\n\nEsta acción es irreversible.`)) return;

    btnManual.disabled = true;
    btnManual.innerHTML = '<span class="spinner"></span> Generando...';

    try {
      const res = await fetch('/mini-facturante/api/facturas.php', {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          manual:         true,
          email_cliente:  email,
          razon_social:   razonSocial,
          dni_cuit:       dniCuit,
          detalle:        detalle || nombreProd || concepto,
          forma_pago:     formaPago,
          monto,
          tipo:           mapearTipo(tipo),
          concepto,
          producto:       nombreProd,
          fecha_servicio: fechaServ,
          fecha_cobro:    fechaCobro,
        })
      });

      const data = await res.json();

      if (data.ok) {
        const conCae = data.con_cae ?? data.facturas.length;
        const msg    = conCae > 0
          ? `✓ Comprobante generado con CAE en ARCA`
          : `✓ Comprobante generado (sin CAE — verificar en historial)`;
        showToast(msg, conCae > 0 ? 'success' : 'warning', 6000);
        limpiarFormularioManual();
        setTimeout(() => window.location.href = 'historial.php', 2500);
      } else {
        showToast(data.mensaje, 'error');
      }
    } catch (err) {
      showToast('Error de conexión.', 'error');
    } finally {
      btnManual.disabled = false;
      btnManual.innerHTML = '<i class="fas fa-file-invoice"></i> Generar comprobante';
    }
  });
}

function limpiarFormularioManual() {
  ['manual-email','manual-razon','manual-dni','manual-detalle',
   'manual-monto','manual-forma-pago'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  const sel = document.getElementById('manual-concepto');
  if (sel) sel.value = '';
}


/* ── HELPERS ── */
function mapearTipo(valor) {
  const mapa = { 'factura_c': 'Factura C', 'nota_credito': 'Nota de crédito C' };
  return mapa[valor] || 'Factura C';
}

function htmlEscF(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── INIT ── */
function initFacturar() {
  if (!document.getElementById('facturar-page')) return;
  if (window._facturarIniciado) return; // guard global
  window._facturarIniciado = true;

  initTabs();
  cargarPagosDesdeStorage();
  configurarBotonGenerar();
  configurarFacturaManual();
}

document.addEventListener('DOMContentLoaded', initFacturar);
