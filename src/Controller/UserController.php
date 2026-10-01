<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangeUserInformationsType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserController extends AbstractController
{
    public function __construct()
    {
    }

    #[Route(path: '/mon-profil', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        RateLimiterFactoryInterface $profilLimiter,
        OrderRepository $orderRepository,
        ValidatorInterface $validator,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ChangeUserInformationsType::class, $user);
        $form->handleRequest($request);

        $status = Response::HTTP_OK;

        if ($form->isSubmitted()) {
            $limiter = $profilLimiter->create($request->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Veuillez patienter pour effectuer un nouveau changement de vos informations.');
                return $this->redirectToRoute('app_profile');
            }

            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();
            $confirmation = $form->get('newPasswordConfirmation')->getData();

            if (!$currentPassword && $newPassword) {
                $form->get('currentPassword')->addError(new FormError('Le mot de passe actuel doit être indiqué.'));
            }

            if (!$newPassword && $currentPassword) {
                $form->get('newPassword')->addError(new FormError('Veuillez renseigner un nouveau mot de passe.'));
            }

            if ($currentPassword && $newPassword) {
                if (!$hasher->isPasswordValid($user, $currentPassword)) {
                    $form->get('currentPassword')->addError(new FormError('Le mot de passe actuel est incorrect.'));
                }

                if ($newPassword !== $confirmation) {
                    $form->get('newPasswordConfirmation')->addError(new FormError(
                        'Les mots de passe ne correspondent pas.'
                    ));
                }
            }

            $addressForm = $form->get('address');
            $isAddressTouched = false;
            foreach (['street1', 'street2', 'zipcode', 'city', 'country'] as $field) {
                if (trim((string) $addressForm->get($field)->getData()) !== '') {
                    $isAddressTouched = true;
                    break;
                }
            }

            $address = $user->getAddress();
            if ($isAddressTouched && $address) {
                foreach ($validator->validate($address) as $violation) {
                    $path = $violation->getPropertyPath();
                    $target = $addressForm->has($path) ? $addressForm->get($path) : $addressForm;
                    $target->addError(new FormError($violation->getMessage()));
                }
            }

            if ($form->isValid()) {
                if ($isAddressTouched) {
                    $address?->setIsActive(true);
                } elseif ($address) {
                    $user->setAddress(null);
                    if ($address->getId()) {
                        $em->remove($address);
                    }
                }

                if ($newPassword) {
                    $user->setPassword($hasher->hashPassword($user, $newPassword));
                }

                $em->flush();

                $this->addFlash('success', 'Vos informations ont été mises à jour.');
                return $this->redirectToRoute('app_profile');
            }

            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('user/profil.html.twig', [
            'activeUser' => $user,
            'lastOrders' => $orderRepository->getLastTenOrders($user),
            'infoForm' => $form,
        ], new Response('', $status));
    }
}
