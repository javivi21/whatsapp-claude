<?php
// Tarea programada: manda al dueño el resumen del correo por WhatsApp. Uso (cron): php cron_resumen.php
// El cron se lanza a las 07:30 y 08:30 UTC; solo envia cuando en España son las 09:30 (verano o invierno).
// Prueba manual: php cron_resumen.php ahora
if (php_sapi_name() !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Europe/Madrid');
if (($argv[1] ?? '') !== 'ahora' && date('H') !== '09') { echo "fuera de hora\n"; exit(0); }
require __DIR__ . '/asistente.php';
$owner = cfg_asistente()['owner'] ?? '';
if (!$owner) exit(1);
$c = wa_enviar($owner, "Resumen de tu correo:\n" . resumen_correo());
echo "WhatsApp HTTP $c\n";
