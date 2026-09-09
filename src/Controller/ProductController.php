<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{

    public function __construct(
        private readonly ProductRepository $productRepository
    ) {
    }

    #[Route('/produits', name: 'app_products')]
    public function index(Request $request): Response
    {
        $limit = 8;
        $page = $request->query->getInt('page', 1);

        $totalProducts = $this->productRepository->count([]);
        $totalPages = (int) ceil($totalProducts / $limit);

        $allProducts = $this->productRepository->findByPage($page, $limit);

        return $this->render('product/allProducts.html.twig', [
            'products' => $allProducts,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'limit' => $limit
        ]);
    }

    #[Route(path: '/produit/{name}', name: 'app_product_details')]
    public function showProduct(
        #[MapEntity(mapping: ['name' => 'name'])] Product $product,
    ): Response {
        return $this->render('product/productDetails.html.twig', [
            'product' => $product,
        ]);
    }
}
