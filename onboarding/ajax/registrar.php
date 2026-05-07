<?php
/**
 * AJAX — Registrar dominio vía GoDaddy API
 * POST /onboarding/ajax/registrar.php
 * Body JSON: { domain: string, contacto: {...}, csrf: string }
 * Response JSON: { success: bool, message: string }
 */

header('Content-Type: application/json');

// ── Helpers ─────────────────────────────────────────────────

/**
 * Normaliza teléfono al formato GoDaddy: +52.XXXXXXXXXX
 * Acepta: +526568149228 / 526568149228 / +52.6568149228
 */
function normalizarTelefono(string $raw): string
{
    // Solo dígitos y +
    $digits = preg_replace('/[^0-9]/', '', $raw);

    // Si empieza con 52 y tiene 12 dígitos → +52.XXXXXXXXXX
    if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
        return '+52.' . substr($digits, 2);
    }
    // Si tiene 10 dígitos → número local MX
    if (strlen($digits) === 10) {
        return '+52.' . $digits;
    }
    // Si ya traía punto, lo conservamos limpio
    $clean = preg_replace('/[^+0-9.]/', '', $raw);
    // Aseguramos formato +CC.XXXXXXXXXX
    if (preg_match('/^\+(\d{1,3})\.(\d+)$/', $clean)) {
        return $clean;
    }
    return '+52.' . $digits;
}

/**
 * Convierte nombre de estado mexicano a abreviatura ISO (3 letras máx.)
 * GoDaddy rechaza nombres completos como "Guanajuato"
 */
function estadoAbreviatura(string $estado): string
{
    $mapa = [
        'aguascalientes' => 'AG', 'baja california' => 'BC', 'baja california sur' => 'BS',
        'campeche' => 'CM', 'chiapas' => 'CS', 'chihuahua' => 'CH',
        'ciudad de mexico' => 'DF', 'cdmx' => 'DF', 'df' => 'DF',
        'coahuila' => 'CO', 'colima' => 'CL', 'durango' => 'DG',
        'guanajuato' => 'GT', 'guerrero' => 'GR', 'hidalgo' => 'HG',
        'jalisco' => 'JA', 'mexico' => 'ME', 'estado de mexico' => 'ME',
        'michoacan' => 'MI', 'morelos' => 'MO', 'nayarit' => 'NA',
        'nuevo leon' => 'NL', 'oaxaca' => 'OA', 'puebla' => 'PU',
        'queretaro' => 'QE', 'quintana roo' => 'QR', 'san luis potosi' => 'SL',
        'sinaloa' => 'SI', 'sonora' => 'SO', 'tabasco' => 'TB',
        'tamaulipas' => 'TM', 'tlaxcala' => 'TL', 'veracruz' => 'VE',
        'yucatan' => 'YU', 'zacatecas' => 'ZA',
    ];

    // Si ya es abreviatura corta (2-3 chars), la usamos directo
    if (strlen($estado) <= 3) {
        return strtoupper($estado);
    }

    // Normalizar: minúsculas sin acentos
    $key = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $estado));
    return strtoupper($mapa[$key] ?? substr($estado, 0, 3));
}

// ────────────────────────────────────────────────────────────

require_once __DIR__ . '/../../auth/session.php';
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$domain = trim($input['domain'] ?? '');
$csrf   = $input['csrf']    ?? '';
$cont   = $input['contacto'] ?? [];

// Validaciones básicas
if (!validateCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
    exit;
}

if (!preg_match('/^[a-z0-9-]+\.(com\.mx|com|mx)$/', $domain)) {
    echo json_encode(['success' => false, 'message' => 'Dominio inválido']);
    exit;
}

// Sanitizar contacto
$contacto = [
    'nameFirst'      => substr(htmlspecialchars($cont['nameFirst'] ?? ''), 0, 80),
    'nameLast'       => substr(htmlspecialchars($cont['nameLast']  ?? ''), 0, 80),
    'email'          => filter_var($cont['email'] ?? '', FILTER_SANITIZE_EMAIL),
    'phone'          => normalizarTelefono($cont['phone'] ?? ''),
    'organization'   => 'Doctores Digital',
    'addressMailing' => [
        'address1'   => substr(htmlspecialchars($cont['addressMailing']['address1']   ?? 'Av. Principal 1'), 0, 80),
        'city'       => substr(htmlspecialchars($cont['addressMailing']['city']       ?? 'Ciudad de México'), 0, 80),
        'state'      => estadoAbreviatura($cont['addressMailing']['state'] ?? 'CMX'),
        'postalCode' => substr(preg_replace('/\D/', '', $cont['addressMailing']['postalCode'] ?? '06600'), 0, 10),
        'country'    => 'MX',
    ],
];

if (!$contacto['nameFirst'] || !$contacto['nameLast'] || !$contacto['email']) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos del registrante']);
    exit;
}

// IP del usuario para el consentimiento
$ip = $_SERVER['HTTP_X_FORWARDED_FOR']
   ?? $_SERVER['REMOTE_ADDR']
   ?? '127.0.0.1';

// Registrar dominio
require_once __DIR__ . '/../../api/GoDaddy.php';
$gd     = new GoDaddy();
$result = $gd->purchase($domain, $contacto, 1, false, $ip);

if ($result['success']) {
    // Guardar dominio en la BD del doctor
    require_once __DIR__ . '/../../config/db.php';
    $stmt = getDB()->prepare(
        "UPDATE doctores SET dominio = ? WHERE id = ?"
    );
    $stmt->execute([$domain, $_SESSION['doctor_id']]);

    echo json_encode([
        'success' => true,
        'message' => "Dominio {$domain} registrado correctamente",
        'domain'  => $domain,
        'orderId' => $result['orderId'],
    ]);
} else {
    // Log interno del error
    error_log("[GoDaddy] Error registrando {$domain}: " . json_encode($result['raw']));

    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'No se pudo registrar el dominio. Verifica tus datos.',
    ]);
}
