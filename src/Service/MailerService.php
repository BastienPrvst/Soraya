<?php

namespace App\Service;

use App\Entity\Order;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MailerService
{

    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     */
    public function sendConfirmationEmail(Order $order): void
    {
        if (!$order->getEmail()) {
            return;
        }

        $email = (new Email())
            ->from('noreply@soraya.com')
            ->to($order->getEmail())
            ->subject('Confirmation de votre commande')
            ->priority(Email::PRIORITY_HIGH)
            ->text('Votre commande à été validée!');

        $this->mailer->send($email);
    }

    /**
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    public function sendResetPasswordEmail(string $userMail): void
    {
        if (!filter_var($userMail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expires = time() + 1800;
        $signer = new UriSigner($_ENV['APP_SECRET']);

        $url = $this->urlGenerator->generate('app_modify_password', [
            'token' => $token,
            'expires' => $expires,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $signedUrl = $signer->sign($url);

        $email = (new TemplatedEmail())
            ->from('noreply@soraya.com')
            ->to($userMail)
            ->subject('Changement de mot de passe Site Soraya')
            ->htmlTemplate('mail/reset_password.html.twig')
            ->locale('FR')
            ->context([
                'data' => [
                    'token' => $token,
                    'expires' => $expires,
                ]
            ]);

        $this->mailer->send($email);
    }
}
