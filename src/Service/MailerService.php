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
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendOrderConfirmationEmail(Order $order): void
    {
        if (!$order->getEmail()) {
            return;
        }

        //Mail client

        $email = (new TemplatedEmail())
            ->from('noreply@soraya.com')
            ->to($order->getEmail())
            ->subject('Confirmation de votre commande')
            ->priority(Email::PRIORITY_HIGH)
            ->htmlTemplate('mail/customer_confirmation.mjml.twig')
            ->context([
                'data' => [
                    'order' => $order
                ]
            ])
        ;

        //Mail Admin

        $adminMailTarget = $this->getAdminMail();

        $adminEmail = (new TemplatedEmail())
            ->from('noreply@soraya.com')
            ->to($adminMailTarget)
            ->subject('Nouvelle commande ' . $order->getBetterId())
            ->text('Nouvelle commande pour admin');

        $this->mailer->send($email);
        $this->mailer->send($adminEmail);

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

        $contactUrl = $this->urlGenerator->generate('app_contact');

        $email = (new TemplatedEmail())
            ->from('noreply@soraya.com')
            ->to($userMail)
            ->subject('Changement de mot de passe Lévédène')
            ->htmlTemplate('mail/reset_password_email.mjml.twig')
            ->locale('FR')
            ->context([
                'data' => [
                    'url' => $url,
                    'contactUrl' => $contactUrl,
                    'username' => $user->getFirstname(),
                ]
            ]);

        $this->mailer->send($email);

        return true;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendContactMail(mixed $data): void
    {
        $adminMailTarget = $this->getAdminMail();
        $adminEmail = (new TemplatedEmail())
            ->from('no-reply@votredomaine.fr')
            ->replyTo($data['email_address'])
            ->to($adminMailTarget)
            ->subject('Contact Client')
            ->htmlTemplate('mail/contact_email.html.twig')
            ->locale('FR')
            ->context($data);

        $this->mailer->send($adminEmail);
    }

    private function getAdminMail(): string
    {
        $parameterRepository = $this->entityManager->getRepository(Parameter::class);
        $parameter = $parameterRepository->findOneBy([]);
        $adminMailTarget = $parameter ? $parameter->getAdminMail() : null;
        if (empty($adminMailTarget)) {
            $adminMailTarget = 'admin@lévédène.com';
        }

        return $adminMailTarget;
    }
}
