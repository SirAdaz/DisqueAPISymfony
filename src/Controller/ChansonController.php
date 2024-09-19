<?php

namespace App\Controller;

use App\Entity\Chanson;
use App\Repository\ChansonRepository;
use App\Repository\DisqueRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
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

class ChansonController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des chansons.
     *
     * @OA\Response(
     *    response=200,
     *    description="Retourne la liste des chansons",
     *    @OA\JsonContent(
     *       type="array",
     *       @OA\Items(ref=@Model(type=Chanson::class, groups={"getChanteurs"}))
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
     * @OA\Tag(name="Chansons")
     * 
     * @param ChansonRepository $chansonRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/chansons', name: 'chansons', methods: ['GET'])]
    public function getChansonList(ChansonRepository $chansonRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $jsonchansonList = $cache->get($idCache, function (ItemInterface $item) use ($chansonRepository, $page, $limit, $serializer) {
            $item->tag("chansonsCache");
            $item->expiresAfter(60);
            $bookList = $chansonRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getChanteurs']);
            $context->setVersion('1.0');
            return $serializer->serialize($bookList, 'json', $context);
        });

        return new JsonResponse($jsonchansonList, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de récuperer le détail d'une chanson.
     *
     * @OA\Response(
     *    response=200,
     *    description="Retourne les informations d'une chanson",
     *    @OA\JsonContent(ref=@Model(type=Chanson::class, groups={"getChanteurs"}))
     * )
     * @OA\Tag(name="Chansons")
     * 
     * @param Chanson $chanson
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route('/api/chansons/{id}', name: 'detailChanson', methods: ['GET'])]
    public function getDetailChanteur(Chanson $chanson, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getChanteurs"]);
        $context->setVersion($version);
        $jsonChanson = $serializer->serialize($chanson, 'json', $context);

        return new JsonResponse($jsonChanson, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de supprimer une chanson.
     *
     * @OA\Response(
     *    response=204,
     *    description="Supprime une chanson"
     * )
     * @OA\Tag(name="Chansons")
     * 
     * @param Chanson $chanson
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/chansons/{id}', name: 'deleteChanson', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer une chanson')]
    public function deleteChansons(Chanson $chanson, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["ChansonsCache"]);
        $em->remove($chanson);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet de créer une nouvelle chanson.
     *
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(ref=@Model(type=Chanson::class, groups={"createChanson"}))
     * )
     * @OA\Response(
     *    response=201,
     *    description="Crée une nouvelle chanson",
     *    @OA\JsonContent(ref=@Model(type=Chanson::class, groups={"getChanteurs"}))
     * )
     * @OA\Tag(name="Chansons")
     * 
     * @param Request $request
     * @param DisqueRepository $disqueRepository
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/chansons', name: "createChanson", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer une chanson')]
    public function createChanson(Request $request, DisqueRepository $disqueRepository, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse
    {
        $chanson = $serializer->deserialize($request->getContent(), Chanson::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($chanson);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idDisque = $content['idDisque'] ?? -1;
        $chanson->setDisque($disqueRepository->find($idDisque));

        $em->persist($chanson);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['getChanteurs']);
        $jsonChanson = $serializer->serialize($chanson, 'json', $context);
        $location = $urlGenerator->generate('detailChanson', ['id' => $chanson->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonChanson, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de mettre à jour une chanson.
     *
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(ref=@Model(type=Chanson::class, groups={"updateChanson"}))
     * )
     * @OA\Response(
     *    response=204,
     *    description="Met à jour une chanson"
     * )
     * @OA\Tag(name="Chansons")
     * 
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param Chanson $currentChanson
     * @param EntityManagerInterface $em
     * @param DisqueRepository $disqueRepository
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/chansons/{id}', name: "updateChanson", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer une chanson')]
    public function updateChanson(Request $request, SerializerInterface $serializer, Chanson $currentChanson, EntityManagerInterface $em, DisqueRepository $disqueRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $newChanson = $serializer->deserialize($request->getContent(), Chanson::class, 'json');
        $currentChanson->setNameChanson($newChanson->getNameChanson());

        // On vérifie les erreurs
        $errors = $validator->validate($currentChanson);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idDisque = $content['idDisque'] ?? -1;
        $currentChanson->setDisque($disqueRepository->find($idDisque));

        $em->persist($currentChanson);
        $em->flush();

        // On vide le cache
        $cache->invalidateTags(["chansonsCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}