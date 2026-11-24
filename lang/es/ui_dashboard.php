<?php

// Los widgets del panel: el resumen del plan y la lista para empezar.
return [

    'overview' => [
        'plan' => 'Plan',
        'projects' => 'Proyectos',
        'seats' => 'Plazas',
        'seats_description' => 'Miembros e invitaciones pendientes',
        'trial_ends' => 'La prueba termina el :date',
        'of' => ':used de :limit',
    ],

    'getting_started' => [
        'heading' => 'Primeros pasos',
        'progress' => ':done de :total completados. Cada paso se marca solo cuando lo completas.',
        'dismiss' => 'Descartar',
        'aria_label' => 'Primeros pasos',
        'done' => '(completado)',
        'steps' => [
            'invite' => [
                'title' => 'Invita a un compañero',
                'description' => 'Trabaja en equipo: invita a personas por correo electrónico o comparte un enlace.',
                'action' => 'Invitar',
            ],
            'project' => [
                'title' => 'Crea tu primer proyecto',
                'description' => 'Todo en lo que trabaja tu equipo empieza con un proyecto.',
                'action' => 'Crear un proyecto',
            ],
            'plan' => [
                'title' => 'Elige un plan',
                'description' => 'Más proyectos, más plazas y la API, cuando los necesites.',
                'action' => 'Ver planes',
            ],
            'two-factor' => [
                'title' => 'Activa la autenticación en dos pasos',
                'description' => 'Mantén tu cuenta segura con un código de una aplicación de autenticación.',
                'action' => 'Configurarla',
            ],
        ],
    ],

];
