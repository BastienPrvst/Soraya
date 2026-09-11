<?php

namespace App\Controller;

use App\Enum\SessionElements;
use App\Form\ContactFormType;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    #[Route('/', name: 'app_main')]
    public function index(): Response
    {
        return $this->render('main/home.html.twig');
    }

    #[Route(path: '/a_propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('main/about.html.twig');
    }

    #[Route(path: '/session', name: 'app_session')]
    public function getSession(Request $request): Response
    {

        //TODO: A supprimer à un moment ou un autre hein
        $session = $request->getSession();
        $token = $session->get(SessionElements::ORDER_TOKEN->value);
        $shoppingCart = $session->get(SessionElements::SHOPPING_CART->value);
        $sessionId = $session->get(SessionElements::SESSION_KEY->value);

        dd($token, $shoppingCart, $sessionId, $session);

        return $this->render('main/session.html.twig');
    }

    #[Route(path: '/aide', name: 'app_frequent_question')]
    public function frequentQuestion(): Response
    {

        return $this->render('main/faq.html.twig', [
        ]);
    }

    #[Route(path: '/contact', name: 'app_contact')]
    public function contact(
        Request $request,
        MailerService $mailerService
    ): Response {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $mailerService->sendContactMail($form->getData());
        }

        return $this->render('main/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route(path: '/testmail', name: 'app_confirm_mail')]
    public function testMailSend(
        MailerService $mailerService,
        OrderRepository $orderRepository,
    ): Response {
        $order = $orderRepository->findOneBy([]);
        $mailerService->sendOrderConfirmationEmail($order);
        return $this->redirectToRoute('app_main');
    }

    #[Route(path: '/testmail2', name: 'app_register_mail')]
    public function testRegisterMail(
        MailerService $mailerService,
        UserRepository $userRepository,
    ): Response {
        $user = $userRepository->findOneBy([]);
        $mailerService->sendRegisterMail($user);
        return $this->redirectToRoute('app_main');
    }

    #[Route(path: '/testmail3', name: 'app_reset_mail')]
    public function testResetMail(
        MailerService $mailerService,
        UserRepository $userRepository,
    ): Response {
        $user = $userRepository->findOneBy([]);
        $mailerService->sendResetPasswordEmail($user->getEmail());
        return $this->redirectToRoute('app_main');
    }
}
