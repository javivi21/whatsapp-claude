// Bot de reservas de sesiones de ejemplo (DATOS FICTICIOS).
// Sin dependencias. Uso:
//   node bot.js            -> servidor webhook de Meta en el puerto 3000
//   node test.js           -> prueba la conversación en local, sin Meta
// Variables de entorno (nunca en el código): WA_TOKEN, WA_PHONE_ID, WA_VERIFY_TOKEN
const http = require('http');
const https = require('https');
const fs = require('fs');
const path = require('path');

const SERVICIOS = {
  1: { nombre: 'Retrato', precio: 60, duracion: '45 min' },
  2: { nombre: 'Familia', precio: 90, duracion: '1 h' },
  3: { nombre: 'Producto para tienda', precio: 120, duracion: '1,5 h' },
  4: { nombre: 'Evento (por horas)', precio: 150, duracion: '2 h mín.' },
};
const HUECOS = ['Mañana 10:00', 'Mañana 17:00', 'Pasado mañana 12:00'];
const PEDIDOS = path.join(__dirname, 'reservas.jsonl');
const sesiones = new Map(); // telefono -> {paso, servicio, hueco, nombre}

const menu = () =>
  'Hola, soy el asistente de Negocio de Ejemplo. ¿Qué sesión quieres?\n' +
  Object.entries(SERVICIOS).map(([k, s]) => `${k}. ${s.nombre} (${s.precio} €, ${s.duracion})`).join('\n') +
  '\nResponde con el número. Escribe PERSONA para hablar con el equipo.';

// Máquina de estados: devuelve el texto de respuesta.
function responder(tel, texto, guardar = guardarReserva) {
  const t = (texto || '').trim();
  if (/^persona$/i.test(t)) { sesiones.delete(tel); return 'Aviso al equipo y te escribe en cuanto pueda.'; }
  let s = sesiones.get(tel);
  if (!s || /^(hola|menu|menú|reiniciar)$/i.test(t)) { s = { paso: 'servicio' }; sesiones.set(tel, s); return menu(); }
  if (s.paso === 'servicio') {
    const sv = SERVICIOS[t];
    if (!sv) return 'No te he entendido. ' + menu();
    s.servicio = sv; s.paso = 'hueco';
    return `${sv.nombre}: ${sv.precio} €. Huecos libres:\n` + HUECOS.map((h, i) => `${i + 1}. ${h}`).join('\n') + '\nResponde con el número.';
  }
  if (s.paso === 'hueco') {
    const h = HUECOS[Number(t) - 1];
    if (!h) return 'Elige un número de la lista de huecos.';
    s.hueco = h; s.paso = 'nombre';
    return '¿A nombre de quién reservo?';
  }
  if (s.paso === 'nombre') {
    if (t.length < 2) return 'Dime tu nombre, por favor.';
    s.nombre = t; s.paso = 'confirmar';
    return `Resumen: ${s.servicio.nombre}, ${s.hueco}, a nombre de ${s.nombre}, ${s.servicio.precio} €.\nResponde SI para confirmar o NO para cancelar.`;
  }
  if (s.paso === 'confirmar') {
    if (/^s[ií]$/i.test(t)) {
      guardar({ tel, servicio: s.servicio.nombre, precio: s.servicio.precio, hueco: s.hueco, nombre: s.nombre, fecha: new Date().toISOString() });
      sesiones.delete(tel);
      return 'Reservado. El equipo te confirmará los detalles. Gracias.';
    }
    sesiones.delete(tel);
    return 'Cancelado. Escribe HOLA cuando quieras reservar.';
  }
  sesiones.delete(tel);
  return menu();
}

function guardarReserva(r) { fs.appendFileSync(PEDIDOS, JSON.stringify(r) + '\n'); }

function enviar(tel, texto) {
  const { WA_TOKEN, WA_PHONE_ID } = process.env;
  if (!WA_TOKEN || !WA_PHONE_ID) { console.log(`[sin credenciales] -> ${tel}: ${texto}`); return; }
  const body = JSON.stringify({ messaging_product: 'whatsapp', to: tel, type: 'text', text: { body: texto } });
  const req = https.request({
    hostname: 'graph.facebook.com', path: `/v21.0/${WA_PHONE_ID}/messages`, method: 'POST',
    headers: { Authorization: 'Bearer ' + WA_TOKEN, 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) },
  }, (r) => { if (r.statusCode >= 300) console.log('Error Meta', r.statusCode); });
  req.on('error', (e) => console.log('Error red', e.message));
  req.end(body);
}

function servidor() {
  const verify = process.env.WA_VERIFY_TOKEN;
  http.createServer((req, res) => {
    const u = new URL(req.url, 'http://x');
    if (req.method === 'GET' && u.pathname === '/webhook') { // verificación de Meta
      if (verify && u.searchParams.get('hub.verify_token') === verify) { res.end(u.searchParams.get('hub.challenge')); }
      else { res.statusCode = 403; res.end(); }
      return;
    }
    if (req.method === 'POST' && u.pathname === '/webhook') {
      let d = ''; req.on('data', (c) => (d += c));
      req.on('end', () => {
        res.end('ok');
        try {
          const msgs = JSON.parse(d).entry.flatMap((e) => e.changes).flatMap((c) => c.value.messages || []);
          for (const m of msgs) if (m.type === 'text') enviar(m.from, responder(m.from, m.text.body));
        } catch (e) { console.log('Payload ignorado', e.message); }
      });
      return;
    }
    res.statusCode = 404; res.end();
  }).listen(3000, () => console.log('Webhook en http://localhost:3000/webhook'));
}

if (require.main === module) servidor();
module.exports = { responder };
