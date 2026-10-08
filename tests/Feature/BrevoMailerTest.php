<?php

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

it('sends mail through the Brevo HTTPS API when MAIL_MAILER is brevo', function () {
    config()->set('services.brevo.key', 'test-brevo-key');

    $transport = Mail::mailer('brevo')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(BrevoApiTransport::class)
        ->and((string) $transport)->toBe('brevo+api://api.brevo.com');
});
