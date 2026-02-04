<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/inscription', name: 'app_register')]
    public function index(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
                $entityManager->persist($user);
                $entityManager->flush();
                return $this->redirectToRoute('app_login');
            } catch (\Exception $e) {
                $this->logger->error(
                    $e->getMessage(),
                    [$e->getCode()]
                );
            }
        }

        return $this->render('user/register.html.twig', [
            'form' => $form,
        ]);
    }
}
