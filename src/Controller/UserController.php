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

    #[Route(path: '/mon-profil/update', name: 'app_profile_update', methods: ['POST'])]
    public function updateProfile(
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

        $limiter = $profilLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Veuillez patienter pour effectuer un nouveau changement de vos informations.');
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(ChangeUserInformationsType::class, $user, [
            'action' => $this->generateUrl('app_profile_update'),
        ]);
        $form->handleRequest($request);

        $currentPassword = $form->get('currentPassword')->getData();
        $newPassword = $form->get('newPassword')->getData();
        $confirmation = $form->get('newPasswordConfirmation')->getData();

        if ($newPassword && !$currentPassword) {
            $form->get('currentPassword')->addError(
                new FormError('Le mot de passe actuel doit être indiqué.')
            );
        }

        if ($newPassword && $form->isSubmitted()) {
            if (!$hasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(
                    new FormError('Le mot de passe actuel est incorrect.')
                );
            }

            if ($newPassword !== $confirmation) {
                $form->get('newPasswordConfirmation')->addError(
                    new FormError('Les mots de passe ne correspondent pas.')
                );
            }
        }

        $addressForm = $form->get('address');
        $fields = ['street1', 'street2', 'zipcode', 'city', 'country'];

        $isAddressTouched = false;
        foreach ($fields as $field) {
            if (trim((string) $addressForm->get($field)->getData()) !== '') {
                $isAddressTouched = true;
                break;
            }
        }

        if (!$isAddressTouched) {
            $user->setAddress(null);
        } else {
            $violations = $validator->validate($user->getAddress());
            foreach ($violations as $violation) {
                $path = $violation->getPropertyPath();
                $target = $addressForm->has($path) ? $addressForm->get($path) : $addressForm;
                $target->addError(new FormError($violation->getMessage()));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($newPassword) {
                $user->setPassword($hasher->hashPassword($user, $newPassword));
            }
            $em->flush();

            $this->addFlash('success', 'Vos informations ont été mises à jour.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user/profil.html.twig', [
            'activeUser' => $user,
            'lastOrders' => $orderRepository->getLastTenOrders($user),
            'infoForm' => $form,
        ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
