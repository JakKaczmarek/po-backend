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
        $jobOffer
            ->setCompanyName($data['companyName'])
            ->setTitle($data['title'])
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

    #[Route('/api/job-offers', name: 'job_offer_list', methods: ['GET'])]
    public function listJobOffers(JobOfferRepository $jobOfferRepository): JsonResponse
    {

        $jobOffers = $jobOfferRepository->findAll();

        $data = array_map(function (JobOffer $jobOffer) {
            return [
                'id' => $jobOffer->getId(),
                'companyName' => $jobOffer->getCompanyName(),
                'title' => $jobOffer->getTitle(),
                'location' => $jobOffer->getLocation(),
                'salary' => $jobOffer->getSalary(),
                'createdAt' => $jobOffer->getCreatedAt()->format('Y-m-d H:i:s'),
                'company' => $jobOffer->getCompany()?->getEmail(),
            ];
        }, $jobOffers);

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }

    #[Route('/api/job-offer/{id}', name: 'job_offer_details', methods: ['GET'])]
    public function getJobOffer(JobOffer $jobOffer): JsonResponse
    {
        $data = [
            'id' => $jobOffer->getId(),
            'companyName' => $jobOffer->getCompanyName(),
            'title' => $jobOffer->getTitle(),
            'description' => $jobOffer->getDescription(),
            'location' => $jobOffer->getLocation(),
            'salary' => $jobOffer->getSalary(),
            'createdAt' => $jobOffer->getCreatedAt()->format('Y-m-d H:i:s'),
            'company' => $jobOffer->getCompany()?->getEmail(),
        ];

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }

    #[Route('/api/user/job-offers', name: 'user_job_offers', methods: ['GET'])]
    public function listUserJobOffers(JobOfferRepository $jobOfferRepository): JsonResponse
    {
        // Pobieramy aktualnie zalogowanego użytkownika
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Pobieramy oferty pracy należące do zalogowanego użytkownika
        $jobOffers = $jobOfferRepository->findBy(['company' => $user]);

        // Mapowanie danych na format JSON
        $data = array_map(function (JobOffer $jobOffer) {
            return [
                'id' => $jobOffer->getId(),
                'companyName' => $jobOffer->getCompanyName(),
                'title' => $jobOffer->getTitle(),
                'location' => $jobOffer->getLocation(),
                'salary' => $jobOffer->getSalary(),
                'createdAt' => $jobOffer->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $jobOffers);

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }
}
