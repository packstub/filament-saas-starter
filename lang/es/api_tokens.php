<?php

// La página de tokens de API: crear, listar y revocar los tokens de un espacio de trabajo.
return [

    'title' => 'Tokens de API',

    'subheading' => 'Deja que tus scripts e integraciones usen la API en :url en nombre de este espacio de trabajo.',

    'abilities' => [
        'projects:read' => 'Leer proyectos',
        'projects:write' => 'Crear proyectos',
    ],

    'actions' => [
        'create' => 'Nuevo token de API',
        'create_submit' => 'Crear token',
        'upgrade' => 'Mejora tu plan para usar la API',
        'revoke' => 'Revocar',
        'revoke_heading' => '¿Revocar :name?',
        'revoke_description' => 'Todo lo que use este token dejará de funcionar de inmediato. No se puede deshacer.',
    ],

    'fields' => [
        'name' => 'Nombre',
        'name_help' => 'Qué lo usa: «Zapier», «Importación nocturna».',
        'abilities' => 'Puede',
        'expires' => 'Caduca',
    ],

    'expires_in' => [
        '30' => 'En 30 días',
        '90' => 'En 90 días',
        '365' => 'En un año',
        'never' => 'Nunca',
    ],

    'columns' => [
        'name' => 'Nombre',
        'abilities' => 'Puede',
        'created_by' => 'Creado por',
        'created_by_empty' => 'Alguien que se fue',
        'last_used' => 'Último uso',
        'expires' => 'Caduca',
        'never' => 'Nunca',
        'created' => 'Creado',
    ],

    'empty' => [
        'heading' => 'Aún no hay tokens de API',
        'with_access' => 'Crea uno para llamar a la API desde un script u otra aplicación.',
        'without_access' => 'El acceso a la API viene con un plan de pago.',
    ],

    'reveal' => [
        'heading' => 'Copia tu nuevo token ahora',
        'description' => 'Solo se muestra esta vez. Envíalo como token bearer: <code>Authorization: Bearer &lt;token&gt;</code>.',
        'aria_label' => 'Tu nuevo token de API',
        'copy' => 'Copiar',
        'copied' => 'Copiado',
        'done' => 'Listo',
    ],

];
