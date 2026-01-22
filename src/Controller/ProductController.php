<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('/produits/', name: 'app_products')]
    public function index(): Response
    {
        return $this->render('product/index.html.twig', []);
    }

    #[Route(path: '/produit/{slug}', name: 'app_product_details')]
    public function showProduct(string $slug): Response
    {
    }

}
