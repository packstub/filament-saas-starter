<?php

// La página de actividad (el registro de auditoría del espacio de trabajo) y las frases de sus entradas.
return [

    'title' => 'Actividad',

    'events' => [
        'member_added' => 'Miembro añadido',
        'member_removed' => 'Miembro eliminado',
        'role_changed' => 'Rol cambiado',
        'invitation_sent' => 'Invitación enviada',
        'invitation_accepted' => 'Invitación aceptada',
        'ownership_transferred' => 'Propiedad transferida',
        'plan_changed' => 'Plan cambiado',
        'api_token_created' => 'Token de API creado',
        'api_token_revoked' => 'Token de API revocado',
        'workspace_deleted' => 'Espacio de trabajo eliminado',
    ],

    'columns' => [
        'event' => 'Evento',
        'description' => 'Descripción',
        'by' => 'Por',
        'when' => 'Cuándo',
    ],

    'empty' => 'Aún no ha pasado nada',

    'system' => 'Sistema',

    'actor_with_support' => ':actor (soporte: :support)',

    'descriptions' => [
        'member_added' => ':name se unió como :role',
        'member_removed' => ':name ya no es miembro',
        'role_changed' => ':name pasó de :from a :to',
        'invitation_sent_email' => 'Invitó a :email como :role',
        'invitation_sent_link' => 'Creó un enlace de invitación para :role',
        'invitation_accepted' => ':name aceptó una invitación',
        'ownership_transferred' => 'La propiedad pasó de :from a :to',
        'api_token_created' => 'Creó el token de API :name (:abilities)',
        'api_token_revoked' => 'Revocó el token de API :name',
        'api_token_revoked_from' => 'Revocó el token de API :name (:from ya no gestiona el espacio de trabajo)',
        'plan_changed' => 'Plan cambiado de :from a :to',
        'workspace_deleted' => 'Eliminó el espacio de trabajo :workspace',
    ],

];
