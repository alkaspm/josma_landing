<?php
/**
 * JOSMA SpA — Formulario de Contacto B2B (Seguro)
 * 
 * Protecciones implementadas:
 *  - CSRF token (session-based)
 *  - Rate limiting (1 envío por minuto por sesión)
 *  - Honeypot anti-bot con timestamp validation
 *  - Sanitización de headers (CRLF injection prevention)
 *  - Validación estricta de todos los campos
 *  - Anti email header injection
 *  - Content-Type enforcement
 */

// --- Configuración ---
define('TO_EMAIL',   'cotizaciones@josma.cl');
define('FROM_EMAIL', 'no-reply@josma.cl');
define('SITE_NAME',  'JOSMA SpA');
define('RATE_LIMIT_SECONDS', 60);
define('HONEYPOT_MIN_SECONDS', 3); // Mínimo de tiempo para considerar un envío humano

// --- Security Headers ---
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// --- Iniciar sesión segura ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// =====================================================================
// GET → Generar y devolver CSRF token
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_time']  = time();
    echo json_encode(['csrf_token' => $token]);
    exit;
}

// =====================================================================
// Solo aceptar POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// =====================================================================
// 1. Verificar CSRF Token
// =====================================================================
$clientToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$serverToken = $_SESSION['csrf_token'] ?? '';

if ($serverToken === '' || !hash_equals($serverToken, $clientToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada. Recargue la página e intente nuevamente.']);
    exit;
}

// Invalidar token tras uso (one-time use)
unset($_SESSION['csrf_token']);

// =====================================================================
// 2. Rate Limiting (sesión)
// =====================================================================
$now = time();
$lastSubmit = $_SESSION['last_contact_submit'] ?? 0;

if ($now - $lastSubmit < RATE_LIMIT_SECONDS) {
    $remaining = RATE_LIMIT_SECONDS - ($now - $lastSubmit);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => "Por favor espere {$remaining} segundos antes de enviar otra solicitud."
    ]);
    exit;
}

// =====================================================================
// 3. Honeypot Check (campo oculto + tiempo mínimo)
// =====================================================================
if (!empty($_POST['website'] ?? '')) {
    // Bot detectado — simular éxito silencioso
    echo json_encode(['success' => true, 'message' => 'Gracias.']);
    exit;
}

// Verificar tiempo mínimo desde que se generó el CSRF token
$csrfTime = $_SESSION['csrf_time'] ?? $now;
if (($now - $csrfTime) < HONEYPOT_MIN_SECONDS) {
    // Envío demasiado rápido — probable bot
    echo json_encode(['success' => true, 'message' => 'Gracias.']);
    exit;
}

// =====================================================================
// 4. Extraer y Sanitizar campos
// =====================================================================
$company = trim($_POST['company'] ?? '');
$rut     = trim($_POST['rut'] ?? '');
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

$consentRaw = $_POST['consent'] ?? null;
$consent = isset($consentRaw) && in_array(strtolower((string)$consentRaw), ['1', 'on', 'true', 'yes'], true);

// =====================================================================
// 5. Validaciones Server-Side
// =====================================================================

// Campos obligatorios
if ($company === '' || $name === '' || $email === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Por favor completa los campos obligatorios.']);
    exit;
}

// Longitudes máximas (previene payloads excesivos)
$maxLengths = [
    'company' => 200,
    'rut'     => 15,
    'name'    => 150,
    'email'   => 254,
    'phone'   => 20,
    'message' => 2000,
];

foreach ($maxLengths as $field => $max) {
    if (mb_strlen($$field) > $max) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "El campo {$field} excede la longitud máxima permitida."]);
        exit;
    }
}

// Email válido
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El correo electrónico no es válido.']);
    exit;
}

// Teléfono — solo caracteres válidos y longitud razonable
$phone = preg_replace('/[^0-9+\-\s()]/', '', $phone);
if ($phone !== '' && (strlen($phone) < 8 || strlen($phone) > 20)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El número de teléfono no es válido.']);
    exit;
}

// Consentimiento obligatorio
if (!$consent) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Debe autorizar el contacto comercial.']);
    exit;
}

// RUT — validar formato chileno si se proporcionó
if ($rut !== '') {
    $rutClean = preg_replace('/[^0-9kK]/', '', $rut);
    if (strlen($rutClean) < 8 || strlen($rutClean) > 9) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'El RUT ingresado no tiene un formato válido.']);
        exit;
    }
}

// =====================================================================
// 6. Sanitizar para salida (previene XSS en email clients)
// =====================================================================
$clean = fn($v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$companyClean = $clean($company);
$rutClean     = $clean($rut);
$nameClean    = $clean($name);
$emailClean   = $clean($email);
$phoneClean   = $clean($phone);
$messageClean = $clean($message);

// =====================================================================
// 7. Prevenir CRLF Injection en headers de email
// =====================================================================
$safeEmail = str_replace(["\r", "\n", "%0a", "%0d", "%0A", "%0D"], '', $email);

// =====================================================================
// 8. Construir y enviar email
// =====================================================================
$to      = TO_EMAIL;
$subject = '=?UTF-8?B?' . base64_encode('Nuevo Requerimiento B2B — ' . SITE_NAME) . '?=';

$body = <<<EOT
╔══════════════════════════════════════════╗
║  Nuevo Requerimiento B2B — JOSMA SpA    ║
╚══════════════════════════════════════════╝

Empresa:   {$companyClean}
RUT:       {$rutClean}
Contacto:  {$nameClean}
Correo:    {$emailClean}
Teléfono:  {$phoneClean}

─────────────────────────────────────────
Detalle del Requerimiento:
─────────────────────────────────────────
{$messageClean}

─────────────────────────────────────────
Metadata:
  Fecha:     %s
  Origen:    Formulario Web josma.cl
  Consent:   Autorizado
─────────────────────────────────────────
EOT;

$body = sprintf($body, date('Y-m-d H:i:s T'));

$headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . FROM_EMAIL,
    'Reply-To: ' . $safeEmail,
    'X-Mailer: JOSMA-Landing/2.0',
    'X-Priority: 3',
]);

$sent = @mail($to, $subject, $body, $headers);

// =====================================================================
// 9. Registrar timestamp de envío exitoso
// =====================================================================
if ($sent) {
    $_SESSION['last_contact_submit'] = $now;
    echo json_encode([
        'success' => true,
        'message' => '¡Gracias! Su solicitud fue recibida. Nuestro equipo comercial le contactará en un plazo máximo de 24 horas hábiles.'
    ]);
} else {
    // Log error internamente (no exponer detalles al cliente)
    error_log('[JOSMA] Error al enviar email de contacto desde: ' . $safeEmail . ' - ' . date('Y-m-d H:i:s'));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo enviar el mensaje en este momento. Por favor contacte directamente a cotizaciones@josma.cl.'
    ]);
}