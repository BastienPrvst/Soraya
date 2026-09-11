<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Parameter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class MailerService
{

    public function __construct(
        private MailerInterface       $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     */
    public function sendOrderConfirmationEmail(Order $order): void
    {
        if (!$order->getEmail()) {
            return;
        }

        //Mail client

        //TODO Faire le corps des mails

        $email = (new TemplatedEmail())
            ->from('noreply@soraya.com')
            ->to($order->getEmail())
            ->subject('Confirmation de votre commande')
            ->priority(Email::PRIORITY_HIGH)
            ->htmlTemplate('email/customer_confirmation.mjml.twig')
            ->context([
                'data' => [
                    'order' => $order
                ]
            ])
        ;

        //Mail Admin

        $adminMailTarget = $this->getAdminMail();

        $adminEmail = (new Email())
            ->from('noreply@soraya.com')
            ->to($adminMailTarget)
            ->subject('Nouvelle commande ' . $order->getBetterId())
            ->text('Nouvelle commande pour admin');

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur mail client: ' . $e->getMessage());
        }

        if (($_ENV['APP_ENV']) === 'dev') {
            usleep(10000000);
        }

        try {
            $this->mailer->send($adminEmail);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur mail admin: ' . $e->getMessage());
        }
    }

    /**
     * @throws TransportExceptionInterface|\JsonException
     */
    public function sendResetPasswordEmail(
        string $userMail,
    ): bool {

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

        $email = (new TemplatedEmail())
            ->from('noreply@soraya.com')
            ->to($userMail)
            ->subject('Changement de mot de passe Lévédène')
            ->htmlTemplate('mail/reset_password.html.twig')
            ->locale('FR')
            ->context([
                'data' => [
                    'url' => $url,
                ]
            ]);

        $this->mailer->send($email);

        return true;
    }

    public function sendContactMail(mixed $data): void
    {
        $adminMailTarget = $this->getAdminMail();

        $adminEmail = (new TemplatedEmail())
            ->from($data['email_address'])
            ->to($adminMailTarget)
            ->subject('Contact Client')
            ->htmlTemplate('contact_email.html.twig')
            ->locale('FR')
            ->context($data);

        try {
            $this->mailer->send($adminEmail);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur mail client: ' . $e->getMessage());
        }
    }
    public function sendRegisterMail(User $user): void
    {
        $url = $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $mail = (new TemplatedEmail())
            ->from('noreply@levedene.com')
            ->to($user->getEmail())
            ->subject('Bienvenue chez Lévédène')
            ->htmlTemplate('mail/register.mjml.twig')
            ->locale('FR')
            ->context([
                'data' => [
                    'user' => $user,
                    'url' => $url,
                ]
            ]);

        try {
            $this->mailer->send($mail);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur mail client: ' . $e->getMessage());
        }
    }

    private function getAdminMail(): string
    {
        $parameterRepository = $this->entityManager->getRepository(Parameter::class);
        $parameter = $parameterRepository->findOneBy([]);
        $adminMailTarget = $parameter ? $parameter->getAdminMail() : null;
        if (empty($adminMailTarget)) {
            $adminMailTarget = 'admin@soraya.com';
        }

        return $adminMailTarget;
    }
}
