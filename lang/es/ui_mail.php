<?php

// Los correos que envía la propia aplicación, y la línea bajo cada correo sobre un espacio de trabajo.
return [

    'sent_by' => 'Enviado por :app para :workspace.',

    'payment_failed' => [
        'subject' => 'No pudimos cobrar el pago de :workspace',
        'heading' => 'Tu pago no se ha completado',
        'body' => 'Stripe no pudo cobrar el último pago de **:workspace**. Lo volverá a intentar durante los próximos días y, mientras tanto, el espacio de trabajo conserva su plan.',
        'update_card' => 'Actualiza la tarjeta en la página de Facturación para resolverlo ahora.',
        'button' => 'Abrir Facturación',
        'reason' => 'Recibes este correo como propietario de :workspace.',
        'database_title' => 'El pago de :workspace ha fallado',
        'database_body' => 'Actualiza la tarjeta en la página de Facturación para conservar tu plan.',
        'database_action' => 'Abrir Facturación',
    ],

    'workspace_deleted' => [
        'subject' => ':workspace se ha eliminado',
        'heading' => ':workspace se ha eliminado',
        'deleted_by' => ':person ha eliminado el espacio de trabajo **:workspace**.',
        'deleted' => 'El espacio de trabajo **:workspace** se ha eliminado.',
        'gone' => 'Sus proyectos, miembros e invitaciones ya no existen, y su suscripción se ha cancelado.',
        'unaffected' => 'Tu cuenta y tus otros espacios de trabajo no se ven afectados.',
        'button' => 'Abrir :app',
    ],

    'account_deleted' => [
        'subject' => 'Tu cuenta de :app se ha eliminado',
        'heading' => 'Tu cuenta se ha eliminado',
        'greeting' => 'Hola, :name.',
        'body' => 'Tu cuenta de :app se ha eliminado, junto con todos los espacios de trabajo en los que eras el único miembro. Has salido de los demás espacios de trabajo en los que estabas, y sus propietarios los conservan.',
        'contact' => 'Si no la eliminaste tú, responde a este correo o escribe a :email.',
    ],

];
