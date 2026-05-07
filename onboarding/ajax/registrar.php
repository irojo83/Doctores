<?php
/**
 * AJAX — Registrar dominio vía GoDaddy API
 * POST /onboarding/ajax/registrar.php
 * Body JSON: { domain: string, contacto: {...}, csrf: string }
 * Response JSON: { success: bool, message: string }
 */

header('Content-Type: application/json');

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
    'phone'          => preg_replace('/[^+0-9.]/', '', $cont['phone'] ?? ''),
    'organization'   => 'Doctores Digital',
    'addressMailing' => [
        'address1'   => substr(htmlspecialchars($cont['addressMailing']['address1']   ?? 'Av. Principal 1'), 0, 80),
        'city'       => substr(htmlspecialchars($cont['addressMailing']['city']       ?? 'Ciudad de México'), 0, 80),
        'state'      => substr(htmlspecialchars($cont['addressMailing']['state']      ?? 'CMX'), 0, 30),
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
