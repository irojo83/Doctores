-- ============================================================
--  Doctores.Digital — Script de base de datos
--  Ejecutar una sola vez en phpMyAdmin o MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS doctores
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE doctores;

-- Tabla principal de doctores
CREATE TABLE IF NOT EXISTS doctores (
    id           INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(150)    NOT NULL,
    email        VARCHAR(150)    NOT NULL UNIQUE,
    password     VARCHAR(255)    NOT NULL,          -- bcrypt hash
    especialidad VARCHAR(100)    DEFAULT NULL,
    ciudad       VARCHAR(100)    DEFAULT NULL,
    telefono     VARCHAR(30)     DEFAULT NULL,
    paquete      ENUM('base','pro','branded') DEFAULT 'base',
    activo       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login   DATETIME        DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de sesiones activas (opcional, para auditoría)
CREATE TABLE IF NOT EXISTS sesiones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id   INT UNSIGNED NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    user_agent  VARCHAR(300) DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Doctor de prueba (password: Doctor123)
INSERT IGNORE INTO doctores (nombre, email, password, especialidad, ciudad, paquete)
VALUES (
    'Dr. Demo',
    'demo@doctores.digital',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Medicina General',
    'Ciudad de México',
    'pro'
);
