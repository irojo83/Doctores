<?php
/**
 * ============================================================
 *  Doctores.Digital — Instalador / Actualizador de BD
 *  ============================================================
 *  • Primera vez: crea todas las tablas e inserta datos base
 *  • Siguientes veces: solo aplica migraciones nuevas (pendientes)
 *  • Para agregar cambios futuros: añade entradas a $MIGRATIONS
 *
 *  ⚠️  SEGURIDAD: Elimina o protege este archivo después de usarlo
 *      Renómbralo o borra el acceso desde cPanel File Manager
 * ============================================================
 */

// ── Clave de acceso al instalador ──────────────────────────
// Cambia esto antes de subir al servidor
define('INSTALL_KEY', 'DrDigital2025!');

// ── Configuración de BD ─────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'doctores');
define('DB_USER',    'doctor');
define('DB_PASS',    'rG5;Ku5!4!ma]1Vq');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
//  MIGRACIONES — Agrega aquí los cambios futuros
//  • Nunca modifiques una migración ya aplicada
//  • Solo agrega nuevas al final con el siguiente número
// ============================================================
$MIGRATIONS = [

    '001_create_migrations_table' => [
        'desc' => 'Tabla de control de migraciones',
        'sql'  => ["
            CREATE TABLE IF NOT EXISTS `_migraciones` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `nombre`     VARCHAR(120) NOT NULL UNIQUE,
                `aplicada_en` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        "],
    ],

    '002_create_doctores_table' => [
        'desc' => 'Tabla principal de doctores',
        'sql'  => ["
            CREATE TABLE IF NOT EXISTS `doctores` (
                `id`           INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
                `nombre`       VARCHAR(150)    NOT NULL,
                `email`        VARCHAR(150)    NOT NULL UNIQUE,
                `password`     VARCHAR(255)    NOT NULL,
                `especialidad` VARCHAR(100)    DEFAULT NULL,
                `ciudad`       VARCHAR(100)    DEFAULT NULL,
                `telefono`     VARCHAR(30)     DEFAULT NULL,
                `paquete`      ENUM('base','pro','branded') DEFAULT 'base',
                `activo`       TINYINT(1)      NOT NULL DEFAULT 1,
                `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_login`   DATETIME        DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        "],
    ],

    '003_create_sesiones_table' => [
        'desc' => 'Tabla de auditoría de sesiones',
        'sql'  => ["
            CREATE TABLE IF NOT EXISTS `sesiones` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `doctor_id`  INT UNSIGNED NOT NULL,
                `ip`         VARCHAR(45)  NOT NULL,
                `user_agent` VARCHAR(300) DEFAULT NULL,
                `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`doctor_id`) REFERENCES `doctores`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        "],
    ],

    '004_insert_demo_doctor' => [
        'desc' => 'Doctor de prueba (demo@doctores.digital / Doctor123)',
        'sql'  => ["
            INSERT IGNORE INTO `doctores`
                (`nombre`, `email`, `password`, `especialidad`, `ciudad`, `paquete`)
            VALUES (
                'Dr. Demo',
                'demo@doctores.digital',
                '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'Medicina General',
                'Ciudad de México',
                'pro'
            )
        "],
    ],

    // ── ESPACIO PARA MIGRACIONES FUTURAS ──────────────────
    // Ejemplo — agregar columna 'foto_url' a doctores:
    //
    // '005_add_foto_url' => [
    //     'desc' => 'Agregar columna foto_url a doctores',
    //     'sql'  => [
    //         "ALTER TABLE `doctores` ADD COLUMN IF NOT EXISTS
    //          `foto_url` VARCHAR(300) DEFAULT NULL AFTER `telefono`"
    //     ],
    // ],
    //
    // Ejemplo — crear tabla de citas:
    //
    // '006_create_citas_table' => [
    //     'desc' => 'Tabla de citas',
    //     'sql'  => ["
    //         CREATE TABLE IF NOT EXISTS `citas` (
    //             `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    //             `doctor_id`  INT UNSIGNED NOT NULL,
    //             `paciente`   VARCHAR(150) NOT NULL,
    //             `telefono`   VARCHAR(30)  DEFAULT NULL,
    //             `fecha`      DATETIME     NOT NULL,
    //             `estado`     ENUM('pendiente','confirmada','cancelada') DEFAULT 'pendiente',
    //             `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    //             FOREIGN KEY (`doctor_id`) REFERENCES `doctores`(`id`) ON DELETE CASCADE
    //         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    //     "],
    // ],

];

// ============================================================
//  LÓGICA DEL INSTALADOR
// ============================================================

session_start();
$step     = 'auth';   // auth | preview | run | done
$dbOk     = false;
$pdo      = null;
$results  = [];
$error    = '';

// ── 1. Verificar clave de acceso ────────────────────────────
$authed = ($_SESSION['install_auth'] ?? false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_key'])) {
    if ($_POST['install_key'] === INSTALL_KEY) {
        $_SESSION['install_auth'] = true;
        $authed = true;
    } else {
        $error = 'Clave incorrecta.';
    }
}

// ── 2. Conectar a BD ────────────────────────────────────────
if ($authed) {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $dbOk = true;
        $step = 'preview';
    } catch (PDOException $e) {
        $error = 'No se pudo conectar a la base de datos: ' . $e->getMessage();
        $step  = 'auth';
    }
}

// ── 3. Verificar migraciones aplicadas ──────────────────────
$applied = [];
if ($dbOk) {
    try {
        $rows = $pdo->query("SELECT `nombre` FROM `_migraciones`")->fetchAll(PDO::FETCH_COLUMN);
        $applied = array_flip($rows);
    } catch (PDOException $e) {
        // La tabla aún no existe, es la primera instalación
        $applied = [];
    }
}

$pending = array_diff_key($MIGRATIONS, $applied);
$done    = array_intersect_key($MIGRATIONS, $applied);

// ── 4. Ejecutar migraciones ─────────────────────────────────
if ($dbOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    $step = 'run';
    foreach ($MIGRATIONS as $name => $migration) {
        if (isset($applied[$name])) {
            $results[$name] = ['status' => 'skip', 'msg' => 'Ya aplicada'];
            continue;
        }
        try {
            $pdo->beginTransaction();
            foreach ($migration['sql'] as $sql) {
                $pdo->exec(trim($sql));
                // MySQL hace commit implícito en DDL (CREATE/ALTER/DROP).
                // Si ya no hay transacción activa, abrimos una nueva para
                // poder registrar la migración de forma segura.
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                }
            }
            // Registrar en tabla de migraciones
            $stmt = $pdo->prepare("INSERT INTO `_migraciones` (`nombre`) VALUES (?)");
            $stmt->execute([$name]);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $results[$name] = ['status' => 'ok', 'msg' => 'Aplicada correctamente'];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $results[$name] = ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }
    $step = 'done';
}

// ── Helper ──────────────────────────────────────────────────
function countPending(array $results): int {
    return count(array_filter($results, fn($r) => $r['status'] === 'ok'));
}
function countErrors(array $results): int {
    return count(array_filter($results, fn($r) => $r['status'] === 'error'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Instalador — Doctores.Digital</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue:      #1a5fcb; --blue-dark: #0d3d8a; --blue-light: #e8f0fc;
      --teal:      #0bb5ae; --teal-light: #e0f7f6;
      --green:     #10b981; --green-light: #d1fae5;
      --amber:     #f59e0b; --amber-light: #fef3c7;
      --red:       #ef4444; --red-light: #fee2e2;
      --text:      #0d1526; --text-2: #3d4d6a; --text-3: #7284a3;
      --border:    #e2e8f4; --surface: #f6f9ff; --white: #ffffff;
    }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--surface);
      color: var(--text);
      min-height: 100vh;
      padding: 2rem 1rem;
    }
    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    @keyframes gradMove { 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }
    @keyframes spin { to{transform:rotate(360deg)} }

    .container { max-width: 740px; margin: 0 auto; }

    /* Header */
    .inst-header {
      background: linear-gradient(135deg, #0d3d8a, #1a5fcb, #0bb5ae);
      background-size: 200% 200%;
      animation: gradMove 6s ease infinite;
      border-radius: 20px;
      padding: 2rem 2.5rem;
      color: #fff;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    .inst-header img { height: 32px; filter: brightness(0) invert(1); }
    .inst-header-text h1 { font-size: 1.4rem; font-weight: 800; letter-spacing: -.5px; }
    .inst-header-text p  { font-size: .85rem; opacity: .75; margin-top: .2rem; }

    /* Warning */
    .warning-bar {
      background: var(--amber-light);
      border: 1px solid #fcd34d;
      border-radius: 12px;
      padding: 1rem 1.3rem;
      font-size: .85rem;
      color: #92400e;
      margin-bottom: 1.5rem;
      display: flex;
      gap: .75rem;
      align-items: flex-start;
    }
    .warning-bar strong { font-weight: 700; }

    /* Card */
    .card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 1.2rem;
      animation: fadeUp .4s ease;
    }
    .card-title {
      font-size: 1rem;
      font-weight: 800;
      margin-bottom: 1.2rem;
      display: flex;
      align-items: center;
      gap: .6rem;
    }

    /* DB status */
    .db-status {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.2rem;
      border-radius: 10px;
      font-size: .9rem;
      font-weight: 600;
    }
    .db-ok    { background: var(--green-light); color: #065f46; border: 1px solid #6ee7b7; }
    .db-error { background: var(--red-light);   color: #991b1b; border: 1px solid #fca5a5; }

    /* Migration list */
    .migration-list { display: flex; flex-direction: column; gap: .6rem; }
    .mig-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: .85rem 1rem;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: var(--surface);
      font-size: .88rem;
    }
    .mig-left  { display: flex; align-items: center; gap: .7rem; }
    .mig-name  { font-weight: 600; font-family: monospace; font-size: .82rem; color: var(--text-3); }
    .mig-desc  { font-size: .88rem; font-weight: 600; }
    .badge {
      font-size: .72rem; font-weight: 700;
      padding: .25rem .75rem; border-radius: 100px;
      white-space: nowrap; flex-shrink: 0;
    }
    .badge-pending  { background: var(--blue-light);  color: var(--blue-dark); }
    .badge-applied  { background: var(--green-light);  color: #065f46; }
    .badge-ok       { background: var(--green-light);  color: #065f46; }
    .badge-skip     { background: var(--surface);      color: var(--text-3); border: 1px solid var(--border); }
    .badge-error    { background: var(--red-light);    color: #991b1b; }

    /* Count summary */
    .summary-row {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .sum-box {
      flex: 1;
      min-width: 120px;
      border-radius: 12px;
      padding: 1rem 1.2rem;
      text-align: center;
      border: 1px solid var(--border);
    }
    .sum-box .sb-num   { font-size: 2rem; font-weight: 800; letter-spacing: -1px; }
    .sum-box .sb-label { font-size: .78rem; color: var(--text-3); margin-top: .2rem; }
    .sum-blue   { background: var(--blue-light); }
    .sum-blue   .sb-num { color: var(--blue-dark); }
    .sum-green  { background: var(--green-light); }
    .sum-green  .sb-num { color: #065f46; }
    .sum-amber  { background: var(--amber-light); }
    .sum-amber  .sb-num { color: #92400e; }

    /* Forms & buttons */
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: .82rem; font-weight: 700; color: var(--text-2); margin-bottom: .4rem; }
    .form-group input[type="password"],
    .form-group input[type="text"] {
      width: 100%; padding: .85rem 1rem;
      border: 1.5px solid var(--border); border-radius: 10px;
      font-family: inherit; font-size: .95rem; outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-group input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(26,95,203,.1); }

    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
      font-family: inherit; font-weight: 700; font-size: 1rem;
      padding: .9rem 2rem; border-radius: 12px; border: none;
      cursor: pointer; text-decoration: none;
      transition: opacity .2s, transform .15s, box-shadow .2s;
    }
    .btn:hover { opacity: .9; transform: translateY(-1px); }
    .btn-primary {
      background: linear-gradient(135deg, var(--blue-dark), var(--blue));
      color: #fff; width: 100%;
      box-shadow: 0 4px 20px rgba(26,95,203,.3);
    }
    .btn-primary:hover { box-shadow: 0 8px 28px rgba(26,95,203,.4); }
    .btn-success {
      background: linear-gradient(135deg, #065f46, var(--green));
      color: #fff; width: 100%;
      box-shadow: 0 4px 20px rgba(16,185,129,.3);
    }
    .btn-outline {
      background: var(--white); color: var(--blue);
      border: 1.5px solid var(--blue);
    }

    .alert { padding: .85rem 1rem; border-radius: 10px; font-size: .88rem; margin-bottom: 1rem; }
    .alert-error   { background: var(--red-light);   border: 1px solid #fca5a5; color: #991b1b; }
    .alert-success { background: var(--green-light); border: 1px solid #6ee7b7; color: #065f46; }

    .empty-state { text-align: center; padding: 2rem; color: var(--text-3); font-size: .9rem; }
    .empty-state .es-icon { font-size: 2.5rem; margin-bottom: .5rem; }

    .result-msg { font-size: .8rem; color: var(--text-3); }
    .done-header { text-align: center; padding: 1.5rem 0 1rem; }
    .done-header .dh-icon { font-size: 3.5rem; margin-bottom: .5rem; }
    .done-header h2 { font-size: 1.4rem; font-weight: 800; margin-bottom: .3rem; }
    .done-header p  { font-size: .9rem; color: var(--text-2); }

    .step-badge {
      background: var(--blue-light); color: var(--blue-dark);
      font-size: .72rem; font-weight: 800; letter-spacing: 1px;
      text-transform: uppercase; padding: .2rem .7rem; border-radius: 100px;
    }

    .delete-reminder {
      background: #fef2f2; border: 2px solid #fca5a5;
      border-radius: 12px; padding: 1.2rem 1.5rem;
      font-size: .88rem; color: #991b1b; margin-top: 1.2rem;
    }
    .delete-reminder strong { font-weight: 800; display: block; margin-bottom: .3rem; }

    code { background: var(--surface); border: 1px solid var(--border); padding: .15rem .5rem; border-radius: 5px; font-size: .85rem; }

    @media(max-width:480px) {
      .inst-header { flex-direction: column; text-align: center; }
      .summary-row { flex-direction: column; }
    }
  </style>
</head>
<body>
<div class="container">

  <!-- Header -->
  <div class="inst-header">
    <div class="inst-header-text">
      <h1>⚙️ Instalador / Actualizador</h1>
      <p>Sistema de migraciones de base de datos · Doctores.Digital</p>
    </div>
    <img src="logo.png" alt="Doctores.Digital" />
  </div>

  <!-- Warning siempre visible -->
  <div class="warning-bar">
    ⚠️ <div><strong>Archivo de uso restringido.</strong>
    Elimina o renombra este archivo desde cPanel → File Manager
    después de completar la instalación o actualización.</div>
  </div>

  <!-- ══════════════════════════════════════════
       PASO 1: AUTENTICACIÓN
  ══════════════════════════════════════════ -->
  <?php if (!$authed): ?>
  <div class="card">
    <div class="card-title">🔐 Acceso al instalador</div>
    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <p style="color:var(--text-2);font-size:.9rem;margin-bottom:1.2rem;">
      Ingresa la clave de instalación para continuar.
      Esta clave está definida en la constante <code>INSTALL_KEY</code> del archivo.
    </p>
    <form method="POST">
      <div class="form-group">
        <label for="install_key">Clave de instalación</label>
        <input type="password" name="install_key" id="install_key"
               placeholder="••••••••••••" required autofocus />
      </div>
      <button type="submit" class="btn btn-primary">Verificar acceso →</button>
    </form>
  </div>

  <!-- ══════════════════════════════════════════
       PASO 2: PREVIEW (GET)
  ══════════════════════════════════════════ -->
  <?php elseif ($step === 'preview'): ?>

  <!-- Estado de la conexión -->
  <div class="card">
    <div class="card-title">🗄️ Conexión a la base de datos</div>
    <?php if ($dbOk): ?>
      <div class="db-status db-ok">
        ✅ Conexión exitosa a <strong><?= DB_NAME ?></strong>
        en <strong><?= DB_HOST ?></strong> como usuario <strong><?= DB_USER ?></strong>
      </div>
    <?php else: ?>
      <div class="db-status db-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
  </div>

  <?php if ($dbOk): ?>

  <!-- Resumen -->
  <div class="summary-row">
    <div class="sum-box sum-blue">
      <div class="sb-num"><?= count($MIGRATIONS) ?></div>
      <div class="sb-label">Total de migraciones</div>
    </div>
    <div class="sum-box sum-green">
      <div class="sb-num"><?= count($done) ?></div>
      <div class="sb-label">Ya aplicadas</div>
    </div>
    <div class="sum-box sum-amber">
      <div class="sb-num"><?= count($pending) ?></div>
      <div class="sb-label">Pendientes</div>
    </div>
  </div>

  <!-- Migraciones pendientes -->
  <div class="card">
    <div class="card-title">
      <span class="step-badge">Pendientes</span>
      🔵 Por aplicar (<?= count($pending) ?>)
    </div>
    <?php if (empty($pending)): ?>
      <div class="empty-state">
        <div class="es-icon">🎉</div>
        <div>¡Todo está actualizado! No hay migraciones pendientes.</div>
      </div>
    <?php else: ?>
      <div class="migration-list">
        <?php foreach ($pending as $name => $mig): ?>
        <div class="mig-row">
          <div class="mig-left">
            <span>🔵</span>
            <div>
              <div class="mig-desc"><?= htmlspecialchars($mig['desc']) ?></div>
              <div class="mig-name"><?= htmlspecialchars($name) ?></div>
            </div>
          </div>
          <span class="badge badge-pending">Pendiente</span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Migraciones ya aplicadas -->
  <?php if (!empty($done)): ?>
  <div class="card">
    <div class="card-title">
      <span class="step-badge" style="background:var(--green-light);color:#065f46;">Historial</span>
      ✅ Ya aplicadas (<?= count($done) ?>)
    </div>
    <div class="migration-list">
      <?php foreach ($done as $name => $mig): ?>
      <div class="mig-row">
        <div class="mig-left">
          <span>✅</span>
          <div>
            <div class="mig-desc"><?= htmlspecialchars($mig['desc']) ?></div>
            <div class="mig-name"><?= htmlspecialchars($name) ?></div>
          </div>
        </div>
        <span class="badge badge-applied">Aplicada</span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Botón ejecutar -->
  <?php if (!empty($pending)): ?>
  <div class="card">
    <div class="card-title">🚀 Ejecutar instalación / actualización</div>
    <p style="color:var(--text-2);font-size:.9rem;margin-bottom:1.2rem;">
      Se aplicarán <strong><?= count($pending) ?> migración(es) pendiente(s)</strong>.
      Las que ya están aplicadas se omitirán automáticamente.
    </p>
    <form method="POST">
      <button type="submit" name="run_migrations" value="1" class="btn btn-primary">
        ⚙️ Aplicar <?= count($pending) ?> migración(es) ahora
      </button>
    </form>
  </div>
  <?php else: ?>
  <div class="card" style="text-align:center;">
    <p style="color:var(--text-2);margin-bottom:1.2rem;font-size:.95rem;">
      La base de datos está completamente actualizada.
    </p>
    <a href="login.php" class="btn btn-outline" style="width:auto;display:inline-flex;">
      → Ir al login
    </a>
  </div>
  <?php endif; ?>

  <?php endif; // dbOk ?>

  <!-- ══════════════════════════════════════════
       PASO 3: RESULTADOS
  ══════════════════════════════════════════ -->
  <?php elseif ($step === 'done'): ?>

  <?php
    $okCount  = countPending($results);
    $errCount = countErrors($results);
  ?>

  <div class="card">
    <div class="done-header">
      <?php if ($errCount === 0): ?>
        <div class="dh-icon">🎉</div>
        <h2>¡Instalación completada!</h2>
        <p><?= $okCount ?> migración(es) aplicadas correctamente.</p>
      <?php else: ?>
        <div class="dh-icon">⚠️</div>
        <h2>Completado con errores</h2>
        <p><?= $okCount ?> aplicadas · <?= $errCount ?> con error</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Detalle de resultados -->
  <div class="card">
    <div class="card-title">📋 Detalle de migraciones</div>
    <div class="migration-list">
      <?php foreach ($results as $name => $result): ?>
      <div class="mig-row">
        <div class="mig-left">
          <span>
            <?= $result['status'] === 'ok'    ? '✅' : '' ?>
            <?= $result['status'] === 'skip'  ? '⏭️' : '' ?>
            <?= $result['status'] === 'error' ? '❌' : '' ?>
          </span>
          <div>
            <div class="mig-desc"><?= htmlspecialchars($MIGRATIONS[$name]['desc']) ?></div>
            <div class="mig-name"><?= htmlspecialchars($name) ?></div>
            <?php if ($result['status'] === 'error'): ?>
              <div class="result-msg" style="color:#ef4444;"><?= htmlspecialchars($result['msg']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <span class="badge badge-<?= $result['status'] ?>">
          <?= $result['status'] === 'ok'    ? '✓ Aplicada'    : '' ?>
          <?= $result['status'] === 'skip'  ? 'Ya existía'    : '' ?>
          <?= $result['status'] === 'error' ? 'Error'         : '' ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Acciones post-instalación -->
  <div class="card">
    <div class="card-title">🔗 Próximos pasos</div>
    <div style="display:flex;flex-direction:column;gap:.8rem;">
      <?php if ($errCount === 0): ?>
      <a href="login.php" class="btn btn-success">→ Ir al login</a>
      <?php endif; ?>
      <form method="GET" style="margin:0;">
        <button type="submit" class="btn btn-outline">↺ Ver estado actual</button>
      </form>
    </div>

    <div class="delete-reminder">
      <strong>⚠️ ¡Importante! Elimina este archivo ahora.</strong>
      Ve a cPanel → File Manager → <code>public_html/install.php</code> → Eliminar.<br/>
      O renómbralo a algo que no sea accesible: <code>_install_used.php</code>
    </div>
  </div>

  <?php endif; // step ?>

  <!-- Footer -->
  <div style="text-align:center;font-size:.78rem;color:var(--text-3);margin-top:1.5rem;padding-bottom:1rem;">
    Doctores.Digital · Sistema de migraciones v1.0
  </div>

</div>
</body>
</html>
