<?php

namespace App\Controller;

use App\Entity\Chanson;
use App\Entity\Disque;
use App\Repository\ChansonRepository;
use App\Repository\ChanteurRepository;
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

class DisqueController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des disques.
     *
     * @OA\Response(
     *    response=200,
     *    description="Retourne la liste des disques",
     *    @OA\JsonContent(
     *       type="array",
     *       @OA\Items(ref=@Model(type=Disque::class, groups={"getChanteurs"}))
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
     * @OA\Tag(name="Disques")
     * 
     * @param DisqueRepository $disqueRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/disques', name: 'disques', methods: ['GET'])]
    public function getDisqueList(DisqueRepository $disqueRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $idCache = "getAllDisques-" . $page . "-" . $limit;
        
        $jsonDisqueList = $cache->get($idCache, function (ItemInterface $item) use ($disqueRepository, $page, $limit, $serializer) {
            $item->tag("disquesCache");
            $item->expiresAfter(60);
            $bookList = $disqueRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getChanteurs']);
            $context->setVersion('1.0');
            return $serializer->serialize($bookList, 'json', $context);
        });

        return new JsonResponse($jsonDisqueList, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de récupérer le détail d'un disque.
     *
     * @OA\Response(
     *    response=200,
     *    description="Retourne les informations d'un disque",
     *    @OA\JsonContent(ref=@Model(type=Disque::class, groups={"getChanteurs"}))
     * )
     * @OA\Tag(name="Disques")
     * 
     * @param Disque $disque
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route('/api/disques/{id}', name: 'detailDisque', methods: ['GET'])]
    public function getDetailDisque(Disque $disque, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getChanteurs"]);
        $context->setVersion($version);
        $jsonBook = $serializer->serialize($disque, 'json', $context);

        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de supprimer un disque.
     *
     * @OA\Response(
     *    response=204,
     *    description="Supprime un disque"
     * )
     * @OA\Tag(name="Disques")
     * 
     * @param Disque $disque
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/disques/{id}', name: 'deleteDisque', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un disque')]
    public function deleteDisque(Disque $disque, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["disquesCache"]);
        $em->remove($disque);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet de créer un nouveau disque.
     *
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(ref=@Model(type=Disque::class, groups={"createDisque"}))
     * )
     * @OA\Response(
     *    response=201,
     *    description="Crée un nouveau disque",
     *    @OA\JsonContent(ref=@Model(type=Disque::class, groups={"getChanteurs"}))
     * )
     * @OA\Tag(name="Disques")
     * 
     * @param Request $request
     * @param ChanteurRepository $chanteurRepository
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/disques', name: "createDisque", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un disque')]
    public function createDisque(Request $request, ChanteurRepository $chanteurRepository, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse
    {
        $disque = $serializer->deserialize($request->getContent(), Disque::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($disque);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idChanteur = $content['idChanteur'] ?? -1;
        $disque->setChanteur($chanteurRepository->find($idChanteur));

        $em->persist($disque);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['getChanteurs']);
        $jsonDisque = $serializer->serialize($disque, 'json', $context);
        $location = $urlGenerator->generate('detailDisque', ['id' => $disque->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonDisque, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de mettre à jour un disque.
     *
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(ref=@Model(type=Disque::class, groups={"updateDisque"}))
     * )
     * @OA\Response(
     *    response=204,
     *    description="Met à jour un disque"
     * )
     * @OA\Tag(name="Disques")
     * 
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param Disque $currentDisque
     * @param EntityManagerInterface $em
     * @param ChanteurRepository $chanteurRepository
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/disques/{id}', name: "updateDisque", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer un disque')]
    public function updateDisque(Request $request, SerializerInterface $serializer, Disque $currentDisque, EntityManagerInterface $em, ChanteurRepository $chanteurRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $newDisque = $serializer->deserialize($request->getContent(), Disque::class, 'json');
        $currentDisque->setNameDisque($newDisque->getNameDisque());

        // On vérifie les erreurs
        $errors = $validator->validate($currentDisque);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idChanteur = $content['idChanteur'] ?? -1;
        $currentDisque->setChanteur($chanteurRepository->find($idChanteur));

        $em->persist($currentDisque);
        $em->flush();

        // On vide le cache
        $cache->invalidateTags(["disquesCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}