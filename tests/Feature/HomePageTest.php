<?php

use function Pest\Laravel\get;

it('shows the home page with the way in', function () {
    $this->withoutVite();

    get('/')
        ->assertOk()
        ->assertSee('/app/register')
        ->assertSee('/app/login');
});
