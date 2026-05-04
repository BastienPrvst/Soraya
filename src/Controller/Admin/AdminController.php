<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\MailerService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{

    #[Route(path: '/admin/dashboard', name: 'admin_dashboard')]
    public function showDashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     */
    #[Route(path: '/renvoie-mail/{token}', name: 'admin_confirmation_mail')]
    public function renvoiMail(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        MailerService $mailerService,
        Request $request
    ): Response {
        if ($order->getStatus()?->isAtLeast(OrderStatus::PAID)) {
            $mailerService->sendConfirmationEmail($order);
        }
        return $this->redirect($request->headers->get('referer'));
    }

    #[Route(path: '/admin/imprimer-etiquette/{token}', name: 'admin_delivery_sticker')]
    public function printDelivery(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request
    ): Response {
        //TODO: Faire la logique d'impression etiquette colissimo ou mondial relay
        return $this->redirect($request->headers->get('referer'));
    }
}
