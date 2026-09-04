<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ModifyProfileType;
use App\Form\MailToChangePasswordType;
use App\Repository\OrderRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerService $mailerService,
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

    /**
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    #[Route(path: '/mot-de-passe-oublié', name: 'app_password_forgot')]
    public function sendResetPasswordMail(
        Request $request,
        RateLimiterFactoryInterface $mailSenderLimiter
    ) : Response {
        $limiter = $mailSenderLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $form = $this->createForm(MailToChangePasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->mailerService->sendResetPasswordEmail($form->get('email')->getData(), 'forget');
        }

        return $this->render('security/send_reset_password_mail.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    #[Route(path: '/mon-profil/changer-de-mot-de-passe', name: 'app_password_change')]
    public function sendChangePasswordMail(
        Request $request,
        RateLimiterFactoryInterface $mailSenderLimiter
    ) : Response {
        /* @var User $user */
        $user = $this->getUser();
        $limiter = $mailSenderLimiter->create($request->getClientIp() . $user->getId());

        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }        /* @var User $user */
        $user = $this->getUser();
        $this->mailerService->sendResetPasswordEmail($user->getEmail(), 'change');
        $this->addFlash(
            'success',
            'Un mail de changement de mot de passe à bien été envoyé à l`\'adresse mail associée au compte. '
        );
        return $this->redirectToRoute('app_profile_modify');
    }
}
