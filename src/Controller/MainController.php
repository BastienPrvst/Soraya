<?php

namespace App\Controller;

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
        $token = $session->get('order_token');
        $shoppingCart = $session->get('shopping_cart');

        dd($token, $shoppingCart);

        return $this->render('main/session.html.twig');
    }


}
