<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contacto — Factu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/styles.css">

  <style>
    /* ── ESTILOS ESPECÍFICOS contacto.php ── */

    /* Nav — igual que index.html */
    .landing-nav {
      padding: 0 48px;
      height: 68px;
      gap: 0;
    }

    .nav-menu {
      display: flex;
      align-items: center;
      gap: 4px;
      margin: 0 auto;
    }

    .nav-menu a {
      padding: 8px 14px;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--text-secondary);
      border-radius: var(--radius-sm);
      transition: var(--transition);
      white-space: nowrap;
      text-decoration: none;
    }

    .nav-menu a:hover { color: var(--text-primary); background: var(--bg-app); }
    .nav-menu a.active { color: var(--brand-primary); background: var(--brand-primary-light); }

    .nav-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

    .nav-hamburger {
      display: none;
      flex-direction: column;
      gap: 5px;
      padding: 8px;
      cursor: pointer;
      background: none;
      border: none;
    }

    .nav-hamburger span {
      display: block;
      width: 22px;
      height: 2px;
      background: var(--text-primary);
      border-radius: 2px;
      transition: var(--transition);
    }

    .mobile-menu {
      display: none;
      flex-direction: column;
      position: fixed;
      top: 68px;
      left: 0;
      right: 0;
      background: white;
      border-bottom: 1px solid var(--border-color);
      padding: 12px 20px 20px;
      z-index: 200;
      box-shadow: var(--shadow-lg);
      gap: 4px;
    }

    .mobile-menu.open { display: flex; }

    .mobile-menu a {
      padding: 12px 14px;
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--text-secondary);
      border-radius: var(--radius-sm);
      transition: var(--transition);
      text-decoration: none;
    }

    .mobile-menu a:hover { background: var(--bg-app); color: var(--text-primary); }
    .mobile-menu .btn { margin-top: 8px; }

    /* ── HERO CONTACTO ── */
    .contacto-hero {
      background: linear-gradient(135deg, #0f1d3a 0%, #1a2e57 60%, #1a56db 100%);
      padding: 72px 48px 80px;
      text-align: center;
    }

    .contacto-hero h1 {
      color: white;
      font-size: clamp(1.8rem, 4vw, 2.6rem);
      margin-bottom: 16px;
      letter-spacing: -0.03em;
    }

    .contacto-hero p {
      color: rgba(255,255,255,0.7);
      font-size: 1.05rem;
      max-width: 520px;
      margin: 0 auto;
    }

    /* ── SECCIÓN PRINCIPAL ── */
    .contacto-section {
      background: var(--bg-app);
      padding: 72px 24px;
    }

    .contacto-grid {
      display: grid;
      grid-template-columns: 1fr 1.6fr;
      gap: 48px;
      max-width: 960px;
      margin: 0 auto;
      align-items: start;
    }

    /* ── INFO LATERAL ── */
    .contacto-info {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .contacto-info-item {
      display: flex;
      align-items: flex-start;
      gap: 16px;
      padding: 20px;
      background: white;
      border-radius: var(--radius-md);
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
    }

    .contacto-info-item:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }

    .contacto-info-icon {
      width: 42px;
      height: 42px;
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      flex-shrink: 0;
    }

    .contacto-info-icon.blue   { background: var(--brand-primary-light); color: var(--brand-primary); }
    .contacto-info-icon.green  { background: var(--brand-accent-light);   color: var(--brand-accent); }
    .contacto-info-icon.orange { background: #fef3c7;                     color: var(--status-warning); }

    .contacto-info-text h5 { font-size: 0.9rem; margin-bottom: 4px; }
    .contacto-info-text p  { font-size: 0.85rem; margin: 0; }
    .contacto-info-text a  { color: var(--brand-primary); }

    /* ── FORMULARIO ── */
    .contacto-form-card {
      background: white;
      border-radius: var(--radius-lg);
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-md);
      overflow: hidden;
    }

    .contacto-form-header {
      padding: 28px 32px 0;
    }

    .contacto-form-header h3 {
      font-size: 1.3rem;
      margin-bottom: 6px;
    }

    .contacto-form-header p {
      font-size: 0.875rem;
      margin: 0;
    }

    .contacto-form-body {
      padding: 24px 32px 32px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    /* Estado de éxito */
    .success-state {
      display: none;
      flex-direction: column;
      align-items: center;
      text-align: center;
      padding: 48px 32px;
      gap: 16px;
    }

    .success-state.visible { display: flex; }

    .success-icon {
      width: 64px;
      height: 64px;
      background: var(--brand-accent-light);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      color: var(--brand-accent);
    }

    .success-state h3 { margin: 0; }
    .success-state p  { margin: 0; font-size: 0.9rem; }

    /* Footer igual que index.html */
    .landing-footer { background: var(--bg-sidebar); padding: 0; border-top: none; }

    .footer-top {
      padding: 60px 48px 48px;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 48px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      max-width: 1200px;
      margin: 0 auto;
    }

    .footer-brand-name { font-family: var(--font-display); font-size: 1.1rem; font-weight: 700; color: white; letter-spacing: -0.02em; }
    .footer-brand-name span { color: #60a5fa; }

    .footer-desc { font-size: 0.875rem; color: rgba(255,255,255,0.55); line-height: 1.7; margin: 16px 0 24px; max-width: 280px; text-align: left; }

    .social-links { display: flex; gap: 10px; justify-content: flex-start; }

    .social-btn {
      width: 38px; height: 38px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; transition: var(--transition);
      border: 1px solid rgba(255,255,255,0.1);
      color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.05);
      text-decoration: none;
    }

    .social-btn:hover { transform: translateY(-2px); border-color: rgba(255,255,255,0.25); color: white; }
    .social-btn.instagram:hover { background: linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); border-color: transparent; }
    .social-btn.twitter:hover   { background: #000; border-color: transparent; }
    .social-btn.facebook:hover  { background: #1877f2; border-color: transparent; }
    .social-btn.whatsapp:hover  { background: #25d366; border-color: transparent; }

    .footer-col h5 { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.9); margin-bottom: 18px; text-align: left; }

    .footer-col ul { display: flex; flex-direction: column; gap: 10px; }

    .footer-col ul li a { font-size: 0.875rem; color: rgba(255,255,255,0.5); transition: var(--transition); display: flex; align-items: center; gap: 8px; text-decoration: none; }

    .footer-col ul li a:hover { color: rgba(255,255,255,0.9); padding-left: 4px; }
    .footer-col ul li a i { width: 14px; opacity: 0.7; flex-shrink: 0; }

    .footer-bottom { padding: 20px 48px; max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .footer-bottom p { font-size: 0.8rem; color: rgba(255,255,255,0.35); }

    .footer-legal { display: flex; gap: 20px; }
    .footer-legal a { font-size: 0.8rem; color: rgba(255,255,255,0.35); transition: var(--transition); text-decoration: none; }
    .footer-legal a:hover { color: rgba(255,255,255,0.7); }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
      .landing-nav { padding: 0 20px; height: 64px; }
      .nav-menu { display: none; }
      .nav-hamburger { display: flex; }

      .contacto-hero { padding: 52px 20px 60px; }

      .contacto-section { padding: 48px 20px; }

      .contacto-grid {
        grid-template-columns: 1fr;
        gap: 28px;
      }

      .contacto-form-header { padding: 24px 24px 0; }
      .contacto-form-body   { padding: 20px 24px 28px; }

      .footer-top { padding: 40px 20px; grid-template-columns: 1fr; gap: 28px; text-align: center; }
      .footer-desc { text-align: center; max-width: 100%; }
      .social-links { justify-content: center; }
      .footer-col h5 { text-align: center; }
      .footer-col ul li a { justify-content: center; }
      .footer-bottom { padding: 18px 20px; flex-direction: column; text-align: center; gap: 10px; }
      .footer-legal { flex-wrap: wrap; justify-content: center; gap: 12px; }
    }

    @media (max-width: 480px) {
      .contacto-form-header { padding: 20px 18px 0; }
      .contacto-form-body   { padding: 16px 18px 24px; }
    }
  </style>
</head>
<body>

<div class="landing-page">

  <!-- ══ NAV ══ -->
  <nav class="landing-nav">
    <a href="../index.html" class="landing-logo" style="text-decoration:none;flex-shrink:0">
      <div class="landing-logo-icon">
        <i class="fas fa-file-invoice-dollar"></i>
      </div>
      <span class="landing-logo-text">Fac<span>tu</span></span>
    </a>

    <div class="nav-menu">
      <a href="../index.html#funcionalidades">Funcionalidades</a>
      <a href="../index.html#como-funciona">Cómo funciona</a>
      <a href="../index.html#nosotros">Nosotros</a>
      <a href="contacto.php" class="active">Contacto</a>
    </div>

    <div class="nav-actions">
      <a href="../index.html#login-section" class="btn btn-primary btn-sm">
        <i class="fas fa-right-to-bracket"></i>
        Iniciar sesión
      </a>
      <button class="nav-hamburger" id="hamburger-btn" aria-label="Abrir menú">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>

  <!-- Menú mobile -->
  <div class="mobile-menu" id="mobile-menu">
    <a href="../index.html#funcionalidades" onclick="closeMobileMenu()">Funcionalidades</a>
    <a href="../index.html#como-funciona"   onclick="closeMobileMenu()">Cómo funciona</a>
    <a href="../index.html#nosotros"        onclick="closeMobileMenu()">Nosotros</a>
    <a href="contacto.php"                  onclick="closeMobileMenu()">Contacto</a>
    <a href="../index.html#login-section" class="btn btn-primary btn-sm" onclick="closeMobileMenu()">
      <i class="fas fa-right-to-bracket"></i> Iniciar sesión
    </a>
  </div>

  <!-- ══ HERO ══ -->
  <section class="contacto-hero">
    <div class="hero-badge" style="display:inline-flex;margin-bottom:20px">
      <i class="fas fa-envelope"></i>
      Estamos para ayudarte
    </div>
    <h1>¿Tenés alguna consulta?</h1>
    <p>
      Completá el formulario y te respondemos a la brevedad.
      También podés escribirnos directamente por WhatsApp.
    </p>
  </section>

  <!-- ══ CONTENIDO PRINCIPAL ══ -->
  <section class="contacto-section">
    <div class="contacto-grid">

      <!-- Info lateral -->
      <div class="contacto-info">

        <div class="contacto-info-item">
          <div class="contacto-info-icon blue">
            <i class="fas fa-envelope"></i>
          </div>
          <div class="contacto-info-text">
            <h5>Email</h5>
            <p><a href="mailto:hola@minifacturante.ar">hola@factu.ar</a></p>
          </div>
        </div>

        <div class="contacto-info-item">
          <div class="contacto-info-icon green">
            <i class="fab fa-whatsapp"></i>
          </div>
          <div class="contacto-info-text">
            <h5>WhatsApp</h5>
            <p>
              <a href="https://wa.me/5491100000000" target="_blank">
                +54 9 11 0000-0000
              </a>
            </p>
          </div>
        </div>

        <div class="contacto-info-item">
          <div class="contacto-info-icon orange">
            <i class="fas fa-map-marker-alt"></i>
          </div>
          <div class="contacto-info-text">
            <h5>Ubicación</h5>
            <p>Buenos Aires, Argentina</p>
          </div>
        </div>

        <!-- Tiempo de respuesta -->
        <div style="background:var(--brand-primary-light);border:1px solid rgba(26,86,219,0.15);
                    border-radius:var(--radius-md);padding:18px 20px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <i class="fas fa-clock" style="color:var(--brand-primary)"></i>
            <span style="font-weight:600;font-size:0.9rem;color:var(--brand-primary)">
              Tiempo de respuesta
            </span>
          </div>
          <p style="font-size:0.85rem;color:var(--text-secondary);margin:0">
            Respondemos todos los mensajes en menos de <strong>24 horas hábiles</strong>.
          </p>
        </div>

      </div>

      <!-- Formulario -->
      <div class="contacto-form-card">

        <!-- Header del card -->
        <div class="contacto-form-header">
          <h3>Envianos un mensaje</h3>
          <p>Todos los campos marcados con <span style="color:var(--status-error)">*</span> son obligatorios.</p>
        </div>

        <!-- Estado éxito (oculto por defecto) -->
        <div class="success-state" id="success-state">
          <div class="success-icon">
            <i class="fas fa-circle-check"></i>
          </div>
          <h3>¡Mensaje enviado!</h3>
          <p style="color:var(--text-secondary)">
            Gracias por contactarnos. Te responderemos a <strong id="success-email"></strong> a la brevedad.
          </p>
          <button class="btn btn-secondary" onclick="mostrarFormulario()" style="margin-top:8px">
            <i class="fas fa-rotate-left"></i> Enviar otro mensaje
          </button>
        </div>

        <!-- Formulario (visible por defecto) -->
        <form class="contacto-form-body" id="contacto-form" novalidate>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="contact-nombre">
                Nombre <span style="color:var(--status-error)">*</span>
              </label>
              <input
                type="text"
                class="form-input"
                id="contact-nombre"
                placeholder="Tu nombre completo"
                maxlength="100"
                autocomplete="name"
              >
            </div>
            <div class="form-group">
              <label class="form-label" for="contact-email">
                Email <span style="color:var(--status-error)">*</span>
              </label>
              <input
                type="email"
                class="form-input"
                id="contact-email"
                placeholder="tu@email.com"
                maxlength="150"
                autocomplete="email"
              >
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="contact-asunto">
              Asunto
              <span class="text-muted" style="font-size:0.75rem;font-weight:400">(opcional)</span>
            </label>
            <input
              type="text"
              class="form-input"
              id="contact-asunto"
              placeholder="Ej: Consulta sobre facturación"
              maxlength="150"
            >
          </div>

          <div class="form-group">
            <label class="form-label" for="contact-mensaje">
              Mensaje <span style="color:var(--status-error)">*</span>
            </label>
            <textarea
              class="form-input"
              id="contact-mensaje"
              placeholder="Contanos tu consulta con el mayor detalle posible..."
              rows="5"
              maxlength="2000"
              style="resize:vertical;min-height:120px"
            ></textarea>
            <div style="display:flex;justify-content:flex-end;margin-top:4px">
              <span id="char-count" style="font-size:0.75rem;color:var(--text-muted)">0 / 2000</span>
            </div>
          </div>

          <!-- Error general -->
          <div id="form-error" style="display:none;padding:12px 16px;background:var(--status-error-bg);
               border:1px solid rgba(239,68,68,0.2);border-radius:var(--radius-sm);
               font-size:0.875rem;color:var(--status-error)">
            <i class="fas fa-triangle-exclamation"></i>
            <span id="form-error-text"></span>
          </div>

          <button type="submit" class="btn btn-primary btn-full btn-lg" id="btn-contacto">
            <i class="fas fa-paper-plane"></i>
            Enviar mensaje
          </button>

          <p class="text-muted text-center" style="font-size:0.78rem;margin-top:4px">
            <i class="fas fa-shield-halved" style="color:var(--brand-primary)"></i>
            Tu información no será compartida con terceros.
          </p>

        </form>
      </div>

    </div>
  </section>

  <!-- ══ FOOTER ══ -->
  <footer class="landing-footer">
    <div class="footer-top">

      <div>
        <div class="landing-logo" style="margin-bottom:0">
          <div class="landing-logo-icon">
            <i class="fas fa-file-invoice-dollar"></i>
          </div>
          <span class="footer-brand-name">Fac<span>tu</span></span>
        </div>
        <p class="footer-desc">
          Automatizá tu facturación electrónica en Argentina.
          Integrado con AFIP/ARCA y Mercado Pago.
        </p>
        <div class="social-links">
          <a href="https://instagram.com" target="_blank" rel="noopener" class="social-btn instagram" title="Instagram">
            <i class="fab fa-instagram"></i>
          </a>
          <a href="https://twitter.com" target="_blank" rel="noopener" class="social-btn twitter" title="Twitter / X">
            <i class="fab fa-x-twitter"></i>
          </a>
          <a href="https://facebook.com" target="_blank" rel="noopener" class="social-btn facebook" title="Facebook">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="https://wa.me/5491100000000" target="_blank" rel="noopener" class="social-btn whatsapp" title="WhatsApp">
            <i class="fab fa-whatsapp"></i>
          </a>
        </div>
      </div>

      <div class="footer-col">
        <h5>Producto</h5>
        <ul>
          <li><a href="../index.html#funcionalidades">Funcionalidades</a></li>
          <li><a href="../index.html#como-funciona">Cómo funciona</a></li>
          <li><a href="../pages/dashboard.php">Ver demo</a></li>
          <li><a href="../index.html#login-section">Iniciar sesión</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h5>Soporte</h5>
        <ul>
          <li><a href="#">Documentación</a></li>
          <li><a href="#">Preguntas frecuentes</a></li>
          <li><a href="#">Estado del servicio</a></li>
          <li><a href="https://wa.me/5491100000000" target="_blank">Chat por WhatsApp</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h5>Contacto</h5>
        <ul>
          <li>
            <a href="mailto:hola@minifacturante.ar">
              <i class="fas fa-envelope"></i> hola@factu.ar
            </a>
          </li>
          <li>
            <a href="https://wa.me/5491100000000" target="_blank">
              <i class="fab fa-whatsapp"></i> +54 9 11 0000-0000
            </a>
          </li>
          <li>
            <a href="#">
              <i class="fas fa-map-marker-alt"></i> Buenos Aires, Argentina
            </a>
          </li>
        </ul>
      </div>

    </div>

    <div class="footer-bottom">
      <p>© 2025 Factu · Desarrollado con ❤️ en Argentina · Uso personal</p>
      <div class="footer-legal">
        <a href="#">Términos de uso</a>
        <a href="#">Privacidad</a>
        <a href="#">Cookies</a>
      </div>
    </div>
  </footer>

</div>

<div class="toast-container"></div>

<script>
/* ══════════════════════════════════════════
   contacto.js — inline para esta página
   ══════════════════════════════════════════ */

// ── Hamburger menu ──
const hamburgerBtn = document.getElementById('hamburger-btn');
const mobileMenu   = document.getElementById('mobile-menu');

hamburgerBtn.addEventListener('click', () => mobileMenu.classList.toggle('open'));

function closeMobileMenu() { mobileMenu.classList.remove('open'); }

document.addEventListener('click', (e) => {
  if (!hamburgerBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
    closeMobileMenu();
  }
});

// ── Contador de caracteres ──
const mensajeInput = document.getElementById('contact-mensaje');
const charCount    = document.getElementById('char-count');

mensajeInput.addEventListener('input', () => {
  const len = mensajeInput.value.length;
  charCount.textContent = `${len} / 2000`;
  charCount.style.color = len > 1800 ? 'var(--status-warning)' : 'var(--text-muted)';
});

// ── Mostrar / ocultar error ──
function mostrarError(msg) {
  const div  = document.getElementById('form-error');
  const span = document.getElementById('form-error-text');
  span.textContent = ' ' + msg;
  div.style.display = 'flex';
  div.style.alignItems = 'center';
  div.style.gap = '8px';
}

function ocultarError() {
  document.getElementById('form-error').style.display = 'none';
}

// ── Mostrar estado éxito / formulario ──
function mostrarFormulario() {
  document.getElementById('success-state').classList.remove('visible');
  document.getElementById('contacto-form').style.display = 'flex';
}

function mostrarExito(email) {
  document.getElementById('contacto-form').style.display = 'none';
  document.getElementById('success-email').textContent = email;
  document.getElementById('success-state').classList.add('visible');
}

// ── Toast básico (sin app.js) ──
function showToastContacto(msg, tipo = 'info') {
  const icons = { success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info', warning:'fa-triangle-exclamation' };
  let container = document.querySelector('.toast-container');

  const toast = document.createElement('div');
  toast.className = `toast ${tipo}`;
  toast.innerHTML = `
    <i class="fas ${icons[tipo] || icons.info} toast-icon"></i>
    <span class="toast-message">${msg}</span>
    <i class="fas fa-xmark toast-close" onclick="this.parentElement.remove()"></i>
  `;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// ── Submit del formulario ──
document.getElementById('contacto-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  ocultarError();

  const nombre  = document.getElementById('contact-nombre').value.trim();
  const email   = document.getElementById('contact-email').value.trim();
  const asunto  = document.getElementById('contact-asunto').value.trim();
  const mensaje = document.getElementById('contact-mensaje').value.trim();
  const btn     = document.getElementById('btn-contacto');

  // Validación frontend básica
  if (!nombre) { mostrarError('El nombre es obligatorio.'); document.getElementById('contact-nombre').focus(); return; }
  if (!email)  { mostrarError('El email es obligatorio.');  document.getElementById('contact-email').focus();  return; }
  if (!mensaje){ mostrarError('El mensaje es obligatorio.'); mensajeInput.focus(); return; }

  // Estado de carga
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Enviando...';

  try {
    const res = await fetch('/mini-facturante/api/contacto.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ nombre, email, asunto, mensaje })
    });

    const data = await res.json();

    if (data.status === 'ok') {
      // Limpiar formulario y mostrar éxito
      document.getElementById('contacto-form').reset();
      charCount.textContent = '0 / 2000';
      mostrarExito(email);
    } else {
      mostrarError(data.message || 'Error al enviar. Intentá de nuevo.');
    }

  } catch (err) {
    mostrarError('Error de conexión. Revisá tu internet e intentá de nuevo.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar mensaje';
  }
});
</script>

</body>
</html>
