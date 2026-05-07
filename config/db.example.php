<?php
// ============================================================
//  PLANTILLA de configuración — cPanel
//  1. Copia este archivo como:  config/db.php
//  2. Llena los datos de tu cPanel
//  3. NUNCA subas db.php al repositorio (ya está en .gitignore)
// ============================================================

// En cPanel el nombre lleva el prefijo de tu usuario:
// Ej: si tu usuario cPanel es "docmx", quedaría "docmx_doctores"
define('DB_HOST',    'localhost');
define('DB_NAME',    'CPANEL_USER_doctores');   // ← reemplaza CPANEL_USER
define('DB_USER',    'CPANEL_USER_doctor');     // ← reemplaza CPANEL_USER
define('DB_PASS',    'TU_PASSWORD_AQUI');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Error: ' . $e->getMessage());
            die('Error de conexión. Contacta al administrador.');
        }
    }
    return $pdo;
}
