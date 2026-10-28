<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/** The website's email account (Gmail SMTP, config mail.mailers.smtp): checks a password before it is saved. */
class MailAccount
{
    /**
     * Sign in to the mail server with this password, without sending anything.
     *
     * @throws RuntimeException with a message for the admin
     */
    public function check(string $password): void
    {
        $smtp = config('mail.mailers.smtp');
        // Port 465 = TLS from the start; 587 = plain first, then STARTTLS (done by the transport itself)
        $transport = new EsmtpTransport($smtp['host'], (int) $smtp['port'], (int) $smtp['port'] === 465);
        $transport->setUsername((string) $smtp['username']);
        $transport->setPassword($password);

        try {
            $transport->start();
            $transport->stop();
        } catch (Throwable $e) {
            throw new RuntimeException(preg_match('/535|BadCredentials|Username and Password not accepted/i', $e->getMessage())
                ? 'Gmail did not accept this App Password for ' . $smtp['username'] . '. Copy the newest one from myaccount.google.com/apppasswords.'
                : 'Could not reach the mail server (' . \Illuminate\Support\Str::limit($e->getMessage(), 150) . ').');
        }
    }
}
