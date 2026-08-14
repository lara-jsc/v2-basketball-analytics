<?php

it('documents every env var the config layer actually reads', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)
        ->toContain('PYTHON_BIN=')
        ->toContain('PYTHON_ENGINE_PATH=')
        ->toContain('DEMO_PASSWORD=')
        ->toContain('REVERB_SERVER_HOST=')
        ->toContain('REVERB_SERVER_PORT=');
});

it('does not advertise the removed HTTP analytics service', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->not->toContain('PYTHON_ANALYTICS_URL');
});
