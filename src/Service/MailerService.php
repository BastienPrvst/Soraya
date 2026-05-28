<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
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
        private string $adminMail,
        private readonly LoggerInterface $logger
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

        //Mail client

        //TODO Faire le corps des mails

        $email = (new Email())
            ->from('noreply@soraya.com')
            ->to($order->getEmail())
            ->subject('Confirmation de votre commande')
            ->priority(Email::PRIORITY_HIGH)
            ->text('Votre commande à été validée!');

        //Mail Admin

        $adminEmail = (new Email())
            ->from('noreply@soraya.com')
            ->to($this->adminMail)
            ->subject('Nouvelle commande ' . $order->getBetterId())
            ->text('Nouvelle commande pour admin');

        try {
            $this->mailer->send($email);
            $this->mailer->send($adminEmail);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @throws RandomException
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    public function sendResetPasswordEmail(string $userMail, string $type): bool
    {
        if (!filter_var($userMail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userMail]);

        if (!$user) {
            return false;
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

        //Template mail en fonction d'oubli ou de changement volontaire
        //TODO faire les template de mails si differents
        if ($type === 'forget') {
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
        } elseif ($type === 'reset') {
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
        } else {
            return false;
        }


        return true;
    }
}
