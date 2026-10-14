const assert = require('assert');
const { responder } = require('./bot');
const guardadas = [];
const g = (r) => guardadas.push(r);
const tel = '34600000000';
const dice = (t) => responder(tel, t, g);

assert(/Qué sesión/.test(dice('hola')));
assert(/No te he entendido/.test(dice('9')));
assert(/Huecos libres/.test(dice('1')));
assert(/Elige un número/.test(dice('7')));
assert(/nombre de quién/.test(dice('2')));
assert(/Resumen: Retrato, Mañana 17:00, a nombre de Ana/.test(dice('Ana')));
assert(/Reservado/.test(dice('sí')));
assert.strictEqual(guardadas.length, 1);
assert.strictEqual(guardadas[0].precio, 60);
// cancelar
dice('hola'); dice('3'); dice('1'); dice('Marta');
assert(/Cancelado/.test(dice('no')));
assert.strictEqual(guardadas.length, 1);
// persona
assert(/Aviso al equipo/.test(dice('persona')));
console.log('OK: todos los casos pasan');
