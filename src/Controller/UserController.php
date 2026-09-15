<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ModifyProfileType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/mon-profil', name: 'app_profile')]
    public function showProfile(
        OrderRepository $orderRepository,
    ) : Response {
        $user = $this->getUser();
        /* @var User $user */
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $lastOrders = $orderRepository->getLastTenOrders($user);
        return $this->render('user/profil.html.twig', [
            'activeUser' => $user,
            'lastOrders' => $lastOrders,
        ]);
    }

    #[Route(path: '/mon-profil/modifier-mon-profil', name: 'app_profile_modify')]
    public function modifyProfile(Request $request) : Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ModifyProfileType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user/modify_profile.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
