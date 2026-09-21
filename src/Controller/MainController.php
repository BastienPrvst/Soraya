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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    #[Route('/', name: 'app_main')]
    public function index(): Response
    {
        return $this->render('main/home.html.twig');
    }

    #[Route(path: '/aide', name: 'app_frequent_question')]
    public function frequentQuestion(): Response
    {

        return $this->render('main/faq.html.twig', [
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route(path: '/contact', name: 'app_contact')]
    public function contact(
        Request $request,
        MailerService $mailerService
    ): Response {
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mailerService->sendContactMail($form->getData());
            $this->addFlash('info', 'Votre demande de contact à bien été envoyée.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('main/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
