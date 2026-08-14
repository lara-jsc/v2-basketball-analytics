<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Account Password
    |--------------------------------------------------------------------------
    |
    | DemoUserSeeder runs automatically against an empty production database
    | (see docker/start.sh). Those accounts sit on a public URL, so the
    | password is read from the environment rather than committed here.
    |
    */

    'password' => env('DEMO_PASSWORD', 'password123'),

];
