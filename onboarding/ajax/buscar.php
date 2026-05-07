<?php
/**
 * AJAX — Buscar disponibilidad de dominios
 * POST /onboarding/ajax/buscar.php
 * Body JSON: { query: string, csrf: string }
 * Response JSON: { resultados: [...] }
 */

header('Content-Type: application/json');

// Solo AJAX
require_once __DIR__ . '/../../auth/session.php';
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Leer y validar body
$input = json_decode(file_get_contents('php://input'), true);
$query = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($input['query'] ?? '')));
$csrf  = $input['csrf'] ?? '';

if (!validateCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF inválido']);
    exit;
}

if (strlen($query) < 2 || strlen($query) > 50) {
    echo json_encode(['resultados' => []]);
    exit;
}

// Cargar API
require_once __DIR__ . '/../../api/GoDaddy.php';

$gd   = new GoDaddy();
$tlds = GODADDY_TLDS; // ['com', 'com.mx', 'mx']

// Construir lista de dominios a verificar
$dominios = array_map(fn($tld) => $query . '.' . $tld, $tlds);

// Consultar disponibilidad masiva
$resultados = $gd->checkMultiple($dominios);

// Si el método masivo falla, verificar uno a uno
if (empty($resultados)) {
    $resultados = [];
    foreach ($dominios as $dom) {
        $resultados[] = $gd->checkAvailability($dom);
    }
}

echo json_encode(['resultados' => $resultados]);
