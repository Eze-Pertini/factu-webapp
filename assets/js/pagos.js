/* ==============================================
   pagos.js — Módulo de importación de pagos
   Factu
   Consume /api/pagos.php y renderiza la tabla
   ============================================== */

/* ── ESTADO LOCAL DEL MÓDULO ── */
const PagosState = {
  pagos: [],              // Datos cargados desde la API
  seleccionados: new Set() // IDs seleccionados (número de fila)
};

/* ── FORMATEADORES (reutiliza los de app.js si existen) ── */
const fmt = {
  moneda: (n) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', minimumFractionDigits: 2
  }).format(n),

  fecha: (str) => {
    if (!str) return '—';
    // str viene como "2025-06-14 10:23:00" desde MySQL
    const [fecha] = str.split(' ');
    const [y, m, d] = fecha.split('-');
    return `${d}/${m}/${y}`;
  }
};

/* ── INICIALIZAR MÓDULO ── */
function initPagos() {
  // Solo ejecutar en la página de importar
  if (!document.getElementById('importar-page')) return;

  // Cargar pagos al entrar a la página
  cargarPagos();

  // Botón "Importar" → recargar con filtros
  const btnImportar = document.getElementById('btn-importar');
  if (btnImportar) {
    btnImportar.addEventListener('click', () => cargarPagos());
  }

  // Checkbox "Seleccionar todo"
  const selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.addEventListener('change', (e) => {
      toggleSeleccionTodo(e.target.checked);
    });
  }
}

/* ── CARGAR PAGOS DESDE MERCADO PAGO ── */
async function cargarPagos() {
  const btnImportar = document.getElementById('btn-importar');
  const tbody       = document.getElementById('tbody-operaciones');

  // Estado de carga
  if (btnImportar) {
    btnImportar.disabled = true;
    btnImportar.innerHTML = '<span class="spinner"></span> Consultando Mercado Pago...';
  }

  if (tbody) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted)">
          <span class="spinner"></span> Conectando con Mercado Pago...
        </td>
      </tr>`;
  }

  // Leer filtros de fecha
  const desde = document.getElementById('filtro-desde')?.value || '';
  const hasta = document.getElementById('filtro-hasta')?.value || '';

  // Construir URL apuntando a MP
  const params = new URLSearchParams();
  if (desde) params.append('desde', desde);
  if (hasta) params.append('hasta', hasta);

  const url = `/mini-facturante/api/mp_pagos.php?${params.toString()}`;

  try {
    const res  = await fetch(url, { credentials: 'same-origin' });
    const data = await res.json();

    if (!data.ok) {
      throw new Error(data.mensaje || 'Error desconocido');
    }

    // Guardar en estado y renderizar
    PagosState.pagos = data.datos;
    PagosState.seleccionados.clear();

    renderizarTabla(PagosState.pagos);
    actualizarBarraSeleccion();

    // Mostrar toast con resultado
    const nuevos = data.datos.filter(p => !p.ya_importado).length;
    const yaImportados = data.meta?.ya_importados || 0;
    let msg = `${data.meta.total} pago(s) de Mercado Pago`;
    if (yaImportados > 0) msg += ` · ${yaImportados} ya facturado(s)`;
    showToast(msg, 'success');

  } catch (err) {
    console.error('Error cargando pagos:', err);

    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <i class="fas fa-exclamation-circle" style="color:var(--status-error)"></i>
              <p>No se pudieron cargar los pagos. Revisá tu conexión.</p>
            </div>
          </td>
        </tr>`;
    }

    showToast('Error al cargar pagos: ' + err.message, 'error');

  } finally {
    // Restaurar botón siempre
    if (btnImportar) {
      btnImportar.disabled = false;
      btnImportar.innerHTML = '<i class="fas fa-download"></i> Importar';
    }
  }
}

/* ── RENDERIZAR TABLA ── */
function renderizarTabla(pagos) {
  const tbody = document.getElementById('tbody-operaciones');
  if (!tbody) return;

  if (pagos.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No se encontraron operaciones para el período seleccionado</p>
          </div>
        </td>
      </tr>`;
    return;
  }

  tbody.innerHTML = pagos.map((pago, index) => {
    // MP usa 'ya_importado', la DB local usa 'estado'
    const yaFacturado = pago.ya_importado === true || pago.estado === 'facturado';
    const seleccionado = PagosState.seleccionados.has(index);
    // Usar mp_id si viene de MP, sino usar id
    const pagoId = pago.id || pago.mp_id;

    return `
      <tr data-index="${index}" class="${seleccionado ? 'selected' : ''} ${yaFacturado ? 'row-facturada' : ''}">
        <td>
          <input
            type="checkbox"
            class="checkbox-custom row-checkbox"
            data-index="${index}"
            ${seleccionado ? 'checked' : ''}
            ${yaFacturado ? 'disabled title="Ya facturado"' : ''}
          >
        </td>
        <td>${fmt.fecha(pago.fecha)}</td>
        <td class="font-mono text-muted">${htmlEscape(String(pagoId))}</td>
        <td>
          <div class="truncate" style="max-width:160px" title="${htmlEscape(pago.email_cliente)}">
            ${htmlEscape(pago.email_cliente)}
          </div>
        </td>
        <td>
          <div class="truncate" style="max-width:180px" title="${htmlEscape(pago.detalle)}">
            ${htmlEscape(pago.detalle)}
          </div>
        </td>
        <td>
          <span class="badge badge-info">${htmlEscape(pago.tipo)}</span>
        </td>
        <td class="font-mono" style="text-align:right; font-weight:600">
          ${fmt.moneda(pago.monto)}
        </td>
      </tr>
    `;
  }).join('');

  // Agregar eventos a los checkboxes recién renderizados
  tbody.querySelectorAll('.row-checkbox:not(:disabled)').forEach(cb => {
    cb.addEventListener('change', (e) => {
      const index = parseInt(e.target.dataset.index);
      const fila  = e.target.closest('tr');

      if (e.target.checked) {
        PagosState.seleccionados.add(index);
        fila.classList.add('selected');
      } else {
        PagosState.seleccionados.delete(index);
        fila.classList.remove('selected');
      }

      actualizarBarraSeleccion();
      actualizarCheckboxTodo();
    });
  });
}

/* ── SELECCIONAR TODO ── */
function toggleSeleccionTodo(activar) {
  const checkboxes = document.querySelectorAll('.row-checkbox:not(:disabled)');

  checkboxes.forEach(cb => {
    cb.checked = activar;
    const index = parseInt(cb.dataset.index);
    const fila  = cb.closest('tr');

    if (activar) {
      PagosState.seleccionados.add(index);
      fila.classList.add('selected');
    } else {
      PagosState.seleccionados.delete(index);
      fila.classList.remove('selected');
    }
  });

  actualizarBarraSeleccion();
}

/* ── SINCRONIZAR CHECKBOX "SELECCIONAR TODO" ── */
function actualizarCheckboxTodo() {
  const checkboxes = document.querySelectorAll('.row-checkbox:not(:disabled)');
  const marcados   = document.querySelectorAll('.row-checkbox:not(:disabled):checked');
  const selectAll  = document.getElementById('select-all');

  if (selectAll) {
    selectAll.checked       = checkboxes.length > 0 && marcados.length === checkboxes.length;
    selectAll.indeterminate = marcados.length > 0 && marcados.length < checkboxes.length;
  }
}

/* ── ACTUALIZAR BARRA DE SELECCIÓN ── */
function actualizarBarraSeleccion() {
  const bar      = document.getElementById('selection-bar');
  const countEl  = document.getElementById('selection-count');
  const totalEl  = document.getElementById('selection-total');

  if (!bar) return;

  const cantidad = PagosState.seleccionados.size;

  if (cantidad === 0) {
    bar.classList.add('hidden');
    return;
  }

  bar.classList.remove('hidden');

  if (countEl) countEl.textContent = cantidad;

  // Calcular total de los seleccionados
  const total = [...PagosState.seleccionados]
    .reduce((sum, index) => sum + parseFloat(PagosState.pagos[index]?.monto || 0), 0);

  if (totalEl) totalEl.textContent = fmt.moneda(total);

  // Guardar en sessionStorage para la página facturar.php
  // Guardar el objeto completo para que facturar.js tenga todos los datos
  const pagosSeleccionados = [...PagosState.seleccionados].map(i => ({
    id:            PagosState.pagos[i]?.id || PagosState.pagos[i]?.mp_id,
    mp_id:         PagosState.pagos[i]?.id || PagosState.pagos[i]?.mp_id,
    email_cliente: PagosState.pagos[i]?.email_cliente,
    detalle:       PagosState.pagos[i]?.detalle,
    monto:         PagosState.pagos[i]?.monto,
    fecha:         PagosState.pagos[i]?.fecha,
    tipo:          PagosState.pagos[i]?.tipo,
    origen:        'mp', // viene de Mercado Pago
  }));
  sessionStorage.setItem('pagosSeleccionados', JSON.stringify(pagosSeleccionados));
}

/* ── LIMPIAR SELECCIÓN (llamada desde importar.php) ── */
function clearSelection() {
  PagosState.seleccionados.clear();

  document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
  document.querySelectorAll('tr.selected').forEach(tr => tr.classList.remove('selected'));

  const selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.checked       = false;
    selectAll.indeterminate = false;
  }

  actualizarBarraSeleccion();
}

/* ── HELPER: escapar HTML para evitar XSS ── */
function htmlEscape(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/* ── INICIALIZAR AL CARGAR EL DOM ── */
document.addEventListener('DOMContentLoaded', () => {
  initPagos();
});
