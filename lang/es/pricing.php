<?php

// Las líneas de las tarjetas de precios (App\Support\Pricing), por clave de característica en config/packstub-features.php.
// Una característica sin línea aquí usa su etiqueta y su unidad de la configuración.
return [

    // `:count` es el límite del plan.
    'limits' => [
        'projects' => '{1} :count proyecto|[0,*] :count proyectos',
        'seats' => '{1} :count plaza|[0,*] :count plazas',
    ],

    'unlimited_limits' => [
        'projects' => 'Proyectos ilimitados',
        'seats' => 'Plazas ilimitadas',
    ],

    // Para un límite sin línea arriba: `:unit` es la unidad de la característica en la configuración.
    'unlimited' => ':unit ilimitado',

    'features' => [
        'api_access' => 'Acceso a la API',
        'priority_support' => 'Soporte prioritario',
    ],

];
