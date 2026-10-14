<?php
// Plantilla de bot de WhatsApp (Cloud API de Meta) para una empresa. Webhook principal.
// Claves y textos van en config.php (nunca en git). Ver config.ejemplo.php.
$cfg = @include __DIR__ . '/config.php';
if (!$cfg) { http_response_code(500); exit('sin config'); }
$cfg += ['negocio' => 'nuestra empresa', 'dueno' => 'el equipo', 'app_secret' => '',
         'api_base' => 'https://graph.facebook.com/v21.0', 'servicios' => null, 'huecos' => null];

function cfg() { global $cfg; return $cfg; }

// Servicios y huecos de ejemplo; se sustituyen desde config.php ('servicios', 'huecos').
function servicios() {
    return cfg()['servicios'] ?: [
        '1' => ['Servicio de ejemplo A', 60, '45 min'], '2' => ['Servicio de ejemplo B', 90, '1 h'],
        '3' => ['Servicio de ejemplo C', 120, '1,5 h'],
    ];
}
function huecos() { return cfg()['huecos'] ?: ['Mañana 10:00', 'Mañana 17:00', 'Pasado mañana 12:00']; }

// Log con rotación sencilla (1 MB) para que no crezca sin límite.
function log_linea($s) {
    $f = __DIR__ . '/log.txt';
    if (is_file($f) && filesize($f) > 1000000) @rename($f, $f . '.old');
    file_put_contents($f, date('c') . ' ' . $s . "\n", FILE_APPEND | LOCK_EX);
}

// Verificación de Meta (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (hash_equals((string)$cfg['verify'], (string)($_GET['hub_verify_token'] ?? ''))) {
        echo preg_replace('/\D/', '', (string)($_GET['hub_challenge'] ?? '')); exit;
    }
    http_response_code(403); exit;
}

// Firma de Meta (X-Hub-Signature-256): comprueba que el mensaje viene de Meta y no de un tercero.
// Se activa poniendo 'app_secret' (Meta > Configuración de la app > Básica) en config.php.
function firma_valida($raw, $secret) {
    if ($secret === '') return true; // modo compatible: sin app_secret no se comprueba (no recomendado)
    $h = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    return $h !== '' && hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $h);
}

// Meta reenvía a veces el mismo mensaje: se recuerda el id de los últimos 300 para no contestar dos veces.
function ya_visto($id) {
    if ($id === '') return false;
    if (!is_dir(__DIR__ . '/estado')) mkdir(__DIR__ . '/estado', 0700);
    $fp = fopen(__DIR__ . '/estado/vistos.json', 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    $l = json_decode((string)stream_get_contents($fp), true) ?: [];
    $visto = isset($l[$id]);
    if (!$visto) {
        $l[$id] = time(); arsort($l); $l = array_slice($l, 0, 300, true);
        ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($l));
    }
    flock($fp, LOCK_UN); fclose($fp);
    return $visto;
}

function menu() {
    $l = 'Hola, soy el asistente de ' . cfg()['negocio'] . ". ¿Qué servicio quieres?\n";
    foreach (servicios() as $k => $s) $l .= "$k. {$s[0]} ({$s[1]} €, {$s[2]})\n";
    return $l . 'Responde con el número. Escribe PERSONA para hablar con ' . cfg()['dueno'] . '.';
}

function responder($tel, $t) {
    $f = __DIR__ . '/estado/' . preg_replace('/\D/', '', $tel) . '.json';
    if (!is_dir(__DIR__ . '/estado')) mkdir(__DIR__ . '/estado', 0700);
    $t = trim($t);
    if (preg_match('/^persona$/iu', $t)) { @unlink($f); return 'Aviso a ' . cfg()['dueno'] . ' y te escribe en cuanto pueda.'; }
    $s = is_file($f) ? json_decode(file_get_contents($f), true) : null;
    if (!$s || preg_match('/^(hola|menu|menú|reiniciar)$/iu', $t)) {
        file_put_contents($f, json_encode(['paso' => 'servicio'])); return menu();
    }
    $out = null; $SERV = servicios(); $HUE = huecos();
    switch ($s['paso']) {
        case 'servicio':
            if (!isset($SERV[$t])) { return 'No te he entendido. ' . menu(); }
            $s['servicio'] = $t; $s['paso'] = 'hueco'; $sv = $SERV[$t];
            $out = "{$sv[0]}: {$sv[1]} €. Huecos libres:\n";
            foreach ($HUE as $i => $h) $out .= ($i + 1) . ". $h\n";
            $out .= 'Responde con el número.'; break;
        case 'hueco':
            if (!isset($HUE[(int)$t - 1])) return 'Elige un número de la lista de huecos.';
            $s['hueco'] = $HUE[(int)$t - 1]; $s['paso'] = 'nombre'; $out = '¿A nombre de quién reservo?'; break;
        case 'nombre':
            if (mb_strlen($t) < 2) return 'Dime tu nombre, por favor.';
            $s['nombre'] = mb_substr($t, 0, 60); $s['paso'] = 'confirmar'; $sv = $SERV[$s['servicio']];
            $out = "Resumen: {$sv[0]}, {$s['hueco']}, a nombre de {$s['nombre']}, {$sv[1]} €.\nResponde SI para confirmar o NO para cancelar."; break;
        case 'confirmar':
            if (preg_match('/^s[ií]$/iu', $t)) {
                $sv = $SERV[$s['servicio']];
                file_put_contents(__DIR__ . '/reservas.jsonl', json_encode(['tel' => $tel, 'servicio' => $sv[0], 'precio' => $sv[1],
                    'hueco' => $s['hueco'], 'nombre' => $s['nombre'], 'fecha' => date('c')], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
                @unlink($f); return 'Reservado. ' . ucfirst(cfg()['dueno']) . ' te confirmará los detalles. Gracias.';
            }
            @unlink($f); return 'Cancelado. Escribe HOLA cuando quieras reservar.';
    }
    file_put_contents($f, json_encode($s));
    return $out;
}

function enviar($cfg, $tel, $texto) {
    $body = json_encode(['messaging_product' => 'whatsapp', 'to' => $tel, 'type' => 'text', 'text' => ['body' => $texto]], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($body === false) { log_linea('json_error ' . json_last_error_msg()); return; }
    for ($i = 0; $i < 3; $i++) { // Meta devuelve 500 a veces: reintentar hasta 3 veces
        $ch = curl_init("{$cfg['api_base']}/{$cfg['phone_id']}/messages");
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . preg_replace('/\s+/', '', $cfg['token']), 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body]);
        $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); $e = curl_error($ch); curl_close($ch);
        if ($c < 500 && $c !== 0) break;
        sleep(1);
    }
    log_linea("-> $tel HTTP $c err[$e] body[" . substr($body, 0, 120) . '] ' . substr((string)$r, 0, 400));
}

// Memoria por cliente: últimos 40 intercambios de cada número en estado/conv_<tel>.json
function cliente_anotar($tel, $dijo, $resp) {
    if (!is_dir(__DIR__ . '/estado')) mkdir(__DIR__ . '/estado', 0700);
    $f = __DIR__ . '/estado/conv_' . preg_replace('/\D/', '', $tel) . '.json';
    $c = is_file($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
    $c[] = ['t' => date('Y-m-d H:i'), 'c' => mb_substr($dijo, 0, 400), 'b' => mb_substr($resp, 0, 400)];
    file_put_contents($f, json_encode(array_slice($c, -40), JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Mensajes entrantes (POST)
$raw = file_get_contents('php://input');
if (!firma_valida($raw, (string)$cfg['app_secret'])) { log_linea('firma invalida, mensaje rechazado'); http_response_code(403); exit('firma'); }
$d = json_decode($raw, true);
http_response_code(200); echo 'ok';
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); // Meta recibe el 200 ya; el resto sigue en segundo plano

foreach (($d['entry'] ?? []) as $e) foreach (($e['changes'] ?? []) as $c)
    foreach (($c['value']['messages'] ?? []) as $m) {
        if (ya_visto((string)($m['id'] ?? ''))) continue;
        if (($m['type'] ?? '') === 'audio') { // aún sin transcripción: avisar en vez de callar
            enviar($cfg, $m['from'], 'Por ahora no puedo escuchar audios. ¿Me lo escribes, por favor?');
        } elseif (($m['type'] ?? '') === 'text') {
            $cuerpo = (string)($m['text']['body'] ?? '');
            $asis = @include __DIR__ . '/config_asistente.php';
            if ($asis && $m['from'] === ($asis['owner'] ?? '') && !preg_match('/^reservas\b/iu', trim($cuerpo))) {
                require_once __DIR__ . '/asistente.php';
                $resp = asistente_responder($cuerpo);
                enviar($cfg, $m['from'], $resp);
                try { asistente_memorizar($cuerpo, $resp); } catch (Throwable $ex) { } // memoria permanente, después de contestar
            } else {
                $txt = preg_replace('/^reservas\b[,:\s]*/iu', '', trim($cuerpo));
                $resp = responder($m['from'], $txt === '' ? 'hola' : $txt);
                enviar($cfg, $m['from'], $resp);
                cliente_anotar($m['from'], $txt, $resp);
            }
        }
    }
