/* ==============================================
   Factu — app.js
   Funciones globales reutilizables:
   - showToast
   - initSidebar
   - initLogin
   - formatCurrency, formatDate, getBadgeHTML

   Módulos dedicados (no tocar acá):
   - dashboard.js     → métricas y actividad
   - pagos.js         → importar pagos de MP
   - facturar.js      → generar facturas
   - historial.js     → historial de facturas
   - productos.js     → gestión de productos
   - configuracion.js → datos fiscales
   ============================================== */


/* ══════════════════════════════════════════════
   UTILIDADES
   ══════════════════════════════════════════════ */

function formatCurrency(amount) {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', minimumFractionDigits: 2
  }).format(amount);
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const parte = dateStr.includes('T') ? dateStr.split('T')[0] : dateStr.split(' ')[0];
  const [y, m, d] = parte.split('-');
  return `${d}/${m}/${y}`;
}

function getBadgeHTML(estado) {
  const map = {
    'pendiente': 'warning', 'facturado': 'success',
    'emitida':   'success', 'anulada':   'error',
    'Pendiente': 'warning', 'Facturado': 'success',
    'Emitida':   'success', 'Anulada':   'error',
  };
  const cls   = map[estado] || 'info';
  const texto = estado.charAt(0).toUpperCase() + estado.slice(1);
  return `<span class="badge badge-${cls}">${texto}</span>`;
}


/* ══════════════════════════════════════════════
   TOAST / NOTIFICACIONES
   ══════════════════════════════════════════════ */

function showToast(message, type = 'info', duration = 3500) {
  const icons = {
    success: 'fa-circle-check',
    error:   'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info'
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
    <i class="fas ${icons[type] || icons.info} toast-icon"></i>
    <span class="toast-message">${message}</span>
    <i class="fas fa-xmark toast-close" onclick="this.parentElement.remove()"></i>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity    = '0';
    toast.style.transform  = 'translateX(100%)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}


/* ══════════════════════════════════════════════
   SIDEBAR / NAVEGACIÓN
   ══════════════════════════════════════════════ */

function initSidebar() {
  const sidebar    = document.getElementById('sidebar');
  const overlay    = document.getElementById('sidebar-overlay');
  const menuToggle = document.getElementById('menu-toggle');

  if (!sidebar) return;

  if (menuToggle) {
    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('open');
    });
  }

  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
  }

  // Marcar nav item activo según URL actual
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-item[data-page]').forEach(item => {
    item.classList.remove('active');
    if (item.dataset.page === currentPage) item.classList.add('active');
  });

  // Nombre e iniciales → los maneja PHP desde sidebar.php — no sobreescribir
}


/* ══════════════════════════════════════════════
   LOGIN
   ══════════════════════════════════════════════ */

function initLogin() {
  const form = document.getElementById('login-form');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const email = document.getElementById('login-email')?.value?.trim();
    const pass  = document.getElementById('login-pass')?.value;
    const btn   = document.getElementById('btn-login');

    if (!email || !pass) {
      showToast('Completá email y contraseña', 'warning');
      return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> Ingresando...';

    fetch('/mini-facturante/api/login.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email, password: pass })
    })
    .then(res => res.json())
    .then(data => {
      btn.disabled  = false;
      btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Ingresar';
      if (data.ok) {
        window.location.href = '/mini-facturante/pages/dashboard.php';
      } else {
        showToast(data.mensaje, 'error');
      }
    })
    .catch(() => {
      btn.disabled  = false;
      btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Ingresar';
      showToast('Error de conexión. Revisá que XAMPP esté corriendo.', 'error');
    });
  });

  const btnScrollLogin = document.getElementById('btn-go-login');
  if (btnScrollLogin) {
    btnScrollLogin.addEventListener('click', () => {
      document.getElementById('login-section')?.scrollIntoView({ behavior: 'smooth' });
    });
  }
}


/* ══════════════════════════════════════════════
   INICIALIZACIÓN
   Cada módulo tiene su propio DOMContentLoaded.
   Acá solo iniciamos lo verdaderamente global.
   ══════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initLogin();
});
