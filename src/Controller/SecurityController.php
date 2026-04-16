<?php

namespace App\Controller;

use App\Form\ChangePasswordType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/connexion', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        RateLimiterFactoryInterface $loginLimiter,
        Request $request
    ): Response {

        $limiter = $loginLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
    }

    /**
     * @throws \Exception
     */
    #[Route(path: '/changer-mon-mot-de-passe/{token}', name: 'app_modify_password')]
    public function modifyPassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): Response {

        $token = $request->attributes->get('token');

        [$payload, $signature] = explode('.', $token);

        $expected = hash_hmac('sha256', $payload, $_ENV['APP_SECRET']);

        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Token invalide');
        }

        $payload = strtr($payload, '-_', '+/');
        $data = json_decode(base64_decode($payload), true);

        if ($data['exp'] < time()) {
            throw new \RuntimeException('Page expirée');
        }

        $email = $data['email'];

        $userToUpdate = $userRepository->findOneBy(['email' => $email]);

        if ($userToUpdate) {
            if (!$userToUpdate->getPasswordResetAt() ||
                $userToUpdate->getPasswordResetAt()->getTimestamp() !== $data['reset']
            ) {
                throw new \RuntimeException('Lien invalide');
            }
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($userToUpdate && $form->isSubmitted() && $form->isValid()) {
            if ($data['exp'] < time()) {
                throw new \RuntimeException('Page expirée');
            }

            $userToUpdate->setPassword(
                $passwordHasher->hashPassword($userToUpdate, $form->get('password')->getData())
            );
            $userToUpdate->setPasswordResetAt(new \DateTimeImmutable());

            $entityManager->flush();

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/modify-password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
