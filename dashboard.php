<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/session.php';
requireLogin();   // redirige a login si no hay sesión

$nombre  = htmlspecialchars($_SESSION['doctor_nombre']);
$email   = htmlspecialchars($_SESSION['doctor_email']);
$paquete = $_SESSION['doctor_paquete'] ?? 'base';

// Datos del doctor desde BD
$db   = getDB();
$stmt = $db->prepare('SELECT * FROM doctores WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['doctor_id']]);
$doctor = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mi Panel — Doctores.Digital</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue:     #1a5fcb; --blue-dark: #0d3d8a; --blue-light: #e8f0fc;
      --teal:     #0bb5ae; --teal-light: #e0f7f6;
      --text:     #0d1526; --text-2: #3d4d6a; --text-3: #7284a3;
      --border:   #e2e8f4; --surface: #f6f9ff; --white: #ffffff;
      --green:    #10b981; --amber: #f59e0b; --purple: #8b5cf6;
    }
    body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--surface); color:var(--text); min-height:100vh; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

    /* ── SIDEBAR ── */
    .layout { display:grid; grid-template-columns:240px 1fr; min-height:100vh; }
    .sidebar {
      background:var(--white); border-right:1px solid var(--border);
      display:flex; flex-direction:column; padding:1.5rem 0;
      position:sticky; top:0; height:100vh; overflow-y:auto;
    }
    .sb-logo { padding:0 1.5rem 1.5rem; border-bottom:1px solid var(--border); margin-bottom:1rem; }
    .sb-logo img { height:30px; }
    .sb-nav { flex:1; padding:0 .75rem; display:flex; flex-direction:column; gap:.2rem; }
    .sb-item {
      display:flex; align-items:center; gap:.75rem;
      padding:.75rem 1rem; border-radius:10px;
      text-decoration:none; font-size:.9rem; font-weight:600;
      color:var(--text-2); transition:background .15s, color .15s;
    }
    .sb-item:hover { background:var(--surface); color:var(--text); }
    .sb-item.active { background:var(--blue-light); color:var(--blue); }
    .sb-item .si-icon { font-size:1.1rem; }
    .sb-bottom { padding:1rem 1.5rem; border-top:1px solid var(--border); margin-top:auto; }
    .sb-doctor { display:flex; align-items:center; gap:.75rem; }
    .sd-avatar {
      width:40px; height:40px; border-radius:50%;
      background:linear-gradient(135deg,var(--blue-dark),var(--blue));
      color:#fff; font-weight:800; font-size:.9rem;
      display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .sd-info .sd-name  { font-size:.85rem; font-weight:700; }
    .sd-info .sd-email { font-size:.75rem; color:var(--text-3); }
    .sb-logout {
      display:flex; align-items:center; gap:.5rem;
      margin-top:.8rem; padding:.6rem 1rem;
      background:none; border:1px solid var(--border);
      border-radius:8px; width:100%; cursor:pointer;
      font-family:inherit; font-size:.83rem; font-weight:600;
      color:var(--text-3); transition:border-color .2s, color .2s;
    }
    .sb-logout:hover { border-color:#fca5a5; color:#ef4444; }

    /* ── MAIN ── */
    .main { padding:2rem; overflow-y:auto; }
    .page-header { margin-bottom:2rem; animation:fadeUp .5s ease; }
    .page-header h1 { font-size:1.6rem; font-weight:800; letter-spacing:-.5px; }
    .page-header p  { font-size:.9rem; color:var(--text-3); margin-top:.3rem; }
    .paquete-badge {
      display:inline-flex; align-items:center; gap:.4rem;
      background:var(--blue-light); color:var(--blue-dark);
      font-size:.75rem; font-weight:700;
      padding:.3rem .8rem; border-radius:100px; margin-top:.6rem;
    }

    /* ── STATS CARDS ── */
    .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:2rem; }
    .stat-card {
      background:var(--white); border:1px solid var(--border);
      border-radius:16px; padding:1.5rem;
      animation:fadeUp .5s ease;
      transition:box-shadow .2s;
    }
    .stat-card:hover { box-shadow:0 4px 20px rgba(13,61,138,.08); }
    .sc-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:.8rem; }
    .sc-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
    .ic-blue   { background:var(--blue-light); }
    .ic-teal   { background:var(--teal-light); }
    .ic-green  { background:#d1fae5; }
    .ic-purple { background:#ede9fe; }
    .sc-label  { font-size:.78rem; font-weight:700; color:var(--text-3); }
    .sc-num    { font-size:1.8rem; font-weight:800; letter-spacing:-1px; }
    .sc-sub    { font-size:.78rem; color:var(--text-3); margin-top:.2rem; }

    /* ── SERVICES STATUS ── */
    .services-section { background:var(--white); border:1px solid var(--border); border-radius:16px; padding:1.5rem; margin-bottom:2rem; animation:fadeUp .6s ease; }
    .section-title { font-size:1rem; font-weight:800; margin-bottom:1.2rem; }
    .service-list  { display:flex; flex-direction:column; gap:.7rem; }
    .service-row {
      display:flex; align-items:center; justify-content:space-between;
      padding:.8rem 1rem; border-radius:10px; background:var(--surface);
      border:1px solid var(--border);
    }
    .sr-left  { display:flex; align-items:center; gap:.75rem; }
    .sr-icon  { font-size:1.1rem; }
    .sr-name  { font-size:.9rem; font-weight:600; }
    .status-badge {
      font-size:.72rem; font-weight:700; padding:.25rem .7rem;
      border-radius:100px;
    }
    .status-active   { background:#d1fae5; color:#065f46; }
    .status-pending  { background:#fef3c7; color:#92400e; }
    .status-inactive { background:var(--surface); color:var(--text-3); border:1px solid var(--border); }

    /* ── PROFILE CARD ── */
    .profile-card { background:var(--white); border:1px solid var(--border); border-radius:16px; padding:1.5rem; animation:fadeUp .7s ease; }
    .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem; }
    .pf-field label { display:block; font-size:.75rem; font-weight:700; color:var(--text-3); margin-bottom:.3rem; }
    .pf-field span  { font-size:.93rem; font-weight:600; }

    @media(max-width:900px) {
      .layout { grid-template-columns:1fr; }
      .sidebar { display:none; }
      .stats-grid { grid-template-columns:1fr 1fr; }
    }
    @media(max-width:500px) { .stats-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<div class="layout">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar">
    <div class="sb-logo">
      <img src="logo.png" alt="Doctores.Digital" />
    </div>
    <nav class="sb-nav">
      <a href="dashboard.php" class="sb-item active"><span class="si-icon">🏠</span> Mi Panel</a>
      <a href="#"             class="sb-item"><span class="si-icon">📅</span> Citas</a>
      <a href="#"             class="sb-item"><span class="si-icon">📊</span> Estadísticas</a>
      <a href="#"             class="sb-item"><span class="si-icon">📱</span> Redes Sociales</a>
      <a href="#"             class="sb-item"><span class="si-icon">✍️</span> Mis Artículos</a>
      <a href="#"             class="sb-item"><span class="si-icon">⚙️</span> Configuración</a>
    </nav>
    <div class="sb-bottom">
      <div class="sb-doctor">
        <div class="sd-avatar"><?= strtoupper(substr($nombre, 3, 1) ?: substr($nombre, 0, 1)) ?></div>
        <div class="sd-info">
          <div class="sd-name"><?= $nombre ?></div>
          <div class="sd-email"><?= $email ?></div>
        </div>
      </div>
      <button class="sb-logout" onclick="window.location.href='logout.php'">
        🚪 Cerrar sesión
      </button>
    </div>
  </aside>

  <!-- ══ MAIN ══ -->
  <main class="main">

    <div class="page-header">
      <h1>Bienvenido, <?= $nombre ?> 👋</h1>
      <p>Aquí está el resumen de tu presencia digital hoy</p>
      <div class="paquete-badge">
        ⭐ Paquete <?= ucfirst($paquete) ?> activo
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="sc-top">
          <div class="sc-icon ic-blue">👁️</div>
          <span class="sc-label">VISTAS HOY</span>
        </div>
        <div class="sc-num">—</div>
        <div class="sc-sub">Perfil de Google Maps</div>
      </div>
      <div class="stat-card">
        <div class="sc-top">
          <div class="sc-icon ic-teal">📅</div>
          <span class="sc-label">CITAS</span>
        </div>
        <div class="sc-num">—</div>
        <div class="sc-sub">Este mes</div>
      </div>
      <div class="stat-card">
        <div class="sc-top">
          <div class="sc-icon ic-green">📱</div>
          <span class="sc-label">SEGUIDORES</span>
        </div>
        <div class="sc-num">—</div>
        <div class="sc-sub">Total en redes</div>
      </div>
      <div class="stat-card">
        <div class="sc-top">
          <div class="sc-icon ic-purple">⭐</div>
          <span class="sc-label">RESEÑAS</span>
        </div>
        <div class="sc-num">—</div>
        <div class="sc-sub">Calificación promedio</div>
      </div>
    </div>

    <!-- Servicios activos -->
    <div class="services-section">
      <div class="section-title">Estado de tus servicios</div>
      <div class="service-list">
        <div class="service-row">
          <div class="sr-left"><span class="sr-icon">🌐</span><span class="sr-name">Dominio web profesional</span></div>
          <span class="status-badge status-active">✓ Activo</span>
        </div>
        <div class="service-row">
          <div class="sr-left"><span class="sr-icon">📧</span><span class="sr-name">Correo personalizado</span></div>
          <span class="status-badge status-active">✓ Activo</span>
        </div>
        <div class="service-row">
          <div class="sr-left"><span class="sr-icon">🗺️</span><span class="sr-name">Google Maps</span></div>
          <span class="status-badge status-pending">⏳ En configuración</span>
        </div>
        <div class="service-row">
          <div class="sr-left"><span class="sr-icon">📅</span><span class="sr-name">Sistema de citas online</span></div>
          <span class="status-badge status-active">✓ Activo</span>
        </div>
        <?php if (in_array($paquete, ['pro','branded'])): ?>
        <div class="service-row">
          <div class="sr-left"><span class="sr-icon">📱</span><span class="sr-name">Redes sociales</span></div>
          <span class="status-badge status-active">✓ Activo</span>
        </div>
        <?php else: ?>
        <div class="service-row">
          <div class="sr-left"><span class="sr-icon">📱</span><span class="sr-name">Redes sociales</span></div>
          <span class="status-badge status-inactive">No incluido</span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Perfil -->
    <div class="profile-card">
      <div class="section-title">Mi perfil</div>
      <div class="profile-grid">
        <div class="pf-field"><label>Nombre</label><span><?= $nombre ?></span></div>
        <div class="pf-field"><label>Correo</label><span><?= $email ?></span></div>
        <div class="pf-field"><label>Especialidad</label><span><?= htmlspecialchars($doctor['especialidad'] ?? '—') ?></span></div>
        <div class="pf-field"><label>Ciudad</label><span><?= htmlspecialchars($doctor['ciudad'] ?? '—') ?></span></div>
        <div class="pf-field"><label>Paquete</label><span>Paquete <?= ucfirst($paquete) ?></span></div>
        <div class="pf-field"><label>Último acceso</label><span><?= $doctor['last_login'] ? date('d/m/Y H:i', strtotime($doctor['last_login'])) : 'Hoy' ?></span></div>
      </div>
    </div>

  </main>
</div>
</body>
</html>
