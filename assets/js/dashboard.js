/* ==============================================
   dashboard.js — Módulo del dashboard
   Factu
   Consume /api/dashboard.php con datos reales
   ============================================== */

const fmtD = {
  moneda: (n) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', minimumFractionDigits: 2
  }).format(n || 0),

  fecha: (str) => {
    if (!str) return '—';
    const parte = str.includes('T') ? str.split('T')[0] : str.split(' ')[0];
    const [y, m, d] = parte.split('-');
    return `${d}/${m}/${y}`;
  }
};

/* ── INICIALIZAR ── */
function initDashboardReal() {
  if (!document.getElementById('dashboard-page')) return;
  if (window._dashboardIniciado) return;
  window._dashboardIniciado = true;

  cargarDashboard();
}

/* ── CARGAR DATOS DESDE LA API ── */
async function cargarDashboard() {
  try {
    const res  = await fetch('/mini-facturante/api/dashboard.php', {
      credentials: 'same-origin'
    });
    const data = await res.json();

    if (!data.ok) throw new Error(data.mensaje);

    renderMetricas(data.metricas);
    renderUltimasOps(data.ultimas_ops);
    renderActividad(data.actividad);
    renderAlertas(data.alertas);

  } catch (err) {
    console.error('Error cargando dashboard:', err);
    // Mostrar guiones en métricas si falla
    ['metric-hoy','metric-mes','metric-facturas','metric-pendientes'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.textContent = '—';
    });
  }
}

/* ── MÉTRICAS ── */
function renderMetricas(m) {
  setElTxt('metric-hoy',       fmtD.moneda(m.total_hoy));
  setElTxt('metric-mes',       fmtD.moneda(m.total_mes));
  setElTxt('metric-facturas',  m.cant_facturas);
  setElTxt('metric-pendientes',m.pendientes);

  // Actualizar label del mes dinámico
  const mesLabel = document.getElementById('metric-mes-label');
  if (mesLabel) mesLabel.textContent = m.mes_label;

  // Alerta visual si hay pendientes
  const cardPend = document.getElementById('metric-pendientes')?.closest('.metric-card');
  if (cardPend && m.pendientes > 0) {
    cardPend.style.cursor = 'pointer';
    cardPend.title = 'Ir a importar pagos';
    cardPend.addEventListener('click', () => {
      window.location.href = 'importar.php';
    });
  }
}

/* ── ÚLTIMAS OPERACIONES ── */
function renderUltimasOps(ops) {
  const tbody = document.getElementById('tbody-ultimas');
  if (!tbody) return;

  if (!ops || ops.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4">
          <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No hay operaciones aún. <a href="importar.php" style="color:var(--brand-primary)">Importá tus pagos</a></p>
          </div>
        </td>
      </tr>`;
    return;
  }

  tbody.innerHTML = ops.map(op => `
    <tr>
      <td>${fmtD.fecha(op.fecha)}</td>
      <td>
        <div class="truncate" style="max-width:180px" title="${htmlEscD(op.email_cliente)}">
          ${htmlEscD(op.email_cliente)}
        </div>
      </td>
      <td class="font-mono" style="font-weight:600">${fmtD.moneda(op.monto)}</td>
      <td>
        <span class="badge ${op.estado === 'facturado' ? 'badge-success' : 'badge-warning'}">
          ${op.estado === 'facturado' ? 'Facturado' : 'Pendiente'}
        </span>
      </td>
    </tr>
  `).join('');
}

/* ── ACTIVIDAD RECIENTE ── */
function renderActividad(actividad) {
  const container = document.getElementById('actividad-container');
  if (!container) return;

  if (!actividad || actividad.length === 0) {
    container.innerHTML = `
      <div class="empty-state" style="padding:20px">
        <i class="fas fa-history"></i>
        <p>Sin actividad reciente</p>
      </div>`;
    return;
  }

  const colores = ['var(--brand-accent)', 'var(--brand-primary)', 'var(--status-warning)', 'var(--status-info)', 'var(--brand-accent)'];

  container.innerHTML = actividad.map((item, i) => {
    const origen = item.origen === 'manual' ? 'Carga manual' : 'Mercado Pago';
    return `
      <div class="activity-item">
        <div class="activity-dot" style="background:${colores[i % colores.length]}"></div>
        <div>
          <div class="activity-text">
            <strong>${htmlEscD(item.numero)}</strong> — ${fmtD.moneda(item.monto_total)}
          </div>
          <div class="activity-time">
            ${fmtD.fecha(item.fecha_emision)} · ${htmlEscD(item.tipo)} · ${origen}
          </div>
        </div>
      </div>
    `;
  }).join('');
}

/* ── ALERTAS ── */
function renderAlertas(alertas) {
  const container = document.getElementById('alertas-container');
  if (!container) return;

  if (!alertas || alertas.length === 0) {
    container.innerHTML = `
      <div class="alert-item">
        <div class="alert-icon success"><i class="fas fa-check"></i></div>
        <div><div class="alert-text">Todo al día, sin alertas pendientes</div></div>
      </div>`;
    return;
  }

  container.innerHTML = alertas.map(a => `
    <div class="alert-item">
      <div class="alert-icon ${a.tipo}"><i class="fas ${a.icono}"></i></div>
      <div>
        <div class="alert-text">${a.texto}</div>
        ${a.tiempo ? `<div class="alert-time">${htmlEscD(a.tiempo)}</div>` : ''}
      </div>
    </div>
  `).join('');
}

/* ── HELPERS ── */
function setElTxt(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

function htmlEscD(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', initDashboardReal);
