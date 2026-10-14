<?php
// Pruebas del webhook sin red real. Uso: php tests/run.php
// Levanta (1) un Meta falso que anota lo que recibe y (2) el webhook real apuntando a él.
$raiz = dirname(__DIR__);
$tmp  = sys_get_temp_dir() . '/wa-test-' . getmypid();
@mkdir($tmp . '/app', 0777, true);
@mkdir($tmp . '/meta', 0777, true);
foreach (glob($raiz . '/php/*.php') as $f) copy($f, $tmp . '/app/' . basename($f));
copy($raiz . '/php/asistente.php', $tmp . '/app/asistente.php');

$puertoApp = 18081; $puertoMeta = 18082; $secreto = 'secreto-de-prueba';
file_put_contents($tmp . '/app/config.php', "<?php return " . var_export([
    'verify' => 'verificacion-larga-aleatoria', 'token' => 'TOKEN_FALSO', 'phone_id' => '111',
    'app_secret' => $secreto, 'api_base' => "http://127.0.0.1:$puertoMeta/v21.0",
    'negocio' => 'Negocio de Prueba', 'dueno' => 'Ana',
], true) . ";\n");
// Meta falso: anota lo recibido. Si la ruta es /v1/messages hace de Anthropic falso (otro archivo).
file_put_contents($tmp . '/meta/router.php', <<<'PHP'
<?php
$ia = strpos($_SERVER['REQUEST_URI'], '/v1/messages') === 0;
file_put_contents(__DIR__ . ($ia ? '/claude.jsonl' : '/capturados.jsonl'), file_get_contents('php://input') . "\n", FILE_APPEND);
header('Content-Type: application/json');
echo $ia ? json_encode(['content' => [['type' => 'text', 'text' => 'Respuesta falsa de Claude']]]) : '{"messages":[{"id":"wamid.fake"}]}';
PHP);
file_put_contents($tmp . '/app/config_asistente.php', '<?php return ' . var_export([
    'owner' => '34699000111', 'anthropic_key' => 'CLAVE_FALSA', 'api_claude' => "http://127.0.0.1:$puertoMeta/v1/messages",
    'nombre' => 'Ana', 'perfil' => 'peluquera en Madrid', 'gmail_user' => 'ana@example.com', 'gmail_app_pass' => 'x',
], true) . ';');

function arrancar($puerto, $dir, $router = null) {
    $cmd = [PHP_BINARY, '-S', "127.0.0.1:$puerto", '-t', $dir] + ($router ? [5 => $router] : []);
    $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', "$dir/server.log", 'a'], 2 => ['file', "$dir/server.log", 'a']], $pipes);
    return [$p, $pipes];
}
[$pApp] = arrancar($puertoApp, $tmp . '/app');
[$pMeta] = arrancar($puertoMeta, $tmp . '/meta', $tmp . '/meta/router.php');
usleep(800000);

function http($metodo, $url, $cuerpo = null, $cab = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $cab, CURLOPT_POSTFIELDS => $cuerpo]);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$c, $r];
}
function capturados($tmp) {
    $f = $tmp . '/meta/capturados.jsonl';
    if (!is_file($f)) return [];
    return array_map(fn($l) => json_decode($l, true), array_values(array_filter(explode("\n", file_get_contents($f)))));
}
function msg($id, $tipo, $texto = '', $from = '34600111222') {
    $m = ['id' => $id, 'from' => $from, 'type' => $tipo];
    if ($tipo === 'text') $m['text'] = ['body' => $texto];
    return json_encode(['entry' => [['changes' => [['value' => ['messages' => [$m]]]]]]]);
}
function post($tmp, $puerto, $secreto, $cuerpo, $firmar = true) {
    $cab = ['Content-Type: application/json'];
    if ($firmar) $cab[] = 'X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $cuerpo, $secreto);
    return http('POST', "http://127.0.0.1:$puerto/webhook.php", $cuerpo, $cab);
}

$fallos = 0; $total = 0;
function prueba($nombre, $ok, $detalle = '') {
    global $fallos, $total; $total++;
    if (!$ok) $fallos++;
    echo ($ok ? '  ok    ' : '  FALLA ') . $nombre . ($ok ? '' : "  -> $detalle") . "\n";
}
$u = "http://127.0.0.1:$puertoApp/webhook.php";

echo "Verificación de Meta (GET)\n";
[$c, $r] = http('GET', "$u?hub_verify_token=verificacion-larga-aleatoria&hub_challenge=12345");
prueba('token correcto devuelve el challenge', $c === 200 && $r === '12345', "$c $r");
[$c] = http('GET', "$u?hub_verify_token=otro&hub_challenge=1");
prueba('token incorrecto da 403', $c === 403, (string)$c);

echo "Firma de Meta\n";
[$c] = post($tmp, $puertoApp, $secreto, msg('wamid.A1', 'text', 'hola'), false);
prueba('sin firma se rechaza (403)', $c === 403, (string)$c);
[$c] = http('POST', $u, msg('wamid.A2', 'text', 'hola'), ['Content-Type: application/json', 'X-Hub-Signature-256: sha256=' . str_repeat('0', 64)]);
prueba('firma falsa se rechaza (403)', $c === 403, (string)$c);
prueba('el rechazo no envía nada', count(capturados($tmp)) === 0);

echo "Mensajes\n";
[$c] = post($tmp, $puertoApp, $secreto, msg('wamid.B1', 'text', 'hola'));
$cap = capturados($tmp);
prueba('mensaje firmado da 200', $c === 200, (string)$c);
prueba('responde con el menú y el nombre del negocio', count($cap) === 1 && str_contains($cap[0]['text']['body'] ?? '', 'Negocio de Prueba') && str_contains($cap[0]['text']['body'], 'Ana'), json_encode($cap));
post($tmp, $puertoApp, $secreto, msg('wamid.B1', 'text', 'hola'));
prueba('mensaje repetido (mismo id) no se contesta dos veces', count(capturados($tmp)) === 1, (string)count(capturados($tmp)));

echo "Reserva completa\n";
foreach (['1' => 'wamid.C1', '1 ' => 'wamid.C2', 'Marta' => 'wamid.C3', 'SI' => 'wamid.C4'] as $t => $id) post($tmp, $puertoApp, $secreto, msg($id, 'text', trim($t)));
$todos = capturados($tmp); $ult = end($todos);
$res = is_file($tmp . '/app/reservas.jsonl') ? json_decode(trim(file_get_contents($tmp . '/app/reservas.jsonl')), true) : null;
prueba('confirma la reserva', str_contains($ult['text']['body'] ?? '', 'Reservado'), $ult['text']['body'] ?? '');
prueba('guarda la reserva con nombre y servicio', ($res['nombre'] ?? '') === 'Marta' && ($res['servicio'] ?? '') === 'Servicio de ejemplo A', json_encode($res));

echo "Audios\n";
$antes = count(capturados($tmp));
post($tmp, $puertoApp, $secreto, msg('wamid.D1', 'audio'));
$cap = capturados($tmp);
$ultimo = end($cap);
prueba('avisa de que no escucha audios', count($cap) === $antes + 1 && str_contains($ultimo['text']['body'] ?? '', 'audios'));

echo "Asistente del dueño\n";
$antes = count(capturados($tmp));
post($tmp, $puertoApp, $secreto, msg('wamid.E1', 'text', 'Anota una idea para el lunes', '34699000111'));
$cap = capturados($tmp); $ult = end($cap);
prueba('el dueño recibe la respuesta de Claude por WhatsApp', count($cap) === $antes + 1 && str_contains($ult['text']['body'] ?? '', 'Respuesta falsa de Claude'), json_encode($ult));
$lin = is_file($tmp . '/meta/claude.jsonl') ? explode("\n", trim(file_get_contents($tmp . '/meta/claude.jsonl'))) : [];
$sis = (json_decode($lin[0] ?? '[]', true)['system'][0] ?? []);
prueba('las instrucciones llevan cache_control', ($sis['cache_control']['type'] ?? '') === 'ephemeral', json_encode($sis));
prueba('se personaliza con nombre y perfil', str_contains($sis['text'] ?? '', 'Ana') && str_contains($sis['text'] ?? '', 'peluquera en Madrid') && !str_contains($sis['text'] ?? '', '{'), $sis['text'] ?? '');
prueba('el código no lleva datos personales del autor', !preg_match('/Javi|Luis|luiart|León/u', file_get_contents($tmp . '/app/asistente.php')));

echo "Registro\n";
prueba('log.txt existe y registra envíos', is_file($tmp . '/app/log.txt') && str_contains(file_get_contents($tmp . '/app/log.txt'), 'HTTP 200'));

foreach ([$pApp, $pMeta] as $p) { proc_terminate($p); proc_close($p); }
echo "\n" . ($total - $fallos) . "/$total pruebas correctas\n";
exit($fallos ? 1 : 0);
