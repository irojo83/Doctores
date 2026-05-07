<?php
// ─────────────────────────────────────────
//  Doctores.Digital — Login con PHP PDO
// ─────────────────────────────────────────
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/session.php';

// Si ya está logueado, ir directo al dashboard
if (!empty($_SESSION['doctor_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = false;

// ── Mensajes de redirección externos
$msgMap = [
    'session'  => 'Tu sesión expiró. Inicia sesión nuevamente.',
    'logout'   => 'Sesión cerrada correctamente.',
    'required' => 'Necesitas iniciar sesión para acceder.',
];
$infoMsg = $msgMap[$_GET['msg'] ?? ''] ?? '';

// ── Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (!validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Error de seguridad. Recarga la página e intenta de nuevo.';
    } else {
        $nombre   = trim($_POST['nombre']   ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // Validación básica
        if (!$nombre || !$email || !$password) {
            $error = 'Por favor completa todos los campos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico no es válido.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            // Buscar doctor por email
            $db   = getDB();
            $stmt = $db->prepare('SELECT * FROM doctores WHERE email = ? AND activo = 1 LIMIT 1');
            $stmt->execute([$email]);
            $doctor = $stmt->fetch();

            if ($doctor && password_verify($password, $doctor['password'])) {
                // ¡Credenciales correctas!

                // Verificar que el nombre coincida (case-insensitive)
                if (mb_strtolower(trim($doctor['nombre'])) !== mb_strtolower($nombre)) {
                    $error = 'El nombre no coincide con el registrado para ese correo.';
                } else {
                    // Actualizar last_login
                    $upd = $db->prepare('UPDATE doctores SET last_login = NOW() WHERE id = ?');
                    $upd->execute([$doctor['id']]);

                    // Guardar en sesiones (auditoría)
                    try {
                        $ins = $db->prepare(
                            'INSERT INTO sesiones (doctor_id, ip, user_agent) VALUES (?,?,?)'
                        );
                        $ins->execute([
                            $doctor['id'],
                            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
                        ]);
                    } catch (PDOException $e) { /* no bloquear el login */ }

                    loginDoctor($doctor);
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                // Pequeño delay para dificultar fuerza bruta
                usleep(400000); // 0.4 s
                $error = 'Credenciales incorrectas. Verifica tu nombre, correo y contraseña.';
            }
        }
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Iniciar sesión — Doctores.Digital</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue:      #1a5fcb; --blue-dark: #0d3d8a;
      --teal:      #0bb5ae; --teal-light: #e0f7f6;
      --text:      #0d1526; --text-2: #3d4d6a; --text-3: #7284a3;
      --border:    #e2e8f4; --surface: #f6f9ff; --white: #ffffff;
      --error:     #ef4444; --success: #10b981;
    }
    html { height: 100%; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
      color: var(--text);
    }
    @keyframes gradMove { 0%,100%{ background-position:0% 50%; } 50%{ background-position:100% 50%; } }
    @keyframes float    { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-10px); } }
    @keyframes fadeUp   { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
    @keyframes pulse    { 0%,100% { box-shadow: 0 0 0 0 rgba(127,232,228,.5); } 70% { box-shadow: 0 0 0 10px rgba(127,232,228,0); } }
    @keyframes spin     { to { transform: rotate(360deg); } }

    /* ── LEFT PANEL ── */
    .left-panel {
      background: linear-gradient(145deg, #0d3d8a 0%, #1a5fcb 55%, #0bb5ae 100%);
      background-size: 200% 200%;
      animation: gradMove 8s ease infinite;
      display: flex; flex-direction: column;
      justify-content: space-between;
      padding: 2.5rem 3rem;
      position: relative; overflow: hidden;
    }
    .lp-blob { position:absolute; border-radius:50%; filter:blur(70px); pointer-events:none; }
    .lp-blob-1 { width:400px; height:400px; background:#7fe8e4; opacity:.12; top:-100px; right:-100px; }
    .lp-blob-2 { width:300px; height:300px; background:#4ab3f4; opacity:.10; bottom:-80px; left:-60px; }
    #ecgLogin { position:absolute; inset:0; width:100%; height:100%; pointer-events:none; opacity:.4; }
    .lp-logo { position:relative; z-index:1; }
    .lp-logo img { height:36px; filter:brightness(0) invert(1); }
    .lp-content { position:relative; z-index:1; animation:fadeUp .8s ease; }
    .lp-badge {
      display:inline-flex; align-items:center; gap:.5rem;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22);
      color:rgba(255,255,255,.9); font-size:.75rem; font-weight:700;
      letter-spacing:1.5px; text-transform:uppercase;
      padding:.4rem 1rem; border-radius:100px; margin-bottom:1.8rem;
      backdrop-filter:blur(6px);
    }
    .lp-dot { width:6px; height:6px; background:#7fe8e4; border-radius:50%; animation:pulse 2s infinite; }
    .lp-content h1 {
      font-size:clamp(1.8rem,3vw,2.6rem); font-weight:800;
      color:#fff; letter-spacing:-1px; line-height:1.15; margin-bottom:1rem;
    }
    .lp-content h1 em {
      font-style:normal;
      background:linear-gradient(90deg,#7fe8e4,#a8edea);
      -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    }
    .lp-content p { color:rgba(255,255,255,.72); font-size:.95rem; line-height:1.75; max-width:380px; }
    .lp-features { margin-top:2.5rem; display:flex; flex-direction:column; gap:.9rem; }
    .lp-feature  { display:flex; align-items:center; gap:.8rem; color:rgba(255,255,255,.78); font-size:.88rem; font-weight:500; }
    .lp-check {
      flex-shrink:0; width:22px; height:22px;
      background:rgba(127,232,228,.2); border:1px solid rgba(127,232,228,.4);
      border-radius:50%; display:flex; align-items:center; justify-content:center;
      font-size:.65rem; color:#7fe8e4;
    }
    .lp-stats { position:relative; z-index:1; display:flex; gap:1rem; }
    .lp-stat {
      background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
      backdrop-filter:blur(10px); border-radius:14px;
      padding:1rem 1.3rem; color:#fff; animation:float 4s ease-in-out infinite;
    }
    .lp-stat:nth-child(2) { animation-delay:.7s; }
    .lp-stat .ls-num  { font-size:1.4rem; font-weight:800; letter-spacing:-1px; color:#7fe8e4; }
    .lp-stat .ls-label{ font-size:.75rem; opacity:.7; margin-top:.1rem; }

    /* ── RIGHT PANEL ── */
    .right-panel {
      background:var(--white);
      display:flex; flex-direction:column;
      align-items:center; justify-content:center;
      padding:3rem 2rem; overflow-y:auto;
    }
    .login-box { width:100%; max-width:420px; animation:fadeUp .6s ease; }
    .login-header { margin-bottom:2rem; }
    .login-header h2 { font-size:1.75rem; font-weight:800; letter-spacing:-.8px; margin-bottom:.4rem; }
    .login-header p  { font-size:.9rem; color:var(--text-3); }
    .login-header a  { color:var(--blue); text-decoration:none; font-weight:600; }
    .login-header a:hover { text-decoration:underline; }

    /* Alerts */
    .alert {
      padding:.85rem 1rem; border-radius:10px; font-size:.875rem;
      margin-bottom:1.2rem; display:flex; align-items:flex-start; gap:.6rem;
    }
    .alert-error   { background:#fef2f2; border:1px solid #fecaca; color:var(--error); }
    .alert-info    { background:#eff6ff; border:1px solid #bfdbfe; color:var(--blue-dark); }
    .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:var(--success); }

    /* Form */
    .form-group { margin-bottom:1.1rem; }
    .form-group label { display:block; font-size:.82rem; font-weight:700; color:var(--text-2); margin-bottom:.45rem; }
    .input-wrap { position:relative; }
    .input-icon { position:absolute; left:1rem; top:50%; transform:translateY(-50%); font-size:1rem; pointer-events:none; }
    .form-group input {
      width:100%; padding:.85rem 1rem .85rem 2.8rem;
      border:1.5px solid var(--border); border-radius:12px;
      font-family:inherit; font-size:.95rem; color:var(--text);
      background:var(--surface); outline:none;
      transition:border-color .2s, box-shadow .2s, background .2s;
    }
    .form-group input:focus {
      border-color:var(--blue);
      box-shadow:0 0 0 3px rgba(26,95,203,.1);
      background:var(--white);
    }
    .toggle-pass {
      position:absolute; right:1rem; top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer; font-size:1.1rem;
      color:var(--text-3); padding:0; transition:color .2s; line-height:1;
    }
    .toggle-pass:hover { color:var(--blue); }
    .form-options {
      display:flex; align-items:center; justify-content:space-between;
      margin-bottom:1.5rem; margin-top:-.2rem;
    }
    .remember { display:flex; align-items:center; gap:.5rem; font-size:.83rem; color:var(--text-2); cursor:pointer; }
    .remember input { width:16px; height:16px; accent-color:var(--blue); cursor:pointer; }
    .forgot-link { font-size:.83rem; color:var(--blue); text-decoration:none; font-weight:600; }
    .forgot-link:hover { text-decoration:underline; }
    .btn-login {
      width:100%; padding:.95rem;
      background:linear-gradient(135deg,var(--blue-dark),var(--blue));
      color:var(--white); font-family:inherit; font-size:1rem; font-weight:700;
      border:none; border-radius:12px; cursor:pointer;
      transition:opacity .2s, transform .15s, box-shadow .2s;
      box-shadow:0 4px 20px rgba(26,95,203,.3);
      display:flex; align-items:center; justify-content:center; gap:.5rem;
    }
    .btn-login:hover { opacity:.92; transform:translateY(-1px); box-shadow:0 8px 28px rgba(26,95,203,.4); }
    .btn-login:active { transform:translateY(0); }
    .btn-login.loading { opacity:.7; pointer-events:none; }
    .spinner { display:none; width:18px; height:18px; border:2px solid rgba(255,255,255,.35); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; }
    .btn-login.loading .spinner { display:block; }
    .btn-login.loading .btn-label { display:none; }

    .back-home { text-align:center; margin-top:1.8rem; font-size:.83rem; color:var(--text-3); }
    .back-home a { color:var(--blue); text-decoration:none; font-weight:600; }
    .back-home a:hover { text-decoration:underline; }

    @media (max-width:768px) {
      body { grid-template-columns:1fr; }
      .left-panel { display:none; }
      .right-panel { min-height:100vh; padding:2.5rem 1.5rem; }
    }
  </style>
</head>
<body>

<!-- ══ LEFT PANEL ══ -->
<div class="left-panel">
  <canvas id="ecgLogin"></canvas>
  <div class="lp-blob lp-blob-1"></div>
  <div class="lp-blob lp-blob-2"></div>
  <div class="lp-logo"><img src="logo.png" alt="Doctores.Digital" /></div>
  <div class="lp-content">
    <div class="lp-badge"><span class="lp-dot"></span> Panel del Doctor</div>
    <h1>Bienvenido a tu<br><em>consultorio digital</em></h1>
    <p>Gestiona tu presencia en línea, revisa estadísticas y mantén tu consultorio visible para los pacientes que te necesitan.</p>
    <div class="lp-features">
      <div class="lp-feature"><span class="lp-check">✓</span> Citas y recordatorios automáticos</div>
      <div class="lp-feature"><span class="lp-check">✓</span> Estadísticas de tu perfil de Google Maps</div>
      <div class="lp-feature"><span class="lp-check">✓</span> Reportes mensuales de resultados</div>
      <div class="lp-feature"><span class="lp-check">✓</span> Gestión de tus redes sociales</div>
    </div>
  </div>
  <div class="lp-stats">
    <div class="lp-stat"><div class="ls-num">88%</div><div class="ls-label">búsquedas locales<br>generan visita</div></div>
    <div class="lp-stat"><div class="ls-num">24/7</div><div class="ls-label">citas disponibles<br>para tus pacientes</div></div>
  </div>
</div>

<!-- ══ RIGHT PANEL ══ -->
<div class="right-panel">
  <div class="login-box">

    <div class="login-header">
      <h2>Iniciar sesión</h2>
      <p>¿Aún no tienes cuenta? <a href="index.html#contacto">Contáctanos</a></p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php elseif ($infoMsg): ?>
      <div class="alert <?= str_contains($infoMsg,'cerrada') ? 'alert-success' : 'alert-info' ?>">
        ℹ️ <?= htmlspecialchars($infoMsg) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" onsubmit="startLoading()" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>" />

      <div class="form-group">
        <label for="nombre">Nombre del doctor</label>
        <div class="input-wrap">
          <span class="input-icon">🩺</span>
          <input type="text" id="nombre" name="nombre"
                 placeholder="Dr. Juan Pérez" required autocomplete="name"
                 value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" />
        </div>
      </div>

      <div class="form-group">
        <label for="email">Correo electrónico</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input type="email" id="email" name="email"
                 placeholder="dr.perez@correo.com" required autocomplete="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
        </div>
      </div>

      <div class="form-group">
        <label for="password">Contraseña</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" id="password" name="password"
                 placeholder="••••••••" required autocomplete="current-password" />
          <button type="button" class="toggle-pass" id="togglePass" title="Mostrar/Ocultar">👁️</button>
        </div>
      </div>

      <div class="form-options">
        <label class="remember">
          <input type="checkbox" name="remember" id="remember" />
          Recordarme
        </label>
        <a href="forgot.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">
        <div class="spinner"></div>
        <span class="btn-label">Entrar a mi panel →</span>
      </button>
    </form>

    <div class="back-home">
      <a href="index.html">← Volver al sitio principal</a>
    </div>

  </div>
</div>

<script>
  function startLoading() {
    document.getElementById('loginBtn').classList.add('loading');
  }

  // Toggle contraseña
  document.getElementById('togglePass').addEventListener('click', function () {
    const inp = document.getElementById('password');
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    this.textContent = show ? '🙈' : '👁️';
  });

  // ECG panel izquierdo
  (function () {
    const canvas = document.getElementById('ecgLogin');
    if (!canvas) return;
    const panel = canvas.parentElement;
    const ctx   = canvas.getContext('2d');
    let W, H, baseY;
    const buf = new Float32Array(2000);
    function resize() { W = canvas.width = panel.offsetWidth; H = canvas.height = panel.offsetHeight; baseY = H * .72; }
    resize();
    window.addEventListener('resize', resize, { passive: true });
    function ecg(t) {
      t = ((t%1)+1)%1;
      if(t<.08) return Math.sin(t/.08*Math.PI)*.13;
      if(t<.36) return 0;
      if(t<.40) return -.18;
      if(t<.44) return 1.0;
      if(t<.48) return -.28;
      if(t<.52) return 0;
      if(t<.65) return Math.sin((t-.52)/.13*Math.PI)*.22;
      return 0;
    }
    const SPEED=2, CYCLE=180, ERASE=50;
    let scan=0, phase=0;
    function frame() {
      for(let s=0;s<Math.ceil(SPEED);s++){
        const ix=Math.round(scan)%W;
        phase+=1/CYCLE;
        buf[ix]=baseY-ecg(phase)*Math.min(H*.15,80);
        scan=(scan+1)%W;
      }
      const head=Math.round(scan)%W;
      ctx.clearRect(0,0,W,H);
      ctx.save();
      ctx.lineWidth=2; ctx.lineCap='round'; ctx.lineJoin='round';
      ctx.strokeStyle='rgba(127,232,228,.85)';
      ctx.shadowColor='#7fe8e4'; ctx.shadowBlur=12;
      const startX=(head+ERASE)%W;
      function seg(x0,x1){if(x1<=x0)return;ctx.beginPath();ctx.moveTo(x0,buf[x0]);for(let x=x0+1;x<=x1;x++)ctx.lineTo(x,buf[x%W]);ctx.stroke();}
      if(startX<head){seg(startX,head-1);}else{seg(startX,W-1);seg(0,head-1);}
      ctx.beginPath();ctx.arc(head,buf[(head-1+W)%W],4,0,Math.PI*2);
      ctx.fillStyle='#7fe8e4';ctx.shadowBlur=20;ctx.fill();
      ctx.restore();
      requestAnimationFrame(frame);
    }
    frame();
  })();
</script>

</body>
</html>
