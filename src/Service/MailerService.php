<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

class MailerService
{

    public function __construct(
        private MailerInterface $mailer,
        private MessageBusInterface $messageBus
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
            ->text('Votre commande à été validée!');
        $this->messageBus->dispatch($email);
    }
}
