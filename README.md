# whatsapp-claude

Bot de WhatsApp (Cloud API de Meta) en PHP para hosting compartido, con asistente opcional de Claude para el dueño: correo completo con confirmación, memoria permanente y resumen diario.

Guía completa por fases: [PLAYBOOK.md](PLAYBOOK.md).

## Qué incluye
- `php/webhook.php`: recibe mensajes, **comprueba la firma de Meta** (`app_secret`), ignora mensajes repetidos, bot de reservas por menú y aviso de audios.
- `php/asistente.php`, `php/cron_resumen.php`: asistente de Claude para el dueño (instrucciones con `cache_control`) y resumen diario del correo.
- `php/config.ejemplo.php`, `php/config_asistente.ejemplo.php`: modelos de configuración sin secretos.
- `tests/run.php`: pruebas automáticas con Meta y Anthropic falsos, sin red real.
- `bot.js`, `test.js`: prototipo del flujo (`node test.js`).

## Probar
```bash
php tests/run.php
```
Necesita PHP 8.1+ con curl, mbstring y openssl.

## Seguridad
Nunca subir `config.php` ni `config_asistente.php`: contienen tokens y claves. Sin `app_secret` el webhook no comprueba la firma (no recomendado).

## Licencia
MIT. Ver [LICENSE](LICENSE).
