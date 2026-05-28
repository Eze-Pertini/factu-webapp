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

/* ── POBLAR FORMULARIO ── */
function poblarFormulario(config) {
  // Datos identificatorios
  setVal('config-cuit',       config.cuit);
  setVal('config-pv',         config.punto_venta);
  setVal('config-razon',      config.razon_social);
  setVal('config-iibb',       config.iibb);

  // Inicio actividades: la DB guarda YYYY-MM-DD, el input type=date necesita ese formato
  if (config.inicio_actividades) {
    const partes = config.inicio_actividades.split('T')[0]; // por si viene con hora
    setVal('config-inicio-act', partes);
  }

  // Condición y categoría
  setSelect('config-condicion', config.condicion_fiscal);
  setSelect('config-categoria', config.categoria_mono);

  // Domicilio
  setVal('config-domicilio',  config.domicilio);
  setVal('config-piso-dpto',  config.piso_dpto);
  setVal('config-ciudad',     config.ciudad);
  setVal('config-provincia',  config.provincia);
  setVal('config-cp',         config.codigo_postal);

  // Contacto
  setVal('config-email',      config.email_contacto);
  setVal('config-telefono',   config.telefono);

  // Mercado Pago
  const mpStatus = document.getElementById('mp-token-status');
  if (mpStatus) {
    if (config.mp_token_guardado) {
      mpStatus.innerHTML = `
        <span style="color:var(--status-success);font-size:0.83rem">
          <i class="fas fa-circle-check"></i> Token guardado correctamente
        </span>`;
      const badge = document.getElementById('mp-badge');
      if (badge) {
        badge.textContent = 'Conectado';
        badge.className   = 'badge badge-success';
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
    const cuit       = document.getElementById('config-cuit')?.value?.trim();
    const razon      = document.getElementById('config-razon')?.value?.trim();
    const pv         = document.getElementById('config-pv')?.value?.trim();
    const email      = document.getElementById('config-email')?.value?.trim();
    const condicion  = document.getElementById('config-condicion')?.value;
    const categoria  = document.getElementById('config-categoria')?.value;
    const iibb       = document.getElementById('config-iibb')?.value?.trim();
    const inicioAct  = document.getElementById('config-inicio-act')?.value?.trim();
    const domicilio  = document.getElementById('config-domicilio')?.value?.trim();
    const pisoDpto   = document.getElementById('config-piso-dpto')?.value?.trim();
    const ciudad     = document.getElementById('config-ciudad')?.value?.trim();
    const provincia  = document.getElementById('config-provincia')?.value?.trim();
    const cp         = document.getElementById('config-cp')?.value?.trim();
    const telefono   = document.getElementById('config-telefono')?.value?.trim();

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
          seccion:             'fiscal',
          cuit,
          razon_social:        razon,
          punto_venta:         pv || '0001',
          email_contacto:      email,
          condicion_fiscal:    condicion,
          categoria_mono:      categoria,
          iibb,
          inicio_actividades:  inicioAct,
          domicilio,
          piso_dpto:           pisoDpto,
          ciudad,
          provincia,
          codigo_postal:       cp,
          telefono,
        })
      });

      const data = await res.json();
      if (!data.ok) throw new Error(data.mensaje);

      showToast('Datos fiscales guardados correctamente', 'success');

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

      const badge = document.getElementById('mp-badge');
      if (badge) {
        badge.textContent = 'Conectado';
        badge.className   = 'badge badge-success';
      }

      // Limpiar campo por seguridad
      const input = document.getElementById('mp-token-input');
      if (input) input.value = '';

      const mpStatus = document.getElementById('mp-token-status');
      if (mpStatus) {
        mpStatus.innerHTML = `
          <span style="color:var(--status-success);font-size:0.83rem">
            <i class="fas fa-circle-check"></i> Token guardado correctamente
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
