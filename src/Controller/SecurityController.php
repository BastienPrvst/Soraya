<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
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
    #[Route(path: '/changer-mot-de-passe/{token}/{expires}', name: 'app_modify_password')]
    public function modifyPassword(Request $request): Response
    {
        $signer = new UriSigner($_ENV['APP_SECRET']);

        if (!$signer->check($request->getUri())) {
            throw new \RuntimeException('Lien invalide');
        }

        $expires = $request->query->get('expires');

        if ($expires < time()) {
            throw new \RuntimeException('Lien expiré');
        }
    }

}
