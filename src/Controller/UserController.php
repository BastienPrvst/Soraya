<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ModifyProfileType;
use App\Form\MailToChangePasswordType;
use App\Form\UserType;
use App\Repository\OrderRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerService $mailerService,
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

    #[Route(path: '/mon-profil', name: 'app_profile')]
    public function showProfile(
        OrderRepository $orderRepository,
    ) : Response {
        $user = $this->getUser();
        /* @var User $user */
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

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     */
    #[Route(path: '/mot-de-passe-oublié', name: 'app_password_forgot')]
    public function sendResetPasswordMail(Request $request) : Response
    {
        $form = $this->createForm(MailToChangePasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->mailerService->sendResetPasswordEmail($form->get('email')->getData());
        }

        return $this->render('security/send_reset_password_mail.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws \JsonException
     */
    #[Route(path: '/mon-profil/changer-de-mot-de-passe', name: 'app_password_change')]
    public function sendChangePasswordMail() : Response
    {
        /* @var User $user */
        $user = $this->getUser();
        $this->mailerService->sendResetPasswordEmail($user->getEmail());
        $this->addFlash(
            'success',
            'Un mail de changement de mot de passe à bien été envoyé à l`\'adresse mail associée au compte. '
        );
        return $this->redirectToRoute('app_profile_modify');
    }
}
