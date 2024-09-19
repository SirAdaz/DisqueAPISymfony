<?php

namespace App\Controller;

use App\Entity\Chanteur;
use App\Repository\ChanteurRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

class ChanteurController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des chanteurs.
     *
     * @OA\Response(
     *    response=200,
     *    description="Retourne la liste des chanteurs",
     *    @OA\JsonContent(
     *       type="array",
     *       @OA\Items(ref=@Model(type=Chanteur::class, groups={"getChanteurs"}))
     *    )
     * )
     * @OA\Parameter(
     *    name="page",
     *    in="query",
     *    description="La page que l'on veut récupérer",
     *    @OA\Schema(type="int")
     * )
     * @OA\Parameter(
     *    name="limit",
     *    in="query",
     *    description="Le nombre d'éléments que l'on veut récupérer",
     *    @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Chanteurs")
     * 
     * @param ChanteurRepository $chanteurRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/chanteurs', name: 'chanteurs', methods: ['GET'])]
    public function getChanteurList(ChanteurRepository $chanteurRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $idCache = "getAllChanteurs-" . $page . "-" . $limit;

        $jsonchanteurList = $cache->get($idCache, function (ItemInterface $item) use ($chanteurRepository, $page, $limit, $serializer) {
            $item->tag("chanteursCache");
            $item->expiresAfter(60);
            $bookList = $chanteurRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getChanteurs']);
            $context->setVersion('1.0');
            return $serializer->serialize($bookList, 'json', $context);
        });

        return new JsonResponse($jsonchanteurList, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de récuperer le détail d'un chanteur.
     *
     * @OA\Response(
     *    response=200,
     *    description="Retourne les informations d'un chanteur",
     *    @OA\JsonContent(ref=@Model(type=Chanteur::class, groups={"getChanteurs"}))
     * )
     * @OA\Tag(name="Chanteurs")
     * 
     * @param Chanteur $chanteur
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route('/api/chanteurs/{id}', name: 'detailChanteur', methods: ['GET'])]
    public function getDetailChanteur(Chanteur $chanteur, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getChanteurs"]);
        $context->setVersion($version);
        $jsonDisque = $serializer->serialize($chanteur, 'json', $context);

        return new JsonResponse($jsonDisque, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de supprimer un chanteur.
     *
     * @OA\Response(
     *    response=204,
     *    description="Supprime un chanteur"
     * )
     * @OA\Tag(name="Chanteurs")
     * 
     * @param Chanteur $chanteur
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/chanteurs/{id}', name: 'deleteChanteur', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un chanteur')]
    public function deleteChanteur(Chanteur $chanteur, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["ChanteursCache"]);
        $em->remove($chanteur);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet de créer un nouveau chanteur.
     *
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(ref=@Model(type=Chanteur::class, groups={"createChanteur"}))
     * )
     * @OA\Response(
     *    response=201,
     *    description="Crée un nouveau chanteur",
     *    @OA\JsonContent(ref=@Model(type=Chanteur::class, groups={"getChanteurs"}))
     * )
     * @OA\Tag(name="Chanteurs")
     * 
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/chanteurs', name: "createChanteur", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un chanteur')]
    public function createChanteur(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse
    {
        $chanteur = $serializer->deserialize($request->getContent(), Chanteur::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($chanteur);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($chanteur);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['getChanteurs']);
        $jsonChanteur = $serializer->serialize($chanteur, 'json', $context);
        $location = $urlGenerator->generate('detailChanteur', ['id' => $chanteur->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonChanteur, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de mettre à jour un chanteur.
     *
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(ref=@Model(type=Chanteur::class, groups={"updateChanteur"}))
     * )
     * @OA\Response(
     *    response=204,
     *    description="Met à jour un chanteur"
     * )
     * @OA\Tag(name="Chanteurs")
     * 
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param Chanteur $currentChanteur
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/chanteur/{id}', name: "updateChanteur", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer un chanteur')]
    public function updateChanteur(Request $request, SerializerInterface $serializer, Chanteur $currentChanteur, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $newChanteur = $serializer->deserialize($request->getContent(), Chanteur::class, 'json');
        $currentChanteur->setName($newChanteur->getName());

        // On vérifie les erreurs
        $errors = $validator->validate($currentChanteur);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($currentChanteur);
        $em->flush();

        // On vide le cache
        $cache->invalidateTags(["chanteursCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
