<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route("/api/article", name: "articles")]
class ArticleController extends AbstractController
{

    private $articleRepository;
    private $serializerInterface;
    private $userRepository;
    private $entityManager;

    public function __construct(
        ArticleRepository $articleRepository,
        SerializerInterface $serializerInterface,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->articleRepository = $articleRepository;
        $this->serializerInterface = $serializerInterface;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'all_article', methods: ["GET"])]
    public function index(): JsonResponse
    {

        $article = $this->articleRepository->findBy([], ["id" => "DESC"]);

        $jsonArticle = $this->serializerInterface->serialize($article, 'json', ['groups' => 'article:collection']);

        return new JsonResponse($jsonArticle, JsonResponse::HTTP_OK, [], true);
    }

    #[Route('/auth/create', name: 'create_article', methods: ["POST"])]
    #[IsGranted("ROLE_ADMIN")]
    public function store(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            $author = $this->userRepository->findOneBy(['id' => $user->getId()]);
        }

        $data = $request->request->all();
        $uploadedFile = $request->files->get('imageFile');

        $article = new Article();
        $article->setTitle($data['title']);
        $article->setContent($data['content']);
        $article->setUser($author);
        $article->setImageFile($uploadedFile);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $articleData = [
            'id' => $article->getId(),
            'title' => $article->getTitle(),
            'content' => $article->getContent(),
            "thumbnail" => $article->getThumbnail(),
            "slug" => $article->getSlug(),
            'createdAt' => $article->getCreatedAt(),
            'user' => [
                'id' => $article->getUser()->getId(),
                'firstname' => $article->getUser()->getFirstname(),
            ],

        ];

        return new JsonResponse(['article' => $articleData], JsonResponse::HTTP_CREATED);
    }

    #[Route("/{id}", name: 'show-article', methods: ["GET"])]
    public function show($id): JsonResponse
    {

        $showArticle = $this->articleRepository->findBy(['id' => $id]);

        if (!$showArticle) {
            return new JsonResponse([
                "success" => false,
                "message" => "Article not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $response = $this->serializerInterface->serialize([
            "success" => true,
            "message" => "Success data",
            'data' => [
                "article" => $showArticle
            ]
        ], 'json', [
            "groups" => ["show:item:Article"]
        ]);

        return new JsonResponse($response, json: true);
    }

    #[Route('/auth/update/{id}', name: "update_article", methods: ['POST'])]
    #[IsGranted("ROLE_ADMIN")]
    public function update($id, Request $request): JsonResponse
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

        $article = $this->articleRepository->findOneBy(["id" => $id]);

        if (!$article) {
            return new JsonResponse([
                "success" => false,
                "message" => "Article not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($user instanceof User) {
            if ($user->getId() !== $article->getUser()->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }

        $data = $request->request->all();

        if (isset($data['title'])) {
            $article->setTitle($data['title']);
        }

        if (isset($data['content'])) {
            $article->setContent($data['content']);
        }

        $newImageFile = $request->files->get('imageFile');

        if ($newImageFile) {
            $oldImage = $article->getThumbnail();
            if ($oldImage) {
                $article->setImageFile(null);
            }
            $article->setImageFile($newImageFile);
        }

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/auth/delete/{id}', name: "destroy_article", methods: ['DELETE'])]
    #[IsGranted("ROLE_ADMIN")]
    public function destroy($id): JsonResponse
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

        $article = $this->articleRepository->find($id);

        if (!$article) {
            return new JsonResponse([
                "success" => false,
                "message" => "Artice not found"
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($user instanceof User) {
            if ($user->getId() !== $article->getUser()->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not authorized modify ressource.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }
        }

        $this->entityManager->remove($article);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
