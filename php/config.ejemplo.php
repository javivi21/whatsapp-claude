<?php return [
  // Copiar como config.php EN EL SERVIDOR. Los valores reales los pega el cliente o su responsable técnico, nunca Claude.
  'verify'     => 'INVENTAR_UN_TEXTO_LARGO_ALEATORIO',   // el mismo que se pone en Meta > Webhooks
  'token'      => 'PEGAR_TOKEN_USUARIO_SISTEMA_META',     // permanente, sin caducidad
  'phone_id'   => 'ID_DEL_NUMERO_DE_TELEFONO',            // Meta > WhatsApp > Configuración de la API
  'app_secret' => 'PEGAR_SECRETO_DE_LA_APP_META',         // Meta > Configuración de la app > Básica. Activa la comprobación de firma
  'negocio'    => 'Nombre del negocio',                   // sale en el saludo del bot
  'dueno'      => 'el equipo',                            // quién atiende cuando el cliente escribe PERSONA
  // Atención a clientes con Claude (pedidos y dudas). Quitar estas líneas para usar solo el menú sencillo.
  'ia_clientes'   => false,                                  // true = Claude atiende a los clientes
  'anthropic_key' => 'PEGAR_CLAVE_ANTHROPIC_AQUI',           // espacio de trabajo propio con límite de gasto
  'aviso'         => '34XXXXXXXXX',                          // móvil que recibe los pedidos, sin +
  'catalogo'      => ['1' => ['nombre' => 'Producto A', 'precio' => 12.5, 'desc' => 'Descripción corta']],
  'info'          => 'Horario, envíos, devoluciones, formas de pago...', // lo que Claude puede contar del negocio
  // Opcionales: 'servicios' => ['1' => ['Corte', 15, '30 min'], ...], 'huecos' => ['Mañana 10:00', ...], 'api_base' => 'https://graph.facebook.com/v21.0'
];
