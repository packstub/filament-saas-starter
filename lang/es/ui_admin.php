<?php

// El panel de administración: el panel de ingresos y los recursos Usuarios, Espacios de trabajo y Actividad.
return [

    'revenue' => [
        'mrr' => 'Ingresos recurrentes mensuales',
        'per_year' => ':amount al año',
        'subscriptions' => 'Suscripciones de pago',
        'subscriptions_description' => 'Activas o reintentando un pago',
        'trials' => 'En prueba gratuita',
        'trials_description' => 'Aún no cuentan en los ingresos',
        'churned' => 'Bajas, últimos 30 días',
        'no_subscriptions' => 'Aún no hay suscripciones',
        'churn_rate' => ':rate% de las suscripciones',
    ],

    'growth' => [
        'heading' => 'Registros y nuevos espacios de trabajo',
        'description' => 'Últimos 30 días: :users registros, :workspaces nuevos espacios de trabajo, :changes cambios de plan.',
        'sign_ups' => 'Registros',
        'new_workspaces' => 'Nuevos espacios de trabajo',
    ],

    'plan_distribution' => [
        'heading' => 'Espacios de trabajo por plan',
        'dataset' => 'Espacios de trabajo',
    ],

    'revenue_by_plan' => [
        'heading' => 'Ingresos recurrentes mensuales por plan',
        'dataset' => 'MRR (:currency)',
    ],

    'users' => [
        'model' => 'usuario',
        'plural_model' => 'usuarios',
        'title' => 'Usuario',
        'plural_title' => 'Usuarios',
        'columns' => [
            'name' => 'Nombre',
            'email' => 'Correo electrónico',
            'workspaces' => 'Espacios de trabajo',
            'admin' => 'Administrador',
            'signed_up' => 'Registrado',
        ],
    ],

    'workspaces' => [
        'model' => 'espacio de trabajo',
        'plural_model' => 'espacios de trabajo',
        'title' => 'Espacio de trabajo',
        'plural_title' => 'Espacios de trabajo',
        'columns' => [
            'name' => 'Nombre',
            'plan' => 'Plan',
            'subscription' => 'Suscripción',
            'none' => 'Ninguna',
            'members' => 'Miembros',
            'created' => 'Creado',
        ],
        'actions' => [
            'activity' => 'Actividad',
            'change_plan' => 'Cambiar de plan',
        ],
        'fields' => [
            'plan' => 'Plan',
            'plan_help' => 'Una suscripción de Stripe vuelve a fijar el plan cada vez que cambia.',
        ],
        'plan_changed' => 'Plan cambiado',
    ],

    'activity' => [
        'navigation' => 'Actividad',
        'model' => 'entrada de actividad',
        'plural_model' => 'entradas de actividad',
        'title' => 'Entrada de actividad',
        'plural_title' => 'Entradas de actividad',
        'columns' => [
            'workspace' => 'Espacio de trabajo',
            'event' => 'Evento',
            'description' => 'Descripción',
            'by' => 'Por',
            'when' => 'Cuándo',
        ],
        'filters' => [
            'event' => 'Evento',
            'workspace' => 'Espacio de trabajo',
            'support_logins' => 'Durante un inicio de sesión de soporte',
        ],
    ],

];
