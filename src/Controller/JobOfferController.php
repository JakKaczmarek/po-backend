<?php

namespace App\Controller;

use App\Action\Query\JobOffer\GetAll\GetAll;
use App\Entity\JobOffer;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class JobOfferController extends AbstractController
{
use HandleTrait;
    public function __construct(private MessageBusInterface $messageBus, private SerializerInterface $serializer)
    {
    }

    #[Route('/api/job-offers', name: 'job_offer_create', methods: ['POST'])]
    public function createJobOffer(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['title']) || empty($data['description']) || empty($data['location'])) {
            return new JsonResponse(['error' => 'Missing required fields'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $jobOffer = new JobOffer();
        $jobOffer->setTitle($data['title'])
            ->setDescription($data['description'])
            ->setLocation($data['location'])
            ->setSalary($data['salary'] ?? null)
            ->setCreatedAt(new \DateTime());

        $company = $this->getUser(); // Pobierz aktualnie zalogowaną firmę
        $jobOffer->setCompany($company);

        $entityManager->persist($jobOffer);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Job offer created successfully'], JsonResponse::HTTP_CREATED);
    }

//    #[Route('/api/job-offers', name: 'job_offer_list', methods: ['GET'])]
//    public function listJobOffers(): JsonResponse
//    {
//        return new JsonResponse ($this->serializer->serialize($this->handle(new GetAll()),'json', [AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]), 200, [], true);
//    }


    #[Route('/api/job-offers', name: 'job_offer_list', methods: ['GET'])]
    public function listJobOffers(JobOfferRepository $jobOfferRepository): JsonResponse
    {

        $jobOffers = $jobOfferRepository->findAll();

        $data = array_map(function (JobOffer $jobOffer) {
            return [
                'id' => $jobOffer->getId(),
                'title' => $jobOffer->getTitle(),
                'description' => $jobOffer->getDescription(),
                'location' => $jobOffer->getLocation(),
                'salary' => $jobOffer->getSalary(),
                'createdAt' => $jobOffer->getCreatedAt()->format('Y-m-d H:i:s'),
                'company' => $jobOffer->getCompany()?->getEmail(),
            ];
        }, $jobOffers);

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }
}