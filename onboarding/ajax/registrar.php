<?php
/**
 * AJAX — Verificar disponibilidad y reservar dominio
 * POST /onboarding/ajax/registrar.php
 * Body JSON: { domain: string, csrf: string }
 * Response JSON: { success: bool, message: string }
 *
 * No compra el dominio en GoDaddy.
 * Solo verifica que esté disponible y lo guarda en la BD
 * para continuar al siguiente paso del onboarding.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../auth/session.php';
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$domain = strtolower(trim($input['domain'] ?? ''));
$csrf   = $input['csrf'] ?? '';

// Validar CSRF
if (!validateCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
    exit;
}

// Validar formato del dominio
if (!preg_match('/^[a-z0-9-]+\.(com\.mx|com|mx)$/', $domain)) {
    echo json_encode(['success' => false, 'message' => 'Formato de dominio inválido']);
    exit;
}

// Verificar disponibilidad en GoDaddy (sin comprar)
require_once __DIR__ . '/../../api/GoDaddy.php';
$gd     = new GoDaddy();
$check  = $gd->checkAvailability($domain);

if (!$check['available']) {
    echo json_encode([
        'success' => false,
        'message' => "El dominio {$domain} ya no está disponible. Elige otro.",
    ]);
    exit;
}

// Dominio disponible — guardarlo en la BD del doctor
require_once __DIR__ . '/../../config/db.php';
$stmt = getDB()->prepare(
    "UPDATE doctores SET dominio = ?, dominio_at = NOW() WHERE id = ?"
);
$stmt->execute([$domain, $_SESSION['doctor_id']]);

echo json_encode([
    'success' => true,
    'message' => "Dominio {$domain} reservado correctamente",
    'domain'  => $domain,
]);
