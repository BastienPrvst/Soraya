<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class MailerService
{

    public function __construct(
        private MailerInterface       $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager,
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
     * @throws \JsonException
     */
    public function sendResetPasswordEmail(string $userMail): void
    {
        if (!filter_var($userMail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userMail]);

        if (!$user) {
            return;
        }

        $user->setPasswordResetAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $data = [
            'email' => $userMail,
            'exp' => time() + 900,
            'reset' => $user->getPasswordResetAt()?->getTimestamp(),
        ];

        $payload = rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payload, $_ENV['APP_SECRET']);
        $token = $payload . '.' . $signature;

        $url = $this->urlGenerator->generate('app_modify_password', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('noreply@soraya.com')
            ->to($userMail)
            ->subject('Changement de mot de passe Site Soraya')
            ->htmlTemplate('mail/reset_password.html.twig')
            ->locale('FR')
            ->context([
                'data' => [
                    'url' => $url,
                ]
            ]);

        $this->mailer->send($email);
    }
}
