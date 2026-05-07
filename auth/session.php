<?php
// ─────────────────────────────────────────
//  Gestión de sesiones seguras
// ─────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,    // HTTPS en producción (doctores.digital)
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Verifica si hay un doctor autenticado.
 * Si no, redirige al login.
 */
function requireLogin(): void
{
    if (empty($_SESSION['doctor_id'])) {
        header('Location: ../login.php?msg=session');
        exit;
    }
}

/**
 * Inicia la sesión del doctor.
 */
function loginDoctor(array $doctor): void
{
    session_regenerate_id(true);        // previene session fixation
    $_SESSION['doctor_id']   = $doctor['id'];
    $_SESSION['doctor_nombre']= $doctor['nombre'];
    $_SESSION['doctor_email'] = $doctor['email'];
    $_SESSION['doctor_paquete']= $doctor['paquete'] ?? 'base';
}

/**
 * Cierra la sesión completamente.
 */
function logoutDoctor(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Genera o recupera el token CSRF de la sesión.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF enviado en el formulario.
 */
function validateCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
