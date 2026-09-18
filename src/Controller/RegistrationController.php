<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
                $user->setIsActive(false);
                $entityManager->persist($user);
                $entityManager->flush();
//                $this->addFlash(
//                    'info',
//                    'Un mail avec lien de confirmation vous a été envoyé pour activer votre compte.'
//                );

                $this->sendVerificationEmail($user);

                $user->setVerificationEmailSentAt(new \DateTimeImmutable());
                $entityManager->flush();

                $request->getSession()->set('pending_verification_user_id', $user->getId());

                return $this->redirectToRoute('app_verify_send');

            } catch (\Exception|TransportExceptionInterface $e) {
                $this->logger->error($e->getMessage(), [$e->getCode()]);
                return $this->redirectToRoute('app_register');
            }
        }

        return $this->render('user/register.html.twig', [
            'form' => $form,
        ]);
    }


    /**
     * @throws TransportExceptionInterface
     */
    #[Route(path: '/verify/send', name: 'app_verify_send')]
    public function resendVerifyEmail(Request $request, EntityManagerInterface $em): Response
    {

        $userId = $request->getSession()->get('pending_verification_user_id');

        if (!$userId) {
            throw $this->createNotFoundException('Aucune vérification en attente.');
        }

        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        if ($user->isActive()) {
            $request->getSession()->remove('pending_verification_user_id');
            return $this->redirectToRoute('app_login');
        }

        $lastSent = $user->getVerificationEmailSentAt();
        $cooldown = 60;

        if ($lastSent !== null) {
            $secondsSinceLastSent = time() - $lastSent->getTimestamp();

            if ($secondsSinceLastSent < $cooldown) {

                return $this->render('security/resend_verify_password.html.twig', [
                    'verification_email_sent_at' => $lastSent,
                ]);
            }
        }

        $this->sendVerificationEmail($user);

        $user->setVerificationEmailSentAt(new \DateTimeImmutable());
        $em->flush();

        return $this->render('security/resend_verify_password.html.twig', [
            'verification_email_sent_at' => $user->getVerificationEmailSentAt(),
        ]);
    }

    #[Route('/verify/verified', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        UserRepository $userRepository
    ): Response {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Votre adresse mail a bien été vérifiée.');

        return $this->redirectToRoute('app_login');
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendVerificationEmail(User $user): void
    {
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address('noreply@soraya.fr', 'Levedène'))
                ->to((string) $user->getEmail())
                ->context(['user' => $user])
                ->subject('Confirmer votre mail - Levedène')
                ->htmlTemplate('mail/register_email.mjml.twig')
        );
    }
}
