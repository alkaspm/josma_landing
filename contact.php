<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

define('TO_EMAIL', 'cotizaciones@josma.cl');
define('FROM_EMAIL', 'no-reply@josma.cl');
define('SITE_NAME', 'JOSMA SpA');

if (!empty($_POST['website'] ?? '')) {
    echo json_encode(['success' => true, 'message' => 'Gracias.']);
    exit;
}

$company = trim($_POST['company'] ?? '');
$rut = trim($_POST['rut'] ?? '');
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$consentRaw = $_POST['consent'] ?? null;
$consent = isset($consentRaw) && in_array(strtolower((string)$consentRaw), ['1', 'on', 'true', 'yes'], true);

if ($company === '' || $name === '' || $email === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Por favor completa los campos obligatorios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'El correo no es válido.']);
    exit;
}

if (!$consent) {
    echo json_encode(['success' => false, 'message' => 'Debes aceptar ser contactado.']);
    exit;
}

$clean = fn($v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$company = $clean($company);
$rut = $clean($rut);
$name = $clean($name);
$email = $clean($email);
$phone = $clean($phone);
$message = $clean($message);

$to = TO_EMAIL;
$subject = 'Nuevo Requerimiento B2B desde ' . SITE_NAME;
$body = "Empresa: {$company}\nRUT: {$rut}\nContacto: {$name}\nCorreo: {$email}\nTeléfono: {$phone}\n\nRequerimiento:\n{$message}\n\nIP: " . $_SERVER['REMOTE_ADDR'];
$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/plain; charset=utf-8',
    'From: ' . FROM_EMAIL,
    'Reply-To: ' . $email
];

$sent = @mail($to, $subject, $body, implode("\r\n", $headers));

if ($sent) {
    echo json_encode(['success' => true, 'message' => '¡Gracias! Tu mensaje fue enviado correctamente.']);
} else {
    echo json_encode(['success' => false, 'message' => 'No se pudo enviar el mensaje (mail deshabilitado). Configura SMTP o revisa el servidor.']);
}