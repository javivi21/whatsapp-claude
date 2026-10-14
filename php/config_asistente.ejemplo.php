<?php return [
  // Solo si se monta el asistente personal del dueño (opcional). Copiar como config_asistente.php EN EL SERVIDOR.
  'owner'          => '34XXXXXXXXX',                    // móvil del dueño, sin + ni espacios
  'anthropic_key'  => 'PEGAR_CLAVE_ANTHROPIC_AQUI',     // espacio de trabajo propio, con límite de gasto
  'gmail_user'     => 'correo@ejemplo.com',
  'gmail_app_pass' => 'PEGAR_CONTRASENA_APLICACION_AQUI',
  'nombre'         => 'Ana',                            // cómo se llama el dueño (firma de correos y charla)
  'perfil'         => 'peluquera en Madrid',            // opcional: a qué se dedica, ayuda a Claude
  // Opcionales: 'dominio_correo' => 'minegocio.com', 'modelo' => 'claude-sonnet-5-5'
];
