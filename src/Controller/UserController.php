<?php

namespace App\Controller;

use App\Entity\Token;
use App\Entity\User;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use App\Helpers\Validator;
use App\Services\SendEmail;


#[Route("/api/auth", name: "users")]
class UserController extends AbstractController
{

    private $serializerInterface;
    private $userRepository;
    private $entityManager;
    private $mailerInterface;
    private $tokenRepository;
    private $validatorInterface;
    private $sendEmail;

    public function __construct(
        UserRepository $userRepository,
        SerializerInterface $serializerInterface,
        EntityManagerInterface $entityManager,
        MailerInterface $mailerInterface,
        TokenRepository $tokenRepository,
        Validator $validatorInterface,
        SendEmail $sendEmail
    ) {
        $this->userRepository = $userRepository;
        $this->serializerInterface = $serializerInterface;
        $this->entityManager = $entityManager;
        $this->mailerInterface = $mailerInterface;
        $this->tokenRepository = $tokenRepository;
        $this->validatorInterface = $validatorInterface;
        $this->sendEmail = $sendEmail;
    }

    #[Route("/users", name: "users", methods: ["GET"])]
    #[IsGranted("ROLE_ADMIN")]
    public function index()
    {

        $usersList = $this->userRepository->findAll();

        return new JsonResponse([
            "count" => count($usersList)
        ]);
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



    #[Route("/me", name: "me", methods: ["GET"])]
    #[IsGranted("ROLE_USER")]
    public function show()
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'You are not logged in.',
                'data' => [
                    'user' => null
                ]
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $response = $this->serializerInterface->serialize(
            [
                'success' => true,
                'message' => 'You are logged in.',
                'data' => [
                    'user' => $user
                ]
            ],
            'json',
            [
                'groups' => 'read:item:User'
            ]
        );

        return new JsonResponse($response, JsonResponse::HTTP_OK, json: true);
    }

    #[Route('/user/{id}', name: "updateUser", methods: ['PUT'])]
    #[IsGranted("ROLE_USER")]
    public function update($id, Request $request, User $currentUser): JsonResponse
    {

        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'You are not logged in.',
                'data' => [
                    'user' => null
                ]
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }
        if ($user instanceof User) {
            if ($user->getId() !== intval($id)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }

        $updatedUser = $this->serializerInterface->deserialize(
            $request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser]
        );

        $this->entityManager->persist($updatedUser);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/user/{id}', name: "destroyUser", methods: ['DELETE'])]
    #[IsGranted("ROLE_USER")]
    public function destroy($id, User $user): JsonResponse
    {

        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'You are not logged in.',
                'data' => [
                    'user' => null
                ]
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }
        if ($user instanceof User) {
            if ($user->getId() !== intval($id)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }


        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route(
        '/confirm-email/{token}',
        name: 'confirm-email',
        requirements: ["firstname" => "[a-zA-Z_\-]+", "token" => "(tk_){1}[a-z0-9]+"],
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
