<?php

namespace App\Controller;

use App\Entity\Token;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Helpers\Validator;
use App\Repository\TokenRepository;
use App\Services\SendEmail;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth', name: 'auth_user')]
class AuthController extends AbstractController
{

    private $serializerInterface;
    private $entityManager;
    private $validatorInterface;
    private $sendEmail;
    private $mailerInterface;
    private $tokenRepository;

    public function __construct(
        SerializerInterface $serializerInterface,
        EntityManagerInterface $entityManager,
        MailerInterface $mailerInterface,
        TokenRepository $tokenRepository,
        Validator $validatorInterface,
        SendEmail $sendEmail
    ) {
        $this->serializerInterface = $serializerInterface;
        $this->entityManager = $entityManager;
        $this->mailerInterface = $mailerInterface;
        $this->tokenRepository = $tokenRepository;
        $this->validatorInterface = $validatorInterface;
        $this->sendEmail = $sendEmail;
    }

    #[Route('/signin', name: 'signin', methods: ["POST"])]
    public function store(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {

        try {

            $newUser = $this->serializerInterface->deserialize(
                $this->serializerInterface->serialize(
                    [
                        ...$request->toArray()
                    ],
                    'json'
                ),
                User::class,
                'json'
            );


            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $newUser->getEmail()]);
            if ($existingUser) {
                return new JsonResponse(['error' => 'Compte existant'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $arrayErrors = null;
            $arrayErrors = $this->validatorInterface->validateUser($newUser);

            if ($arrayErrors !== null) {
                return $this->json($arrayErrors);
            }

            $newUser
                ->setPassword(
                    $passwordHasher->hashPassword(
                        $newUser,
                        $newUser->getPassword()
                    )
                )
                ->setStatus(User::STATUS_INACTIVE);

            $token = uniqid("tk_", false);
            $hash = sha1($token);
            $createToken = (new Token())
                ->setToken($hash)
                ->setExp(new \DateTimeImmutable('+10 minutes'))
                ->setUser($newUser);


            $this->sendEmail->send($newUser, $token);

            $this->entityManager->persist($createToken);
            $this->entityManager->persist($newUser);
            $this->entityManager->flush();


            return new JsonResponse([
                "success" => true,
                'message' => 'Your account has been created. Please check your emails to activate it.'
            ], JsonResponse::HTTP_CREATED);
        } catch (\Exception $error) {
            return new JsonResponse([
                "success" => false,
                'message' => $error->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    #[Route(
        '/confirm-email/{token}',
        name: 'confirm-email',
        requirements: ["token" => "(tk_){1}[a-z0-9]+"],
        methods: ['GET']
    )]
    public function confirmEmail(
        string $token,
    ) {
        $tokenId = $this->tokenRepository->findOneBy(["token" => sha1($token)]);

        if (!$tokenId) {
            return $this->json([
                'success' => false
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $tokenId->getUser();

        $user->setStatus(User::STATUS_ACTIVE);
        //recuperer status
        $this->entityManager->remove($tokenId);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $email = (new TemplatedEmail())
            ->from('lapetiteboutiquedephee@gmail.com')
            ->to($user->getEmail())
            ->subject('Your account has been activated !')
            ->html(
                "<p>Hello {$user->getFirstname()},</p>"
                    . "<p>
                      Votre compte est bien activé !
                    </p>"
                    . "<p>A plus tard sur La Petite Boutique D'Ephée !</p>"
            );

        $this->mailerInterface->send($email);

        return $this->json([
            'success' => true,
            'message' => 'Your account has been activated !'
        ], Response::HTTP_OK);
    }
}
