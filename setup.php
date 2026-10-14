<?php
// Asistente de configuración. Uso: php setup.php
// Pregunta los datos y escribe php/config.php (y php/config_asistente.php si quieres el asistente del dueño).
// Las claves las escribe la persona en SU terminal: Claude no las ve ni las necesita.
if (php_sapi_name() !== 'cli') { http_response_code(404); exit; }

function pregunta($texto, $defecto = '') {
    echo $texto . ($defecto !== '' ? " [$defecto]" : '') . ': ';
    $r = trim((string)fgets(STDIN));
    return $r === '' ? $defecto : $r;
}
function escribir($ruta, $datos) {
    if (is_file($ruta) && strtolower(pregunta("$ruta ya existe. ¿Sobrescribir? (s/n)", 'n')) !== 's') { echo "  Se deja como estaba.\n"; return; }
    file_put_contents($ruta, '<?php return ' . var_export($datos, true) . ";\n");
    echo "  Escrito: $ruta\n";
}

$dir = __DIR__ . '/php';
echo "== Configuración de whatsapp-claude ==\n";
echo "Datos de Meta (developers.facebook.com > tu app > WhatsApp > Configuración de la API):\n";
$cfg = [
    'verify'     => pregunta('Texto de verificación (inventa uno largo; el mismo irá en Meta > Webhooks)', bin2hex(random_bytes(16))),
    'token'      => pregunta('Token permanente del usuario del sistema'),
    'phone_id'   => pregunta('ID del número de teléfono (solo cifras)'),
    'app_secret' => pregunta('Clave secreta de la app (Configuración de la app > Básica)'),
    'negocio'    => pregunta('Nombre del negocio', 'Mi negocio'),
    'dueno'      => pregunta('Quién atiende si el cliente escribe PERSONA', 'el equipo'),
];
foreach (['token', 'phone_id', 'app_secret'] as $k) if ($cfg[$k] === '') echo "  Aviso: '$k' vacío; el bot no funcionará hasta rellenarlo.\n";
escribir("$dir/config.php", $cfg);

if (strtolower(pregunta('¿Quieres el asistente personal del dueño con Claude? (s/n)', 'n')) === 's') {
    escribir("$dir/config_asistente.php", [
        'owner'          => preg_replace('/\D/', '', pregunta('Móvil del dueño con prefijo, sin + (ej. 34600111222)')),
        'anthropic_key'  => pregunta('Clave de la API de Anthropic'),
        'gmail_user'     => pregunta('Gmail (opcional, para el correo)'),
        'gmail_app_pass' => pregunta('Contraseña de aplicación de Gmail (opcional)'),
        'nombre'         => pregunta('Cómo te llamas', 'el dueño'),
        'perfil'         => pregunta('A qué te dedicas (opcional)'),
    ]);
}
echo "\nListo. Sube la carpeta php/ a tu hosting con PHP (renombra htaccess.txt a .htaccess).\n";
echo "En Meta > WhatsApp > Configuración, webhook: https://TU-DOMINIO/RUTA/webhook.php y el texto de verificación de arriba.\n";
echo "Nunca subas estos archivos a GitHub.\n";
