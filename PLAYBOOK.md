# Playbook: bot de WhatsApp para una empresa (con número propio)

Basado en una prueba real con una pyme de servicios (14-oct-2026, número de pruebas de Meta).
Resultado probado: bot de reservas por menú + asistente personal (Claude) con correo completo (leer, buscar, enviados, enviar/responder con confirmación, marcar, archivar, borrar), memoria (historial, notas permanentes, registro por cliente) y resumen diario por WhatsApp, todo en la nube (Hostinger, PHP), sin PC encendido.
Última actualización: 14-oct-2026 15:40.

## Resumen en 12 pasos (orden real de instalación)
1. Fase 0: acuerdo con el cliente, RGPD, páginas legales. 2. Fase 1: portfolio, app y número real en Meta. 3. Fase 2: subir `whatsapp-bot/` y `.htaccess`. 4. Crear `config.php` en el servidor (el humano), con `app_secret` (Meta > Configuración de la app > Básica): el webhook rechaza con 403 todo mensaje sin firma válida de Meta. 5. Fase 3: webhook + suscribir la cuenta. 6. Fase 4: token permanente (el humano lo genera y pega). 7. Fase 5: probar con simulación + log. 8. Fase 6: personalizar servicios y huecos. 9. Fase 7.1-7.2: espacio de trabajo y clave Anthropic (el humano). 10. Fase 7.5: contraseña de aplicación de Gmail (el humano). 11. Sembrar memoria del dueño (7.8), probar correo/borradores, cron 9:30. 12. Fase 8: limpiar y entregar.
Tiempo real en la prueba: ~1 día con los tropiezos; con este playbook, 2-3 h más esperas de Meta.

## Reglas de oro (no negociables)
1. Claude NO escribe ni ve claves. Las pega el cliente o el responsable técnico en el servidor: token de Meta, clave Anthropic, contraseña de Gmail. El sistema bloquea generar tokens, crear claves y conceder permisos: se pide al humano que pulse.
2. Aceptar condiciones, conceder permisos y publicar la app los hace el humano o se pide permiso explícito en el chat.
3. Datos reales de clientes finales: solo con RGPD resuelto (ver Fase 0).
4. Se verifica cada paso con el registro (`log.txt`), no se da nada por hecho.

## Fase 0 - Antes de empezar (negocio)
- Qué quiere el cliente: reservas, pedidos, FAQ, avisos. Elegir UNO para el piloto.
- Datos legales: razón social, dirección, web, correo. Alta de autónomo/empresa si es real.
- Páginas legales públicas (privacidad, términos, eliminar datos) en su web. Son borrador hasta que las revise un profesional.
- RGPD: contrato de encargado de tratamiento con el cliente; la API de Anthropic procesa en EE. UU.; no enviar datos sensibles a la IA.
- Política de Meta desde 15-ene-2026: bots de IA de propósito general prohibidos en la API Business. Un bot de reservas/pedidos con función concreta sí está permitido. El asistente personal del dueño es uso propio no verificado: no ofrecerlo como producto a terceros sin comprobarlo.

## Fase 1 - Meta (portfolio comercial, app, número)
1. Portfolio comercial de Meta Business a nombre de la empresa (business.facebook.com).
2. App en developers.facebook.com, caso de uso "Conectar con WhatsApp". Nota: la app de pruebas no se reutiliza; una app por cliente.
3. Número REAL del cliente (no el de pruebas). Debe poder recibir SMS/llamada de verificación y NO estar ya en WhatsApp/WhatsApp Business (hay que borrarlo de ahí o migrarlo). Registrar con `whatsapp_biz_add_phone_number` + verificación.
4. Perfil de empresa completo (nombre legal, dirección en UNA sola línea de calle, categoría, web, correo).
5. Verificación del negocio (Paso 3 de Meta) si el volumen lo exige.
6. Método de pago en Meta: los mensajes iniciados por la empresa fuera de ventana de 24 h se cobran (plantillas). Comprobar precios actuales antes de presupuestar.
7. Publicar la app (modo Live): necesario para recibir webhooks reales. Pide permiso explícito.

## Fase 2 - Servidor (Hostinger)
1. Subir carpeta `whatsapp-bot/` a `public_html/` del dominio del cliente. API Hostinger: `hosting_files_generate-upload-url` (usuario `u...`, dominio) + subida TUS con curl (POST crear 201, PATCH subir 204). Mejor: webhook.php, asistente.php si aplica, `.htaccess` (desde `htaccess.txt`).
2. `.htaccess`: bloquea txt/json/jsonl/php salvo `webhook.php`. Verificar que `log.txt` da 403 desde fuera.
3. Crear `config.php` EN EL SERVIDOR desde `config.ejemplo.php`: verify (inventado, largo), token, phone_id.
4. Permisos: carpeta `estado/` se crea sola (0700).

## Fase 3 - Webhook
1. Meta > App > Webhooks > producto "WhatsApp Business Account": URL `https://DOMINIO/whatsapp-bot/webhook.php` + token de verificación (el `verify` de config.php). "Verificar y guardar".
2. Suscribir el campo `messages` (y los de plantillas/estado que interesen).
3. IMPORTANTE (nos costó una hora): suscribir la CUENTA de WhatsApp a la app. Con la herramienta `whatsapp_biz_subscribe_webhook` (pide confirmación). Sin esto, la URL está bien y no llega ningún mensaje.
4. Comprobar con `whatsapp_biz_onboarding_status` (único modo de leer): Webhooks=done, Account subscription=done, App published=done.

## Fase 4 - Token permanente (usuario del sistema)
El token temporal de "API Setup" caduca (en la prueba cayó con 401 "Authentication Error", code 190).
1. business.facebook.com > Configuración > Usuarios > Usuarios del sistema > Agregar. Nombre `bot-whatsapp`. Aceptar la política de no discriminación (la acepta el humano). Rol: Employee basta; Admin lo pone el humano si hace falta.
2. Asignar activos: la app (acceso total) y la cuenta de WhatsApp (mínimo: permiso "Mensajes"). El humano pulsa "Asignar activos".
3. Generar token: app del cliente, caducidad Nunca, permisos `whatsapp_business_messaging` (+ `whatsapp_business_management`). Lo genera y copia el humano.
4. El humano lo pega en `config.php` (línea `'token'`). Cuidado: dentro de comillas simples, con la coma final. Un fallo típico: falta la comilla de cierre.
5. El código limpia espacios/saltos del token (`preg_replace('/\s+/', '', ...)`): un salto de línea pegado rompía la cabecera Authorization.

## Fase 5 - Probar el bot (sin mover al cliente)
1. Mensaje saliente de prueba: POST `https://graph.facebook.com/v21.0/{phone_id}/messages` con `messaging_product:"whatsapp"` (si falta: error 400 "messaging_product is required").
2. Ventana de 24 h: texto libre solo si el usuario escribió antes. Fuera: plantilla aprobada.
3. Simular un mensaje entrante sin mover el móvil: POST de un JSON con `entry[0].changes[0].value.messages[0] = {from, type:"text", text:{body}}` al webhook y leer `log.txt` (HTTP 200 + id = enviado).
4. Prueba real: escribir "hola" al número y ver el menú. Con el número de pruebas solo llegan a los destinatarios de la lista permitida (máx. 5).
5. Leer siempre `log.txt` (en Hostinger: administrador de archivos, no la URL pública).

## Fase 6 - Personalizar el bot por empresa
`webhook.php`: cambiar `SERVICIOS`, `HUECOS`, textos de `menu()` y de confirmación. Las reservas se guardan en `reservas.jsonl` (privado). Siguiente nivel: leer huecos reales de Google Calendar y avisar al dueño por WhatsApp al reservar (usar plantilla si está fuera de ventana).
Estado por conversación: `estado/<telefono>.json` (pasos: servicio, hueco, nombre, confirmar). Escribir PERSONA permite derivar a humano.

## Fase 7 (opcional) - Asistente personal del dueño
Solo el número `owner` de `config_asistente.php`. Todo lo que escribe va a Claude; si empieza por "reservas" va al bot (para probarlo). Los clientes ven siempre el bot.
1. Consola de Claude (platform.claude.com): espacio de trabajo propio por cliente (Ajustes > Espacios de trabajo), con límite de gasto mensual bajo, saldo prepago. Ubicación EE. UU. por defecto.
2. Clave de API dentro de ese espacio, SIN caducidad, la crea y pega el humano en `config_asistente.php` (`anthropic_key`).
3. Modelo en `asistente.php`: `claude-sonnet-5-5`. La respuesta puede traer bloques que no son texto: el código recorre `content[]` y concatena los de tipo `text`.
4. El prompt de sistema incluye fecha y hora de Europe/Madrid (si no, el asistente dice que no tiene reloj).
5. Correo (Gmail): contraseña de aplicación (verificación en 2 pasos activa). myaccount.google.com/apppasswords. Nombre "asistente WhatsApp". El humano la crea y la pega en `gmail_app_pass`. Funciones completas (clasificador con Claude que devuelve JSON con la acción): resumen de no leídos, buscar (de, para, asunto, texto, sin leer), leer cuerpos (IMAP `BODY.PEEK`, MIME multipart/base64/quoted-printable), carpeta Enviados (se descubre por `LIST` special-use `\Sent`), escribir y responder (SMTP `smtp.gmail.com:465`), marcar leído, archivar (`UID MOVE` a Todos) y borrar (a Papelera).
6. Seguridad de escritura: nada se envía ni se toca sin confirmar. El envío guarda un borrador en `estado/correo_pend.json` (caduca a los 30 min) y el dueño debe contestar "ENVÍA". Acciones masivas (marcar, archivar, borrar) piden "CONFIRMA"; más de 200 coincidencias se rechazan. "CANCELA" descarta.
7. Frases: "resúmeme el correo", "¿qué dice el último correo de X?", "mira los enviados", "escribe un correo a X diciendo...", "responde a ese correo", "marca como leídos los de X".
8. Memoria (probada 14-oct): (a) historial de los últimos 30 mensajes en `estado/hist_asistente.json`, también de las acciones de correo (si no, el asistente "no recuerda" el borrador); (b) memoria permanente `estado/mem_<tel>.json`: notas que Claude fusiona cada 3 intercambios, o al instante con "recuerda/apunta/anota/no olvides"; se inyectan en todos los prompts (chat, clasificador de correo, redactor, editor de borradores); `asistente_memorizar()` se llama DESPUÉS de enviar la respuesta; (c) memoria por cliente: `webhook.php` guarda en `estado/conv_<tel>.json` los últimos 40 intercambios de cada cliente con el bot y el dueño puede preguntar "¿qué han escrito los clientes?". Para otra empresa: una instalación = un dueño = sus notas; los clientes quedan separados por número.
9. Borradores editables: si hay un borrador pendiente y el mensaje no es ENVÍA/CANCELA, `correo_editar()` lo retoca ("más informal", "añade…", "cambia el asunto"); el destinatario solo cambia si se pide. Nunca inventar direcciones: `contacto_email()` busca primero en enviados y descarta no-reply; si no la encuentra pide la dirección.
10. Audios: `webhook.php` solo procesa `type=text`; los `audio` reciben "no puedo escuchar audios". Transcribirlos exige un servicio externo de voz a texto (Claude no transcribe) y su clave; pendiente de elegir uno con precios actuales. Flujo: descargar el media con el token de Meta y pasar el texto a `asistente_responder`.
11. Resumen diario automático: cron de Hostinger (`hosting_cron-jobs_create`, comando `/usr/bin/php /home/<usuario>/domains/<dominio>/public_html/whatsapp-bot/cron_resumen.php`). El servidor va en UTC: el cron se lanza a las 07:30 y 08:30 UTC (`30 7,8 * * *`) y el script solo envía si en Europe/Madrid son las 9:xx, así no se desajusta con el cambio de hora. Prueba manual: `php cron_resumen.php ahora`. Pedir permiso al dueño y elegir su hora. Recuerda la ventana de 24 h.

## Fase 8 - Cierre y entrega
- Borrar/rotar `log.txt`; quitar datos de prueba (`estado/hist_asistente.json`, `mem_*.json`, `conv_*.json`, `correo_pend.json`, `reservas.jsonl`); confirmar que `.htaccess` protege todo.
- Sembrar la memoria del dueño con datos VERIFICADOS (direcciones de correo de sus contactos habituales, tono, firma): "recuerda que...". Una nota falsa se repite para siempre: en la prueba una frase errónea del propio asistente ("la dirección es inventada", "no puedo enviar") quedó guardada y hubo que reescribir `estado/mem_<tel>.json` subiéndolo de nuevo.
- Entregar al cliente: qué hace, cómo se apaga (revocar token del usuario del sistema), a quién avisar, coste mensual (Meta + Anthropic + hosting).
- Anotar en el registro de sesiones y en la memoria del proyecto.

## Tabla de fallos conocidos
| Síntoma | Causa | Arreglo |
|---|---|---|
| 400 messaging_product is required | Token con salto de línea/espacios | `preg_replace('/\s+/','',token)` |
| 401 code 190 Authentication Error | Token temporal caducado | Token permanente de usuario del sistema |
| Meta acepta (200) pero no llega | Fuera de ventana de 24 h | Que el usuario escriba primero o plantilla |
| Nada llega al servidor, URL bien | Cuenta no suscrita a la app | `whatsapp_biz_subscribe_webhook` |
| Respuesta de Claude vacía | `content[0]` no es de tipo text | Recorrer bloques `text` |
| Ningún mensaje contesta tras editar config | Error de sintaxis (comilla/coma) en config*.php | Revisar la línea pegada |
| "Aún no tengo la clave de Claude" | `anthropic_key` sin pegar | Pegarla en el servidor |
| Asistente dice que no tiene hora | Falta fecha en el prompt | Añadir hora Europe/Madrid |
| "Resúmeme el correo" lo contesta como charla normal | El detector no reconocía tildes (resúmeme) | Regex con `res[uú]m`, `l[eé]e`, etc. (ya corregido en asistente.php) |
| "No puedo leer el correo todavía..." | Contraseña de Gmail sin guardar/mal pegada | Ver tamaño de config_asistente.php en el administrador de archivos (si no cambió, no se guardó) y repetir |
| Meta HTTP 500 "Something went wrong" al enviar | Fallo temporal de Meta | `enviar()` reintenta hasta 3 veces (ya en webhook.php) |
| Escribe el dueño y sale el menú de reservas | Enrutado por palabra "claude" | Ahora todo lo del dueño va a Claude; prefijo `reservas` para probar el bot |
| "No tengo memoria de ningún borrador" / cambia la dirección al pedir otro tono | El chat general no veía el borrador y el mensaje no se trataba como edición | `correo_editar()` + historial compartido (`hist_anotar`) (ya en asistente.php) |
| Inventa direcciones de correo | El chat respondía sin datos reales | Prompts: nunca inventar; `contacto_email()` busca en enviados, ignora no-reply y si no halla pide la dirección; memoria con direcciones verificadas |
| El asistente afirma cosas falsas sobre sí mismo (p. ej. "no puedo enviar") y lo repite | Las notas permanentes recogieron una frase errónea | Corregir diciéndole "recuerda y corrige: ..." o reescribir `estado/mem_<tel>.json`; revisar las notas tras las primeras pruebas |
| Un audio no recibe respuesta | Solo se procesa `type=text` | Ya avisa "no puedo escuchar audios"; transcripción pendiente (7.10) |
| Los cambios en `asistente.php` no se notan / el log no muestra nada | Respuestas lentas (IMAP + 2 llamadas a Claude, 10-30 s) | Esperar y recargar `log.txt`; el estado de `estado/` muestra si se está escribiendo |
| 403 al abrir File Browser | Sesión caducada | Reabrir desde hPanel > Archivos > Administrador de archivos |

## Un cliente = una instalación
Cada cliente nuevo = app, portfolio, número y espacio de trabajo propios (no reutilizar identificadores de otra instalación).

## Archivos de esta plantilla
- `tests/run.php`: pruebas automáticas sin red real (`php tests/run.php`).
- `php/webhook.php`: comprueba la firma de Meta, ignora mensajes repetidos, recibe mensajes, bot de reservas, registro por cliente (`conv_<tel>.json`), aviso de audios y desvío al asistente.
- `php/asistente.php`, `php/cron_resumen.php`: asistente de Claude (correo completo, memoria, borradores editables) y resumen diario.
- `php/config.ejemplo.php`, `php/config_asistente.ejemplo.php`: modelos sin secretos.
- `php/htaccess.txt`: protección (subir como `.htaccess`).
- `bot.js`, `test.js`: prototipo del flujo con prueba automática (`node test.js`).

## Anexo - Trucos de la sesión (lo que ahorra tiempo)
- Reglas del sistema: Claude no genera tokens, no crea claves, no concede permisos ni acepta condiciones, y no lee archivos con secretos. Preparar la pantalla (abrir página, escribir nombres, marcar casillas) y pedir al humano solo el último clic o pegado.
- Con muchas ventanas el humano se pierde: dejar SOLO las pestañas necesarias (Google/Meta + archivo a editar) y decir el título exacto de la pestaña.
- Para mirar el estado sin ver secretos: tamaño y fecha de `config*.php` en el administrador de archivos (un cambio de tamaño confirma que se guardó) y `log.txt`.
- Probar sin mover el móvil: POST simulado al webhook desde la pestaña del administrador de archivos (`fetch` con `mode:'no-cors'`, cuerpo JSON de mensaje entrante) y leer `log.txt`.
- Subir archivos: `hosting_files_generate-upload-url` da claves que caducan en ~6 min; usar en el mismo bloque POST (201) + PATCH (204).
- Gmail: contraseña de aplicación creada por el humano en myaccount.google.com/apppasswords; solo se muestra una vez. Resultado probado: resume los últimos 15 sin leer (en la prueba había 1799), busca, lee cuerpos y mira enviados.
- Enrutado final del número del dueño: todo a Claude; `reservas ...` al bot. Clientes siempre al bot.
