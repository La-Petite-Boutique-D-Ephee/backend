<?php

namespace App\Controller;


use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\UuidV6;

#[Route("/api", name: "users")]
class UserController extends AbstractController
{

    private $serializerInterface;
    private $userRepository;
    private $entityManager;

    public function __construct(
        UserRepository $userRepository,
        SerializerInterface $serializerInterface,
        EntityManagerInterface $entityManager
    ) {
        $this->userRepository = $userRepository;
        $this->serializerInterface = $serializerInterface;
        $this->entityManager = $entityManager;
    }

    #[Route("/list", name: "list_users", methods: ["GET"])]
    #[IsGranted("ROLE_ADMIN")]
    public function index()
    {

        $usersList = $this->userRepository->findAll();

        return new JsonResponse([
            "count" => count($usersList)
        ]);
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

    #[Route('/user/{id}', name: "user_update", methods: ['PUT'], requirements: ['id' => '\d+'])]
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

    #[Route('/user/{id}', name: "user_destroy", methods: ['DELETE'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
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
            $uuid = UuidV6::fromString($id);
            $uuid1 = $user->getId();
            $uuid1->equals($uuid);
            // dd($user->getId(), $uuid,);
            if (!$uuid1->equals($uuid)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized delete ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }


        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
