/* ==============================================
   configuracion.js — Módulo de configuración fiscal
   Factu
   Consume /api/configuracion.php
   ============================================== */

/* ── INICIALIZAR ── */
function initConfiguracionFiscal() {
  if (!document.getElementById('config-page')) return;
  if (window._configFiscalIniciada) return;
  window._configFiscalIniciada = true;

  cargarConfiguracion();
  configurarFormFiscal();
  configurarFormMercadoPago();
}

/* ── CARGAR CONFIGURACIÓN DESDE LA API ── */
async function cargarConfiguracion() {
  try {
    const res  = await fetch('/mini-facturante/api/configuracion.php', {
      credentials: 'same-origin'
    });
    const data = await res.json();

    if (!data.ok) throw new Error(data.mensaje);

    poblarFormulario(data.datos);

  } catch (err) {
    showToast('Error al cargar la configuración: ' + err.message, 'error');
  }
}

/* ── POBLAR FORMULARIO CON DATOS DE LA DB ── */
function poblarFormulario(config) {
  // Datos fiscales
  setVal('config-cuit',  config.cuit);
  setVal('config-razon', config.razon_social);
  setVal('config-pv',    config.punto_venta);
  setVal('config-email', config.email_contacto);

  // Selects
  setSelect('config-condicion', config.condicion_fiscal);
  setSelect('config-categoria', config.categoria_mono);

  // Mercado Pago — mostrar estado del token
  const mpStatus = document.getElementById('mp-token-status');
  if (mpStatus) {
    if (config.mp_token_guardado) {
      mpStatus.innerHTML = `
        <span style="color:var(--status-success);font-size:0.83rem">
          <i class="fas fa-circle-check"></i> Token guardado
        </span>`;
      // Cambiar badge a conectado
      const badge = document.getElementById('mp-badge');
      if (badge) {
        badge.textContent = 'Conectado';
        badge.className = 'badge badge-success';
      }
    }
  }

  setSelect('mp-ambiente', config.mp_ambiente);
}

/* ── FORMULARIO DATOS FISCALES ── */
function configurarFormFiscal() {
  const btnGuardar = document.getElementById('btn-guardar-fiscal');
  if (!btnGuardar || btnGuardar.dataset.cfgListener) return;
  btnGuardar.dataset.cfgListener = 'true';

  btnGuardar.addEventListener('click', async () => {
    const cuit      = document.getElementById('config-cuit')?.value?.trim();
    const razon     = document.getElementById('config-razon')?.value?.trim();
    const pv        = document.getElementById('config-pv')?.value?.trim();
    const email     = document.getElementById('config-email')?.value?.trim();
    const condicion = document.getElementById('config-condicion')?.value;
    const categoria = document.getElementById('config-categoria')?.value;

    if (!cuit || !razon) {
      showToast('CUIT y razón social son obligatorios', 'warning');
      return;
    }

    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<span class="spinner"></span> Guardando...';

    try {
      const res = await fetch('/mini-facturante/api/configuracion.php', {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          seccion:          'fiscal',
          cuit,
          razon_social:     razon,
          punto_venta:      pv || '0001',
          email_contacto:   email,
          condicion_fiscal: condicion,
          categoria_mono:   categoria,
        })
      });

      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje);

      showToast('Datos fiscales guardados correctamente', 'success');

      // Actualizar punto de venta si cambió
      if (data.punto_venta) {
        setVal('config-pv', data.punto_venta);
      }

    } catch (err) {
      showToast(err.message || 'Error al guardar', 'error');
    } finally {
      btnGuardar.disabled = false;
      btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar cambios';
    }
  });
}

/* ── FORMULARIO MERCADO PAGO ── */
function configurarFormMercadoPago() {
  const btnToken = document.getElementById('btn-guardar-token');
  if (!btnToken || btnToken.dataset.cfgListener) return;
  btnToken.dataset.cfgListener = 'true';

  btnToken.addEventListener('click', async () => {
    const token   = document.getElementById('mp-token-input')?.value?.trim();
    const ambiente= document.getElementById('mp-ambiente')?.value;

    if (!token) {
      showToast('Ingresá el Access Token de Mercado Pago', 'warning');
      return;
    }

    btnToken.disabled = true;
    btnToken.innerHTML = '<span class="spinner"></span> Guardando...';

    try {
      const res = await fetch('/mini-facturante/api/configuracion.php', {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          seccion:          'mercadopago',
          mp_access_token:  token,
          mp_ambiente:      ambiente,
        })
      });

      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje);

      showToast('Token de Mercado Pago guardado correctamente', 'success');

      // Actualizar badge
      const badge = document.getElementById('mp-badge');
      if (badge) {
        badge.textContent = 'Conectado';
        badge.className   = 'badge badge-success';
      }

      // Limpiar campo del token por seguridad
      const input = document.getElementById('mp-token-input');
      if (input) input.value = '';

      // Mostrar estado
      const mpStatus = document.getElementById('mp-token-status');
      if (mpStatus) {
        mpStatus.innerHTML = `
          <span style="color:var(--status-success);font-size:0.83rem">
            <i class="fas fa-circle-check"></i> Token guardado
          </span>`;
      }

    } catch (err) {
      showToast(err.message || 'Error al guardar el token', 'error');
    } finally {
      btnToken.disabled = false;
      btnToken.innerHTML = '<i class="fas fa-save"></i> Guardar token';
    }
  });

  // Botón probar conexión
  const btnProbar = document.getElementById('btn-probar-mp');
  if (btnProbar && !btnProbar.dataset.cfgListener) {
    btnProbar.dataset.cfgListener = 'true';
    btnProbar.addEventListener('click', () => {
      showToast('Función disponible con integración real de Mercado Pago', 'info');
    });
  }
}

/* ── HELPERS ── */
function setVal(id, val) {
  const el = document.getElementById(id);
  if (el && val !== undefined && val !== null) el.value = val;
}

function setSelect(id, val) {
  const el = document.getElementById(id);
  if (!el || !val) return;
  for (const opt of el.options) {
    if (opt.value === val || opt.text === val) {
      opt.selected = true;
      break;
    }
  }
}

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', initConfiguracionFiscal);
