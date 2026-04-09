<?php

namespace App\Controller;

use App\Enum\SessionElements;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $session = $request->getSession();
        $token = $session->get(SessionElements::ORDER_TOKEN->value);
        $shoppingCart = $session->get(SessionElements::SHOPPING_CART->value);
        $sessionId = $session->get(SessionElements::SESSION_KEY->value);

        dd($token, $shoppingCart, $sessionId, $session);

        return $this->render('main/session.html.twig');
    }


}
