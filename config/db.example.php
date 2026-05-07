<?php
// ============================================================
//  PLANTILLA de configuración — cPanel
//  1. Copia este archivo como:  config/db.php
//  2. Llena los datos de tu cPanel
//  3. NUNCA subas db.php al repositorio (ya está en .gitignore)
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'doctores');
define('DB_USER',    'doctor');
define('DB_PASS',    'TU_PASSWORD_AQUI');   // ← solo cambia esto
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
