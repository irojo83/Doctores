<?php
// ============================================================
//  GoDaddy API — Plantilla de configuración
//  1. Copia este archivo como: config/godaddy.php
//  2. Llena tus credenciales de https://developer.godaddy.com
//  3. NUNCA subas godaddy.php al repositorio (ya está en .gitignore)
// ============================================================

// Modo producción: true = api.godaddy.com | false = api.ote-godaddy.com (pruebas)
define('GODADDY_PRODUCTION', true);

// Tus credenciales de GoDaddy Developer Portal
define('GODADDY_KEY',    'TU_API_KEY_AQUI');
define('GODADDY_SECRET', 'TU_API_SECRET_AQUI');

// Información del registrante por defecto (se usa al registrar dominios)
// El doctor puede sobreescribir esto desde el formulario de onboarding
define('GODADDY_REGISTRANT', [
    'nameFirst'      => 'Doctores',
    'nameLast'       => 'Digital',
    'email'          => 'contacto@doctores.digital',
    'phone'          => '+52.4771234567',
    'organization'   => 'Doctores Digital SA de CV',
    'addressMailing' => [
        'address1'   => 'Av. Ejemplo 123',
        'city'       => 'Ciudad de México',
        'state'      => 'CMX',
        'postalCode' => '06600',
        'country'    => 'MX',
    ],
]);

// TLDs que se ofrecen a los doctores
define('GODADDY_TLDS', ['com', 'com.mx', 'mx']);
