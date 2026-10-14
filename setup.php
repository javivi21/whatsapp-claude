<?php
// Asistente de instalación para una empresa. Uso: php setup.php
// Pregunta los datos del negocio y de Meta y deja listo el paquete en instalar/ (config, catálogo, .htaccess y código).
// Las claves las escribe la persona en SU terminal: Claude no las ve ni las necesita.
if (php_sapi_name() !== 'cli') { http_response_code(404); exit; }

function pregunta($texto, $defecto = '') {
    echo $texto . ($defecto !== '' ? " [$defecto]" : '') . ': ';
    $r = trim((string)fgets(STDIN));
    return $r === '' ? $defecto : $r;
}
function si($texto, $defecto = 's') { return strtolower(pregunta("$texto (s/n)", $defecto)) === 's'; }
function guardar($ruta, $datos) { file_put_contents($ruta, '<?php return ' . var_export($datos, true) . ";\n"); echo "  Escrito: $ruta\n"; }

$raiz = __DIR__; $out = "$raiz/instalar";
@mkdir("$out/php", 0777, true);

echo "== Instalación de whatsapp-claude para una empresa ==\n\n1) El negocio\n";
$cfg = [
    'negocio' => pregunta('Nombre del negocio', 'Mi negocio'),
    'tipo'    => pregunta('A qué se dedica (ej. peluquería, tienda de ropa, restaurante)'),
    'dueno'   => pregunta('Quién atiende cuando el cliente escribe PERSONA', 'el equipo'),
    'info'    => pregunta('Info que Claude puede contar (horario, envíos, devoluciones, pagos, dirección), en una línea'),
];

echo "\n2) Datos de Meta (developers.facebook.com > tu app > WhatsApp > Configuración de la API)\n";
$cfg += [
    'verify'     => pregunta('Texto de verificación (inventa uno largo; el mismo irá en Meta > Webhooks)', bin2hex(random_bytes(16))),
    'token'      => pregunta('Token permanente del usuario del sistema'),
    'phone_id'   => pregunta('ID del número de teléfono (solo cifras)'),
    'app_secret' => pregunta('Clave secreta de la app (Configuración de la app > Básica)'),
];
foreach (['token', 'phone_id', 'app_secret'] as $k) if ($cfg[$k] === '') echo "  Aviso: '$k' vacío; hay que rellenarlo en php/config.php antes de usar.\n";

echo "\n3) Qué vende o ofrece (productos o servicios, con precio)\n";
$csv = "$out/php/catalogo.csv";
$origen = pregunta('Ruta de tu CSV (id;nombre;precio;descripcion) o Enter para empezar con el de ejemplo');
copy($origen !== '' && is_file($origen) ? $origen : "$raiz/php/catalogo.ejemplo.csv", $csv);
echo "  Catálogo: $csv (edítalo en Excel; guarda como CSV UTF-8)\n";
$cfg['catalogo_csv'] = 'catalogo.csv';

echo "\n4) Atención con Claude\n";
if (si('¿Claude atiende a los clientes y recoge pedidos?')) {
    $cfg['ia_clientes'] = true;
    $cfg['anthropic_key'] = pregunta('Clave de la API de Anthropic (espacio de trabajo propio con límite de gasto)');
    $cfg['aviso'] = preg_replace('/\D/', '', pregunta('Móvil que recibe los pedidos y avisos, con prefijo y sin + (ej. 34600111222)'));
} else {
    $cfg['ia_clientes'] = false;
    echo "  Se usará el menú sencillo de reservas (edita 'servicios' y 'huecos' en config.php).\n";
}
guardar("$out/php/config.php", $cfg);

if (si('¿Quieres también el asistente personal del dueño (correo, memoria)?', 'n')) {
    guardar("$out/php/config_asistente.php", [
        'owner'          => preg_replace('/\D/', '', pregunta('Móvil del dueño con prefijo, sin +')),
        'anthropic_key'  => $cfg['anthropic_key'] ?? pregunta('Clave de la API de Anthropic'),
        'gmail_user'     => pregunta('Gmail (opcional)'),
        'gmail_app_pass' => pregunta('Contraseña de aplicación de Gmail (opcional)'),
        'nombre'         => pregunta('Cómo se llama el dueño', 'el dueño'),
        'perfil'         => pregunta('A qué se dedica (opcional)'),
    ]);
}

// Código y protección
foreach (['webhook.php', 'asistente.php', 'cliente.php', 'cron_resumen.php'] as $f) copy("$raiz/php/$f", "$out/php/$f");
copy("$raiz/php/htaccess.txt", "$out/php/.htaccess");

echo "\nListo. Sube TODO el contenido de instalar/php/ a tu hosting (PHP con HTTPS), por ejemplo a /whatsapp-bot/.\n";
echo "En Meta > WhatsApp > Configuración, webhook: https://TU-DOMINIO/whatsapp-bot/webhook.php y el texto de verificación; suscribe el campo 'messages'.\n";
echo "La carpeta instalar/ lleva claves: no la subas a GitHub (ya está en .gitignore).\n";
