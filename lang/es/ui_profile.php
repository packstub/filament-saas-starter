<?php

// La página de perfil: foto, cuentas conectadas, descarga de datos y eliminación de la cuenta.
return [

    'avatar' => 'Foto de perfil',

    'connected' => [
        'heading' => 'Cuentas conectadas',
        'with_password' => 'Inicia sesión con ellas además de con tu contraseña.',
        'without_password' => 'Inicias sesión con ellas. Elige una contraseña arriba para tener otra forma de entrar.',
        'connect' => 'Conectar :provider',
        'disconnect' => 'Desconectar :provider',
        'disconnect_with_email' => 'Desconectar :provider (:email)',
        'disconnect_heading' => '¿Desconectar :provider?',
        'disconnect_description' => 'Ya no podrás iniciar sesión con :provider.',
        'only_way' => 'Elige primero una contraseña: es tu única forma de iniciar sesión.',
        'disconnected' => ':provider se ha desconectado.',
        'fallback_provider' => 'cuenta',
    ],

    'data' => [
        'heading' => 'Tus datos',
        'description' => 'Tu cuenta, tus espacios de trabajo y roles, tus notificaciones y cada acceso de soporte a tu cuenta, en un único archivo JSON.',
        'download' => 'Descargar tus datos',
    ],

    'delete' => [
        'heading' => 'Eliminar cuenta',
        'description' => 'Tu cuenta, y cada espacio de trabajo en el que seas el único miembro, se eliminan para siempre.',
        'action' => 'Eliminar cuenta',
        'modal_heading' => '¿Eliminar tu cuenta?',
        'blocked' => 'Primero cede la propiedad de estos espacios de trabajo en Miembros, o elimínalos: :workspaces.',
        'modal_description' => 'Saldrás de todos los espacios de trabajo que compartes con otras personas. Los espacios de trabajo en los que eres el único miembro se eliminan y sus suscripciones se cancelan. Esta acción no se puede deshacer.',
        'confirm_email' => 'Escribe tu dirección de correo electrónico para confirmar',
        'wrong_email' => 'Esa no es tu dirección de correo electrónico.',
        'password' => 'Tu contraseña',
        'hand_over_first' => 'Primero cede o elimina los espacios de trabajo de los que eres el único propietario',
        'stripe_failed' => 'No se pudo cancelar una suscripción, así que tu cuenta se ha conservado',
        'try_again' => 'Inténtalo de nuevo en un momento.',
    ],

];
