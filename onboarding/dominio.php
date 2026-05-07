<?php
// ── Protección: solo doctores autenticados ───────────────────
require_once __DIR__ . '/../auth/session.php';
requireLogin();

// Si ya tiene dominio, al dashboard
require_once __DIR__ . '/../config/db.php';
$stmt = getDB()->prepare("SELECT dominio FROM doctores WHERE id = ?");
$stmt->execute([$_SESSION['doctor_id']]);
$doc = $stmt->fetch();
if (!empty($doc['dominio'])) {
    header('Location: ../dashboard.php');
    exit;
}

$nombre = $_SESSION['doctor_nombre'] ?? 'Doctor';
// Sugerir slug basado en el nombre
$slug = strtolower(preg_replace('/[^a-z0-9]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre)));
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Elige tu dominio — Doctores.Digital</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue:       #1a5fcb;
      --blue-dark:  #0d3d8a;
      --blue-light: #e8f0fc;
      --teal:       #0bb5ae;
      --green:      #10b981;
      --green-light:#d1fae5;
      --red:        #ef4444;
      --red-light:  #fee2e2;
      --amber:      #f59e0b;
      --amber-light:#fef3c7;
      --text:       #0d1526;
      --text-2:     #3d4d6a;
      --text-3:     #7284a3;
      --border:     #e2e8f4;
      --surface:    #f6f9ff;
      --white:      #ffffff;
      --radius:     14px;
      --shadow:     0 4px 24px rgba(26,95,203,.10);
    }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--surface);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 40px 20px 80px;
    }

    /* ── Header ── */
    .brand {
      font-size: 1.3rem;
      font-weight: 800;
      color: var(--blue);
      margin-bottom: 40px;
      letter-spacing: -.5px;
    }
    .brand span { color: var(--teal); }

    /* ── Stepper ── */
    .stepper {
      display: flex;
      align-items: center;
      gap: 0;
      margin-bottom: 40px;
    }
    .step {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: .82rem;
      font-weight: 600;
      color: var(--text-3);
    }
    .step.active { color: var(--blue); }
    .step.done   { color: var(--green); }
    .step-num {
      width: 28px; height: 28px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .78rem; font-weight: 700;
      background: var(--border);
      color: var(--text-3);
      flex-shrink: 0;
    }
    .step.active .step-num { background: var(--blue); color: #fff; }
    .step.done   .step-num { background: var(--green); color: #fff; }
    .step-line {
      width: 48px; height: 2px;
      background: var(--border);
      margin: 0 4px;
    }

    /* ── Card principal ── */
    .card {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 40px;
      width: 100%;
      max-width: 680px;
    }
    .card-title {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 8px;
      line-height: 1.2;
    }
    .card-subtitle {
      font-size: .95rem;
      color: var(--text-2);
      margin-bottom: 32px;
    }

    /* ── Search box ── */
    .search-wrap {
      display: flex;
      gap: 10px;
      margin-bottom: 10px;
    }
    .search-input {
      flex: 1;
      padding: 14px 18px;
      border: 2px solid var(--border);
      border-radius: 10px;
      font-size: 1rem;
      font-family: inherit;
      color: var(--text);
      outline: none;
      transition: border-color .2s;
    }
    .search-input:focus { border-color: var(--blue); }
    .btn-search {
      padding: 14px 24px;
      background: var(--blue);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: .95rem;
      font-weight: 700;
      cursor: pointer;
      transition: background .2s;
      white-space: nowrap;
    }
    .btn-search:hover { background: var(--blue-dark); }
    .btn-search:disabled { opacity: .6; cursor: not-allowed; }
    .hint {
      font-size: .8rem;
      color: var(--text-3);
      margin-bottom: 28px;
    }

    /* ── Resultados ── */
    #resultados { margin-top: 4px; }
    .loading {
      text-align: center;
      padding: 32px;
      color: var(--text-3);
      font-size: .9rem;
    }
    .spinner {
      display: inline-block;
      width: 22px; height: 22px;
      border: 3px solid var(--border);
      border-top-color: var(--blue);
      border-radius: 50%;
      animation: spin .7s linear infinite;
      margin-bottom: 10px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Resultado item ── */
    .dominio-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      border: 2px solid var(--border);
      border-radius: 12px;
      margin-bottom: 10px;
      transition: border-color .2s, box-shadow .2s;
      gap: 12px;
    }
    .dominio-item.available {
      cursor: pointer;
    }
    .dominio-item.available:hover {
      border-color: var(--blue);
      box-shadow: 0 0 0 4px rgba(26,95,203,.08);
    }
    .dominio-item.selected {
      border-color: var(--blue);
      background: var(--blue-light);
      box-shadow: 0 0 0 4px rgba(26,95,203,.12);
    }
    .dominio-item.taken {
      opacity: .55;
      background: #fafafa;
    }
    .dom-name {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--text);
      word-break: break-all;
    }
    .dom-right {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-shrink: 0;
    }
    .dom-price {
      font-size: .95rem;
      font-weight: 700;
      color: var(--blue);
    }
    .badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .3px;
    }
    .badge-available { background: var(--green-light); color: #065f46; }
    .badge-taken     { background: var(--red-light);   color: #991b1b; }

    /* ── Panel de confirmación ── */
    #confirmar {
      display: none;
      margin-top: 28px;
      border-top: 2px solid var(--border);
      padding-top: 28px;
    }
    .confirm-title {
      font-size: 1.1rem;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--text);
    }
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      margin-bottom: 14px;
    }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label {
      font-size: .8rem;
      font-weight: 600;
      color: var(--text-2);
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .form-input {
      padding: 11px 14px;
      border: 2px solid var(--border);
      border-radius: 8px;
      font-size: .95rem;
      font-family: inherit;
      color: var(--text);
      outline: none;
      transition: border-color .2s;
    }
    .form-input:focus { border-color: var(--blue); }

    /* Dominio seleccionado destacado */
    .selected-domain-box {
      background: var(--blue-light);
      border: 2px solid var(--blue);
      border-radius: 10px;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }
    .selected-domain-box .name {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--blue);
    }
    .selected-domain-box .price {
      font-size: .95rem;
      font-weight: 700;
      color: var(--text-2);
    }

    /* ── Botón registrar ── */
    .btn-register {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, var(--blue), var(--teal));
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      margin-top: 20px;
      transition: opacity .2s, transform .1s;
      letter-spacing: .3px;
    }
    .btn-register:hover:not(:disabled) { opacity: .92; transform: translateY(-1px); }
    .btn-register:disabled { opacity: .6; cursor: not-allowed; }

    /* ── Alertas ── */
    .alert {
      padding: 14px 18px;
      border-radius: 10px;
      font-size: .9rem;
      margin-top: 16px;
      display: none;
    }
    .alert-error   { background: var(--red-light);   color: #991b1b; }
    .alert-success { background: var(--green-light);  color: #065f46; }
    .alert-warning { background: var(--amber-light);  color: #92400e; }

    /* ── Responsive ── */
    @media (max-width: 520px) {
      .card { padding: 24px 18px; }
      .form-grid { grid-template-columns: 1fr; }
      .search-wrap { flex-direction: column; }
      .card-title { font-size: 1.3rem; }
    }
  </style>
</head>
<body>

  <div class="brand">Doctores<span>.Digital</span></div>

  <!-- Stepper -->
  <div class="stepper">
    <div class="step active">
      <div class="step-num">1</div>
      <span>Tu dominio</span>
    </div>
    <div class="step-line"></div>
    <div class="step">
      <div class="step-num">2</div>
      <span>Tu sitio web</span>
    </div>
    <div class="step-line"></div>
    <div class="step">
      <div class="step-num">3</div>
      <span>Servicios</span>
    </div>
  </div>

  <!-- Card -->
  <div class="card">
    <h1 class="card-title">Elige el dominio de tu consultorio</h1>
    <p class="card-subtitle">
      Tu dominio es la dirección de tu consultorio en internet.<br>
      Busca el nombre que mejor te represente.
    </p>

    <!-- Buscador -->
    <div class="search-wrap">
      <input type="text" id="searchInput" class="search-input"
             placeholder="Ej: drgarcia, cardiologiamorales…"
             value="<?= htmlspecialchars($slug) ?>"
             maxlength="50"/>
      <button id="btnBuscar" class="btn-search" onclick="buscarDominios()">
        Buscar →
      </button>
    </div>
    <p class="hint">Solo letras, números y guiones. Sin espacios ni acentos.</p>

    <!-- Resultados -->
    <div id="resultados"></div>

    <!-- Confirmar registro -->
    <div id="confirmar">
      <div class="selected-domain-box">
        <span class="name" id="confirmarNombre">—</span>
        <span class="price" id="confirmarPrecio">—</span>
      </div>

      <p class="confirm-title">Datos del registrante del dominio</p>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Nombre</label>
          <input type="text" id="reg_nombre" class="form-input"
                 placeholder="Juan"
                 value="<?= htmlspecialchars(explode(' ', $nombre)[0] ?? '') ?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">Apellido</label>
          <input type="text" id="reg_apellido" class="form-input"
                 placeholder="García López"/>
        </div>
        <div class="form-group">
          <label class="form-label">Correo electrónico</label>
          <input type="email" id="reg_email" class="form-input"
                 placeholder="dr@ejemplo.com"
                 value="<?= htmlspecialchars($_SESSION['doctor_email'] ?? '') ?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">Teléfono (+52...)</label>
          <input type="tel" id="reg_telefono" class="form-input"
                 placeholder="+52.4771234567"/>
        </div>
        <div class="form-group full">
          <label class="form-label">Dirección</label>
          <input type="text" id="reg_direccion" class="form-input"
                 placeholder="Av. Hidalgo 123, Col. Centro"/>
        </div>
        <div class="form-group">
          <label class="form-label">Ciudad</label>
          <input type="text" id="reg_ciudad" class="form-input"
                 placeholder="Guadalajara"/>
        </div>
        <div class="form-group">
          <label class="form-label">Estado</label>
          <input type="text" id="reg_estado" class="form-input"
                 placeholder="JAL"/>
        </div>
        <div class="form-group">
          <label class="form-label">Código postal</label>
          <input type="text" id="reg_cp" class="form-input"
                 placeholder="44100"/>
        </div>
      </div>

      <div class="alert alert-error"   id="alertError"></div>
      <div class="alert alert-success" id="alertSuccess"></div>
      <div class="alert alert-warning" id="alertWarning"></div>

      <button class="btn-register" id="btnRegistrar" onclick="registrarDominio()">
        🌐 Registrar dominio
      </button>
    </div>
  </div>

<script>
  let dominioSeleccionado = null;
  let precioSeleccionado  = null;
  const csrf = <?= json_encode($csrf) ?>;

  // Limpiar input: solo letras/nums/guion
  document.getElementById('searchInput').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
  });

  // Buscar al presionar Enter
  document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') buscarDominios();
  });

  // Buscar automáticamente al cargar si hay slug
  window.addEventListener('DOMContentLoaded', () => {
    const val = document.getElementById('searchInput').value.trim();
    if (val.length >= 3) buscarDominios();
  });

  async function buscarDominios() {
    const query = document.getElementById('searchInput').value.trim();
    if (query.length < 2) return;

    const btn = document.getElementById('btnBuscar');
    btn.disabled = true;
    btn.textContent = 'Buscando…';

    document.getElementById('resultados').innerHTML = `
      <div class="loading">
        <div class="spinner"></div><br>
        Verificando disponibilidad…
      </div>`;
    document.getElementById('confirmar').style.display = 'none';
    dominioSeleccionado = null;

    try {
      const resp = await fetch('ajax/buscar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query, csrf }),
      });
      const data = await resp.json();
      renderResultados(data.resultados || []);
    } catch(e) {
      document.getElementById('resultados').innerHTML =
        `<div class="loading">Error de conexión. Intenta de nuevo.</div>`;
    } finally {
      btn.disabled = false;
      btn.textContent = 'Buscar →';
    }
  }

  function renderResultados(items) {
    if (!items.length) {
      document.getElementById('resultados').innerHTML =
        `<div class="loading">No se encontraron resultados. Prueba otro nombre.</div>`;
      return;
    }

    let html = '';
    items.forEach(item => {
      const avail   = item.available;
      const price   = item.price ? `$${parseFloat(item.price).toFixed(2)} ${item.currency}/año` : '';
      const badgeCls= avail ? 'badge-available' : 'badge-taken';
      const badgeTxt= avail ? 'Disponible' : 'No disponible';
      const cls     = avail ? 'available' : 'taken';
      const onclick = avail
        ? `onclick="seleccionarDominio('${item.domain}', ${item.price}, '${item.currency}')"`
        : '';

      html += `
        <div class="dominio-item ${cls}" id="item-${item.domain.replace(/\./g,'_')}" ${onclick}>
          <span class="dom-name">${item.domain}</span>
          <div class="dom-right">
            ${price ? `<span class="dom-price">${price}</span>` : ''}
            <span class="badge ${badgeCls}">${badgeTxt}</span>
          </div>
        </div>`;
    });

    document.getElementById('resultados').innerHTML = html;
  }

  function seleccionarDominio(domain, price, currency) {
    // Quitar selección previa
    document.querySelectorAll('.dominio-item').forEach(el => {
      el.classList.remove('selected');
    });

    // Marcar el seleccionado
    const id = domain.replace(/\./g, '_');
    document.getElementById('item-' + id)?.classList.add('selected');

    dominioSeleccionado = domain;
    precioSeleccionado  = price;

    const priceStr = price ? `$${parseFloat(price).toFixed(2)} ${currency}/año` : '';
    document.getElementById('confirmarNombre').textContent = domain;
    document.getElementById('confirmarPrecio').textContent = priceStr;
    document.getElementById('confirmar').style.display = 'block';

    // Scroll suave al formulario
    document.getElementById('confirmar').scrollIntoView({ behavior: 'smooth' });
  }

  async function registrarDominio() {
    if (!dominioSeleccionado) return;

    // Recoger datos del formulario
    const contacto = {
      nameFirst:      document.getElementById('reg_nombre').value.trim(),
      nameLast:       document.getElementById('reg_apellido').value.trim(),
      email:          document.getElementById('reg_email').value.trim(),
      phone:          document.getElementById('reg_telefono').value.trim(),
      addressMailing: {
        address1:   document.getElementById('reg_direccion').value.trim(),
        city:       document.getElementById('reg_ciudad').value.trim(),
        state:      document.getElementById('reg_estado').value.trim(),
        postalCode: document.getElementById('reg_cp').value.trim(),
        country:    'MX',
      }
    };

    // Validar campos obligatorios
    if (!contacto.nameFirst || !contacto.nameLast || !contacto.email || !contacto.phone) {
      mostrarAlerta('error', '⚠️ Completa nombre, apellido, correo y teléfono.');
      return;
    }
    if (!contacto.phone.startsWith('+')) {
      mostrarAlerta('error', '⚠️ El teléfono debe incluir el código de país. Ej: +52.4771234567');
      return;
    }

    const btn = document.getElementById('btnRegistrar');
    btn.disabled = true;
    btn.textContent = '⏳ Registrando dominio…';
    ocultarAlertas();

    try {
      const resp = await fetch('ajax/registrar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ domain: dominioSeleccionado, contacto, csrf }),
      });
      const data = await resp.json();

      if (data.success) {
        mostrarAlerta('success',
          `✅ ¡Dominio <strong>${dominioSeleccionado}</strong> registrado exitosamente! Redirigiendo…`);
        setTimeout(() => { window.location.href = '../dashboard.php'; }, 2500);
      } else {
        mostrarAlerta('error', '❌ ' + (data.message || 'Error al registrar el dominio. Intenta de nuevo.'));
        btn.disabled = false;
        btn.textContent = '🌐 Registrar dominio';
      }
    } catch(e) {
      mostrarAlerta('error', '❌ Error de conexión. Verifica tu internet e intenta de nuevo.');
      btn.disabled = false;
      btn.textContent = '🌐 Registrar dominio';
    }
  }

  function mostrarAlerta(tipo, msg) {
    ocultarAlertas();
    const el = document.getElementById('alert' + tipo.charAt(0).toUpperCase() + tipo.slice(1));
    if (el) { el.innerHTML = msg; el.style.display = 'block'; }
  }
  function ocultarAlertas() {
    ['alertError','alertSuccess','alertWarning'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = 'none';
    });
  }
</script>
</body>
</html>
