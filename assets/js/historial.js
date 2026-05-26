/* ==============================================
   historial.js — Módulo de historial de facturas
   Factu
   Consume /api/facturas.php
   ============================================== */

const fmtH = {
  moneda: (n) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', minimumFractionDigits: 2
  }).format(n),
  fecha: (str) => {
    if (!str) return '—';
    const parte = str.includes('T') ? str.split('T')[0] : str.split(' ')[0];
    const [y, m, d] = parte.split('-');
    return `${d}/${m}/${y}`;
  }
};

/* ── INICIALIZAR ── */
function initHistorial() {
  if (!document.getElementById('historial-page')) return;

  cargarFacturas();

  // Filtro por estado
  const filtroEstado = document.getElementById('filtro-estado');
  if (filtroEstado) {
    filtroEstado.addEventListener('change', () => cargarFacturas());
  }
}

/* ── CARGAR FACTURAS DESDE LA API ── */
async function cargarFacturas() {
  const tbody = document.getElementById('tbody-historial');

  if (tbody) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted)">
          <span class="spinner"></span> Cargando facturas...
        </td>
      </tr>`;
  }

  try {
    const res  = await fetch('/mini-facturante/api/facturas.php', {
      credentials: 'same-origin'
    });
    const data = await res.json();

    if (!data.ok) throw new Error(data.mensaje);

    // Aplicar filtro local de estado
    const filtro    = document.getElementById('filtro-estado')?.value || '';
    const facturas  = filtro
      ? data.datos.filter(f => f.estado === filtro)
      : data.datos;

    // Actualizar métricas
    actualizarMetricas(data.meta, facturas);

    // Renderizar tabla
    renderizarTabla(facturas);

  } catch (err) {
    console.error('Error cargando historial:', err);

    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <i class="fas fa-exclamation-circle" style="color:var(--status-error)"></i>
              <p>No se pudo cargar el historial.</p>
            </div>
          </td>
        </tr>`;
    }
  }
}

/* ── ACTUALIZAR MÉTRICAS ── */
function actualizarMetricas(meta, facturasFiltradas) {
  setTexto('metric-total-comp',  meta.total);
  setTexto('metric-emitidas',    meta.total_emitidas);
  setTexto('metric-anuladas',    meta.total_anuladas);
  setTexto('metric-monto-total', fmtH.moneda(meta.total_monto));

  // Contador de resultados en el footer
  const contador = document.getElementById('historial-contador');
  if (contador) {
    contador.textContent = `Mostrando ${facturasFiltradas.length} de ${meta.total} comprobantes`;
  }
}

function setTexto(id, valor) {
  const el = document.getElementById(id);
  if (el) el.textContent = valor;
}

/* ── RENDERIZAR TABLA ── */
function renderizarTabla(facturas) {
  const tbody = document.getElementById('tbody-historial');
  if (!tbody) return;

  if (facturas.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>No hay facturas generadas aún.<br>
              <a href="importar.php" style="color:var(--brand-primary)">
                Importá pagos y generá tu primera factura
              </a>
            </p>
          </div>
        </td>
      </tr>`;
    return;
  }

  tbody.innerHTML = facturas.map((f, i) => `
    <tr>
      <td>${fmtH.fecha(f.fecha_emision)}</td>
      <td>
        <div style="font-size:0.85rem; font-weight:500">${htmlEscapeH(f.producto)}</div>
        <div style="font-size:0.75rem; color:var(--text-muted)">${f.cantidad_pagos} pago(s)</div>
      </td>
      <td>
        <span class="badge ${f.tipo.includes('crédito') ? 'badge-warning' : 'badge-info'}">
          ${htmlEscapeH(f.tipo)}
        </span>
      </td>
      <td class="font-mono">${htmlEscapeH(f.numero)}</td>
      <td class="font-mono" style="text-align:right; font-weight:600">
        ${fmtH.moneda(f.monto_total)}
      </td>
      <td>${badgeEstado(f.estado)}</td>
      <td>
        <div style="display:flex; gap:6px">
          <button class="btn btn-ghost btn-sm"
            onclick="verFactura(${f.id})" title="Ver detalle">
            <i class="fas fa-eye"></i>
          </button>
          <button class="btn btn-ghost btn-sm"
            onclick="descargarFactura('${htmlEscapeH(f.numero)}')" title="Descargar PDF">
            <i class="fas fa-download"></i>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

/* ── BADGE ESTADO ── */
function badgeEstado(estado) {
  const mapa = {
    emitida: 'badge-success',
    anulada: 'badge-error'
  };
  const cls   = mapa[estado] || 'badge-info';
  const texto = estado.charAt(0).toUpperCase() + estado.slice(1);
  return `<span class="badge ${cls}">${texto}</span>`;
}

/* ── ACCIONES ── */
function verFactura(id) {
  showToast(`Abriendo detalle de factura #${id}`, 'info');
  // En el futuro: abrir modal con detalle completo
}

function descargarFactura(numero) {
  showToast(`Descargando PDF: ${numero}`, 'success');
  // En el futuro: llamar a /api/facturas.php?id=X&formato=pdf
}

/* ── HELPER XSS ── */
function htmlEscapeH(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/* ── INICIALIZAR ── */
document.addEventListener('DOMContentLoaded', initHistorial);
