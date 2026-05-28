<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class APIController extends AbstractController
{
    #[Route('/api/stock/{product}', name: 'api_product_stock', methods: ['GET'])]
    public function getStock(Product $product): JsonResponse
    {
        return new JsonResponse(
            [
                'stock'     => $product->getStock(),
                'available' => $product->getStock() > 0,
            ],
            Response::HTTP_OK
        );
    }
}
