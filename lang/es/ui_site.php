<?php

// El sitio público: el diseño, la página de inicio y las páginas de ejemplo de Términos y Privacidad.
// El eslogan, la descripción y la empresa vienen de config/site.php, no de aquí.
return [

    'layout' => [
        'skip' => 'Saltar al contenido',
        'main_nav' => 'Principal',
        'features' => 'Funciones',
        'pricing' => 'Precios',
        'faq' => 'Preguntas frecuentes',
        'open_app' => 'Abrir la aplicación',
        'log_in' => 'Iniciar sesión',
        'sign_up' => 'Registrarse',
        'copyright' => '© :year :company. Todos los derechos reservados.',
        'footer_nav' => 'Pie de página',
        'terms' => 'Términos',
        'privacy' => 'Privacidad',
        'contact' => 'Contacto',
    ],

    'home' => [
        'trial_badge' => 'Prueba gratuita de :days días en todos los planes de pago',
        'create_workspace' => 'Crea tu espacio de trabajo',
        'see_pricing' => 'Ver precios',

        // La imagen de la aplicación en la cabecera, dibujada en HTML.
        'mock' => [
            'plan' => 'Plan Pro',
            'projects' => 'Proyectos',
            'seats' => 'Plazas',
            'this_week' => 'Esta semana',
            'of' => ':used de :total',
            'status' => [
                'active' => 'Activo',
                'on_hold' => 'En pausa',
                'archived' => 'Archivado',
            ],
            'project_names' => [
                'website' => 'Rediseño web',
                'mobile' => 'App móvil',
                'campaign' => 'Campaña de primavera',
                'move' => 'Mudanza de oficina',
            ],
        ],

        'features' => [
            'heading' => 'Todo lo que tu equipo necesita para empezar',
            'intro' => 'Regístrate, invita a tu gente y ponte manos a la obra en minutos.',
            'items' => [
                'workspace' => [
                    'title' => 'Un espacio de trabajo para cada equipo',
                    'body' => 'Todo vive en un espacio de trabajo: tus proyectos, tu gente, tu plan. Pertenece a tantos como quieras y cambia de uno a otro con un clic.',
                ],
                'team' => [
                    'title' => 'Lleva a tu equipo contigo',
                    'body' => 'Invita a personas por correo electrónico o con un enlace. Propietarios, administradores y miembros ven y hacen lo que su rol permite.',
                ],
                'plans' => [
                    'title' => 'Planes que crecen contigo',
                    'body' => 'Empieza con el plan gratuito y sube de plan cuando necesites más espacio. Tu uso siempre está a un clic.',
                ],
                'security' => [
                    'title' => 'Seguro por defecto',
                    'body' => 'Direcciones de correo verificadas, contraseñas seguras y verificación en dos pasos con cualquier aplicación de autenticación.',
                ],
                'activity' => [
                    'title' => 'Sabe quién hizo qué',
                    'body' => 'Un registro de actividad muestra quién se unió, quién se fue, quién cambió de rol y cuándo cambió el plan.',
                ],
                'data' => [
                    'title' => 'Tus datos siguen siendo tuyos',
                    'body' => 'Descárgalo todo en un solo archivo cuando quieras y cierra tu cuenta o tu espacio de trabajo tú mismo.',
                ],
            ],
        ],

        'pricing' => [
            'heading' => 'Precios sencillos para cada equipo',
            'intro' => 'Empieza gratis y mejora tu plan cuando necesites más.',
            'trial' => 'Los planes de pago empiezan con una prueba gratuita de :days días.',
            'interval' => 'Periodo de facturación',
            'intervals' => [
                'monthly' => 'Mensual',
                'yearly' => 'Anual',
            ],
            'most_popular' => 'El más popular',
            'contact_us' => 'Contáctanos',
            'per_seat' => 'por plaza',
            'cta_free' => 'Empieza gratis',
            'cta_trial' => 'Empieza tu prueba gratuita',
            'cta' => 'Empezar',
            'cta_plan' => 'con el plan :plan',
            'note' => 'Eliges un plan de pago desde la página de Facturación de tu espacio de trabajo, después de registrarte.',
        ],

        'faq' => [
            'heading' => 'Preguntas y respuestas',
            'items' => [
                'free' => [
                    'q' => '¿Puedo probarlo gratis?',
                    'a' => 'Sí. Cada nuevo espacio de trabajo empieza con el plan gratuito, sin necesidad de tarjeta.',
                    'a_trial' => 'Sí. Cada nuevo espacio de trabajo empieza con el plan gratuito, sin necesidad de tarjeta. Los planes de pago incluyen una prueba gratuita de :days días, así que puedes probarlo todo antes de tu primer pago.',
                ],
                'billed' => [
                    'q' => '¿A quién se factura: a mí o a mi equipo?',
                    'a' => 'Al espacio de trabajo. Una suscripción cubre a todos sus miembros, y los propietarios y administradores la gestionan desde la página de Facturación.',
                ],
                'change' => [
                    'q' => '¿Puedo cambiar de plan más adelante?',
                    'a' => 'Cuando quieras. Al subir de plan tienes los nuevos límites de inmediato; al bajar, conservas todo lo que ya tienes.',
                ],
                'cancel' => [
                    'q' => '¿Cómo cancelo?',
                    'a' => 'Desde la página de Facturación, en un par de clics. Tu espacio de trabajo pasa al plan gratuito al terminar el periodo que has pagado.',
                ],
                'data' => [
                    'q' => '¿Cómo se protegen mis datos?',
                    'a' => 'Cada espacio de trabajo solo ve sus propios datos. Puedes activar la verificación en dos pasos y descargar o eliminar tus datos cuando quieras.',
                ],
            ],
            // `:email` es un enlace mailto creado por la vista.
            'contact' => '¿Algo más? Escríbenos a :email.',
        ],

        'cta' => [
            'heading' => 'Listo cuando tú lo estés',
            'body' => 'Crea tu espacio de trabajo en menos de un minuto e invita a tu equipo.',
            'button' => 'Empieza gratis',
        ],
    ],

    'legal' => [
        'placeholder_title' => 'Sustitúyelo por el tuyo.',
        'placeholder_body' => 'Esta página es un texto de ejemplo que viene con el kit de inicio, no asesoramiento legal. Redáctala con alguien que conozca la ley del lugar donde estáis tú y tus clientes.',
        'updated' => 'Última actualización: :date',
        'date' => '[fecha]',
        'description' => ':title de :app.',
        // `:email` es un enlace mailto creado por la vista.
        'contact_terms' => 'Preguntas sobre estos términos: :email.',
        'contact_privacy' => 'Preguntas sobre tus datos: :email.',
        'contact' => 'Contacto',
    ],

    'terms' => [
        'title' => 'Términos del servicio',
        'intro' => 'Estos términos se aplican cuando usas :app (el «servicio»), prestado por :company. Al crear una cuenta, los aceptas.',
        'account' => [
            'heading' => 'Tu cuenta',
            'body' => 'Eres responsable de mantener seguros tus datos de acceso y de lo que ocurra con tu cuenta. Avísanos de inmediato si crees que otra persona la ha usado.',
        ],
        'workspaces' => [
            'heading' => 'Espacios de trabajo y tu equipo',
            'body' => 'Un espacio de trabajo pertenece a sus propietarios. Los propietarios y administradores deciden quién se une y qué puede hacer cada miembro, y son responsables de las personas que invitan.',
        ],
        'plans' => [
            'heading' => 'Planes y pagos',
            'billing' => 'Los planes de pago se facturan por espacio de trabajo, por adelantado, de forma mensual o anual. Los pagos los gestiona nuestro proveedor de pagos.',
            'placeholder' => '[Describe las pruebas gratuitas, las renovaciones, los reembolsos, los impuestos y los cambios de precio.]',
            'cancel' => 'Puedes cancelar en cualquier momento desde la página de Facturación; el plan sigue activo hasta el final del periodo que has pagado.',
        ],
        'content' => [
            'heading' => 'Tu contenido',
            'body' => 'Lo que incorporas al servicio sigue siendo tuyo. Nos autorizas a almacenarlo y procesarlo solo para prestarte el servicio. Puedes descargarlo o eliminarlo en cualquier momento.',
        ],
        'use' => [
            'heading' => 'Uso aceptable',
            'body' => '[Describe lo que los clientes no pueden hacer con el servicio.]',
        ],
        'availability' => [
            'heading' => 'Disponibilidad y cambios',
            'body' => '[Describe el nivel de servicio que ofreces, cómo anuncias los cambios en el servicio y en estos términos, y cómo puede cualquiera de las partes dar por terminado el acuerdo.]',
        ],
        'liability' => [
            'heading' => 'Responsabilidad',
            'body' => '[Tu limitación de responsabilidad y la ley aplicable.]',
        ],
    ],

    'privacy' => [
        'title' => 'Política de privacidad',
        'intro' => 'Esta política explica qué datos personales recoge :company cuando usas :app, por qué, y qué puedes hacer al respecto.',
        'collect' => [
            'heading' => 'Qué recogemos',
            'account' => '<strong>Tu cuenta:</strong> tu nombre, tu correo electrónico y una contraseña cifrada; el secreto de la verificación en dos pasos, si la activas.',
            'workspaces' => '<strong>Tus espacios de trabajo:</strong> lo que tú y tu equipo añadís, quién es miembro y con qué rol, y las invitaciones.',
            'activity' => '<strong>Actividad:</strong> un registro de los cambios de miembros, de roles y de plan en cada espacio de trabajo, conservado durante [365 días].',
            'billing' => '<strong>Facturación:</strong> tu plan y tu suscripción. Los datos de la tarjeta van a nuestro proveedor de pagos, Stripe, y nunca llegan a nuestros servidores.',
            'technical' => '<strong>Datos técnicos:</strong> una cookie de sesión que mantiene tu sesión iniciada y los registros del servidor.',
        ],
        'why' => [
            'heading' => 'Para qué los usamos',
            'body' => 'Para prestar el servicio, facturarlo, mantenerlo seguro y responderte cuando nos escribes. [Añade cualquier otra finalidad, como correos sobre el producto o analítica, y su base legal.]',
        ],
        'who' => [
            'heading' => 'Quién más los ve',
            'body' => 'Los proveedores que nos ayudan a prestar el servicio: [alojamiento], Stripe para los pagos, [envío de correo]. [Indica cada uno y dónde almacena los datos.]',
        ],
        'rights' => [
            'heading' => 'Tus derechos',
            'download' => 'Descarga todo lo que tenemos sobre ti desde tu página de Perfil, en un solo archivo.',
            'delete' => 'Elimina tu cuenta desde tu página de Perfil; los propietarios de un espacio de trabajo pueden exportarlo o eliminarlo desde sus ajustes.',
            'placeholder' => '[Describe cómo solicitar una corrección, oponerte a un uso o presentar una reclamación ante una autoridad de control.]',
        ],
        'cookies' => [
            'heading' => 'Cookies',
            'body' => 'Solo usamos las cookies que el servicio necesita para funcionar: una para tu sesión y otra que protege los formularios. [Indica las demás, si las hay.]',
        ],
    ],

];
