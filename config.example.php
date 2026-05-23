<?php
/**
 * JOSMA SpA — Configuración del Landing
 * 
 * INSTRUCCIONES:
 * 1. Copiar este archivo como "config.php"
 * 2. Ajustar los valores según el entorno
 * 3. NUNCA commitear config.php al repositorio
 */

// --- Email de destino para cotizaciones ---
define('TO_EMAIL',   'cotizaciones@josma.cl');

// --- Remitente (no-reply) ---
define('FROM_EMAIL', 'no-reply@josma.cl');

// --- Nombre del sitio ---
define('SITE_NAME',  'JOSMA SpA');

// --- Rate Limiting (segundos entre envíos) ---
define('RATE_LIMIT_SECONDS', 60);

// --- Tiempo mínimo para considerar envío humano (segundos) ---
define('HONEYPOT_MIN_SECONDS', 3);