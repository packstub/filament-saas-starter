<?php

// Ajustes del espacio de trabajo y la página "Nuevo espacio de trabajo".
return [

    'settings' => 'Ajustes del espacio de trabajo',
    'new' => 'Nuevo espacio de trabajo',

    'logo' => [
        'label' => 'Logotipo',
        'help' => 'Se muestra en el menú del espacio de trabajo y en la parte superior de los correos sobre este espacio. PNG, JPEG o WebP; lo cuadrado funciona mejor.',
    ],

    'export' => [
        'heading' => 'Exportar datos del espacio de trabajo',
        'description' => 'El espacio de trabajo, sus miembros, invitaciones, suscripciones, excepciones y proyectos, en un único archivo JSON.',
        'action' => 'Descargar datos del espacio de trabajo',
    ],

    'delete' => [
        'heading' => 'Eliminar espacio de trabajo',
        'description' => 'Sus proyectos, miembros, invitaciones y suscripción se eliminan con él, para siempre.',
        'action' => 'Eliminar espacio de trabajo',
        'modal_heading' => '¿Eliminar :workspace?',
        'modal_description' => 'Sus proyectos, miembros, invitaciones y suscripción se eliminan, y la suscripción se cancela ahora. Esta acción no se puede deshacer.',
        'confirm_name' => 'Escribe el nombre del espacio de trabajo para confirmar',
        'wrong_name' => 'Ese no es el nombre de este espacio de trabajo.',
        'stripe_failed' => 'No se pudo cancelar la suscripción, así que el espacio de trabajo se ha conservado',
        'try_again' => 'Inténtalo de nuevo en un momento.',
        'deleted' => 'Espacio de trabajo eliminado',
    ],

];
