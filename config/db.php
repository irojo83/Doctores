<?php
// ─────────────────────────────────────────
//  Configuración de base de datos
//  Cambia estos valores según tu servidor
// ─────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'doctores_digital');
define('DB_USER',    'root');
define('DB_PASS',    '');           // En XAMPP es vacío por defecto
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna una instancia singleton de PDO.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción: no expongas el error real
            error_log('DB Error: ' . $e->getMessage());
            die(json_encode(['error' => 'Error de conexión. Contacta al administrador.']));
        }
    }

    return $pdo;
}
