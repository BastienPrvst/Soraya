<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{

    public function __construct(
        private readonly ProductRepository $productRepository
    ) {
    }

    #[Route('/produits', name: 'app_products')]
    public function index(): Response
    {
        $allProducts = $this->productRepository->findAll();

        return $this->render('product/allProducts.html.twig', [
            'products' => $allProducts,
        ]);
    }

    #[Route(path: '/produit/{name}', name: 'app_product_details')]
    public function showProduct(
        #[MapEntity(mapping: ['name' => 'name'])] Product $product
    ): Response {
        return $this->render('product/productDetails.html.twig', [
            'product' => $product,
        ]);
    }
}
