<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Form\Admin\PackageType;
use App\Repository\OrderRepository;
use App\Service\MailerService;
use App\Service\WorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
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

    #[Route(path: '/admin/package/{token}', name: 'admin_package_order')]
    public function preparePackage(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
        WorkflowService $workflowService,
        AdminUrlGenerator $adminUrlGenerator,
        OrderRepository $orderRepository,
    ): Response {
        if ($order->getStatus() !== OrderStatus::TO_PREPARE) {
            return $this->redirect($request->headers->get('referer'));
        }

        $form = $this->createForm(PackageType::class, $order);
        $form->handleRequest($request);
        $numberToPrepare = $orderRepository->count(['status' => OrderStatus::TO_PREPARE->value]);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($workflowService->canTransition($order, 'to_pending_shipping')) {
                $workflowService->applyTransition($order, 'to_pending_shipping');
            }

            $nextOrder = $orderRepository->findNextToPrepare($order);

            $url = $adminUrlGenerator
                ->setController(OrderCrudController::class)
                ->setAction('index')
                ->set('filters[status][value][]', 'to_prepare')
                ->set('filters[status][comparison]', '=')
                ->generateUrl();

            $this->addFlash('success', 'La commande ' . $order->getBetterId() . ' est marquée comme emballée.');

            if (!$nextOrder || $form->get('validate')->isClicked()) {
                return $this->redirect($url);
            }

            return $this->redirect(
                $adminUrlGenerator
                    ->setRoute('admin_package_order', ['token' => $nextOrder->getToken()])
                    ->generateUrl()
            );
        }

        return $this->render('admin/package.html.twig', [
            'form' => $form,
            'order' => $order,
            'numberToPrepare' => $numberToPrepare,
        ]);
    }

    #[Route(path: '/admin/package/shipped/{token}', name: 'admin_package_shipped')]
    public function markAsDelivered(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator $adminUrlGenerator,
        Request $request,
    ): Response {
        try {
            $order->setStatus(OrderStatus::SHIPPING);
            $entityManager->flush();
        } catch (\Exception) {
            $this->addFlash('error', 'Une erreur est survenue');
            return $this->redirect($request->headers->get('referer'));
        }

        $this->addFlash('success', 'Commande ' . $order->getBetterId() . ' marquée comme expédiée.');

        return $this->redirect($adminUrlGenerator
            ->setController(OrderCrudController::class)
            ->setAction(Action::INDEX)
            ->setEntityId($order->getId())
            ->generateUrl());
    }

    #[Route(path: '/admin/stock/{product}', name: 'admin_product_stock')]
    public function manageStocks(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator $adminUrlGenerator,
    ): Response {
        $addStock = $request->request->get('add-stock');
        $removeStock = $request->request->get('remove-stock');
        $actualStock = $product->getStock();
        $url = $adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->setAction('index')
            ->generateUrl();

        if ($addStock && is_numeric($addStock)) {
            $actualStock += $addStock;
            $product->setStock($actualStock);
            $entityManager->flush();
            return $this->redirect($url);
        }

        if ($removeStock && is_numeric($removeStock)) {
            if (($actualStock - $removeStock) < 0) {
                $this->addFlash('error', 'Le stock ne peut pas aller en dessous de 0');
                return $this->redirect(
                    $adminUrlGenerator
                        ->setRoute('admin_product_stock', ['product' => $product->getId()])
                        ->generateUrl()
                );
            }

            $product->setStock($actualStock - $removeStock);
            $entityManager->flush();
            return $this->redirect($url);
        }


        return $this->render('admin/stock.html.twig', [
        'product' => $product,
        ]);
    }


//    #[Route('/admin/label/{token}', name: 'admin_print_label')]
//    public function printLabel(
//        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
//    ): Response {
//
//        $pdfContent = $this->colissimoService->generateLabel($order);
//
//
//        $html = sprintf('
//        <!DOCTYPE html>
//        <html lang="fr">
//        <head>
//            <style>
//                body, html { margin: 0; padding: 0; height: 100%%; }
//                iframe { width: 100%%; height: 100%%; border: none; }
//            </style>
//        </head>
//        <body>
//            <iframe id="pdf" src="data:application/pdf;base64,%s"></iframe>
//            <script>
//                document.getElementById("pdf").onload = function() {
//                    this.contentWindow.print();
//                };
//            </script>
//        </body>
//        </html>
//    ', base64_encode($pdfContent));
//
//        return new Response($html, 200, [
//            'Content-Type' => 'text/html',
//        ]);
//    }
}
