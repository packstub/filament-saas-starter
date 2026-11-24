<?php

// La página de facturación: planes, avisos de configuración de Stripe, pruebas, pagos y sus botones.
return [

    'title' => 'Facturación',

    'subheading' => 'Estás en el plan :plan.',

    'checkout_success' => [
        'title' => '¡Gracias!',
        'body' => 'Tu plan se actualiza en cuanto Stripe confirme el pago.',
    ],

    'plan_active' => 'Estás en el plan :plan',

    'intervals' => [
        'monthly' => 'Mensual',
        'yearly' => 'Anual',
    ],

    'interval_label' => 'Periodo de facturación',

    'setup' => [
        'heading' => 'Añade tus claves de Stripe',
        'description' => 'La facturación está lista y espera una cuenta de Stripe. Hasta entonces, cada espacio de trabajo sigue en su plan actual.',
        'step_product' => 'En el panel de Stripe (modo de prueba), crea un producto para cada plan de pago, con un precio recurrente mensual y, si lo vendes también al año, uno anual.',
        'step_keys' => 'Rellena estas claves en <code>.env</code>:',
        'step_webhook' => 'Reenvía los webhooks de Stripe a esta aplicación:',
    ],

    'coming_soon' => [
        'heading' => 'Los planes de pago están en camino',
        'description' => 'Mientras tanto, puedes seguir trabajando con tu plan actual.',
    ],

    'trial' => [
        'heading' => 'Tu prueba gratuita termina el :date',
        'description' => 'Entonces se cobrará a tu tarjeta y el plan continuará. Cancela cuando quieras antes, en «Gestionar facturación».',
        'badge' => ':days día de prueba gratuita|:days días de prueba gratuita',
    ],

    'first_payment' => [
        'heading' => 'Tu pago aún no está terminado',
        'description' => 'Complétalo para empezar tu plan. Hasta entonces, el espacio de trabajo sigue en su plan actual.',
    ],

    'paused' => [
        'heading' => 'Tu prueba terminó sin un método de pago',
        'description' => 'Añade una tarjeta en «Gestionar facturación» y reanuda tu suscripción. Hasta entonces, el espacio de trabajo está en el plan gratuito.',
    ],

    'payment_failed' => [
        'heading' => 'Tu último pago no se completó',
        'description' => 'Actualiza tu método de pago para conservar tu plan. Podrás cambiar de plan otra vez cuando el pago esté resuelto.',
    ],

    'plan' => [
        'current' => 'Plan actual',
        'per_seat' => 'Por miembro: :count miembro hoy, se actualiza cuando la gente entra y sale.|Por miembro: :count miembros hoy, se actualiza cuando la gente entra y sale.',
        'cancel_to_switch' => 'Cancela tu suscripción en «Gestionar facturación» para pasar a este plan.',
    ],

    'actions' => [
        'manage' => 'Gestionar facturación',
        'complete_payment' => 'Completar el pago',
        'resume' => 'Reanudar suscripción',
        'choose' => 'Elegir :plan',
        'switch_to' => 'Cambiar a :plan',
        'switch_interval' => 'Cambiar a facturación :interval',
        'change_plan_description' => 'Tu suscripción pasa a este plan ahora y Stripe prorratea la diferencia.',
    ],

    'stripe_failed' => [
        'title' => 'Stripe no pudo completar la operación',
        'try_again' => 'Inténtalo de nuevo en unos instantes.',
    ],

];
