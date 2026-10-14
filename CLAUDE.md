# whatsapp-claude: guía para Claude

Si una persona te pide "monta mi WhatsApp con este repo", guíala paso a paso. Habla en lenguaje llano.

## Reglas
1. Nunca pidas, veas ni escribas claves (token de Meta, app_secret, clave Anthropic, contraseña de Gmail). Las escribe la persona en su terminal con `php setup.php`.
2. Aceptar condiciones, generar tokens y publicar la app los hace la persona. Tú le dices qué pulsar.
3. No subas `config.php` ni `config_asistente.php` a ningún sitio.
4. Comprueba cada paso con `log.txt` del servidor y con `php tests/run.php`; no des nada por hecho.

## Orden
1. `php tests/run.php` debe dar todo correcto (necesita PHP 8.1+ con curl, mbstring, openssl).
2. Meta: portfolio, app con caso de uso WhatsApp, número real, usuario del sistema y token permanente. Detalle en `PLAYBOOK.md` (Fase 1 y 4).
3. `php setup.php` crea `php/config.php` (y el del asistente si lo quiere).
4. Subir `php/` a un hosting con PHP y HTTPS; `htaccess.txt` pasa a `.htaccess`.
5. En Meta, webhook = `https://dominio/ruta/webhook.php` + texto de verificación; suscribir el campo `messages`.
6. Probar escribiendo "hola" desde otro móvil. Si no responde, mirar `log.txt` y la tabla de problemas del `PLAYBOOK.md`.
7. Personalizar `servicios` y `huecos` en `config.php`.

## Aviso legal que debes dar
- RGPD: si se guardan datos de clientes, hace falta contrato de encargado de tratamiento; la API de Anthropic procesa en EE. UU.
- Meta prohíbe bots de IA de propósito general en la API Business; un bot de reservas o pedidos con función concreta sí está permitido.
- Precios y condiciones de Meta y Anthropic cambian: comprobarlos en sus webs antes de presupuestar.
