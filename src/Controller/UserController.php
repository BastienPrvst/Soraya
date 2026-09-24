<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangeUserInformationsType;
use App\Form\ModifyProfileType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    public function __construct()
    {
    }

    #[Route(path: '/mon-profil', name: 'app_profile', methods: ['GET'])]
    public function showProfile(OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ChangeUserInformationsType::class, $user, [
            'action' => $this->generateUrl('app_profile_update'),
        ]);

        return $this->render('user/profil.html.twig', [
            'activeUser' => $user,
            'lastOrders' => $orderRepository->getLastTenOrders($user),
            'infoForm' => $form,
        ]);
    }

    #[Route(path: '/mon-profil/update', name: 'app_profile_update')]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        RateLimiterFactoryInterface $profilLimiter
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $limiter = $profilLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Veuillez patienter pour effectuer un nouveau changement de vos informations.');
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(ChangeUserInformationsType::class, $user);
        $form->handleRequest($request);

        $currentPassword = $form->get('currentPassword')->getData();
        $newPassword = $form->get('newPassword')->getData();

    }
}
