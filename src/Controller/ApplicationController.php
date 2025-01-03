<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Repository\JobOfferRepository;
use App\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApplicationController extends AbstractController
{
    private string $uploadsDirectory;

    public function __construct(string $uploadsDirectory)
    {
        $this->uploadsDirectory = $uploadsDirectory;
    }

    #[Route('/api/job-offers/{id}/apply', name: 'apply_to_job_offer', methods: ['POST'])]
    public function applyToJobOffer(
        int $id,
        Request $request,
        JobOfferRepository $jobOfferRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $jobOffer = $jobOfferRepository->find($id);

        if (!$jobOffer) {
            return new JsonResponse(['error' => 'Job offer not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Pobieramy plik cvFile z żądania
        $file = $request->files->get('cvFile');
        if (!$file instanceof UploadedFile || $file->getClientOriginalExtension() !== 'pdf') {
            return new JsonResponse(['error' => 'Invalid CV file. Only PDF allowed'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Pobieramy aktualnie zalogowanego użytkownika
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Przenosimy plik do katalogu upload
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->uploadsDirectory);
        $cvFileName = uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($this->uploadsDirectory, $cvFileName);

        // Tworzymy nowy obiekt Application, wypełniając danymi z zalogowanego użytkownika
        $application = new Application();
        $application
            ->setFirstName($user->getFirstName())
            ->setLastName($user->getLastName())
            ->setEmail($user->getEmail())
            ->setPhone($user->getPhone())
            ->setCvFile($cvFileName)
            ->setAppliedAt(new \DateTime())
            ->setJobOffer($jobOffer)
        ;

        // Jeśli w encji Application masz pole user (relacja ManyToOne), możesz też je ustawić:
        // $application->setUser($user);

        $entityManager->persist($application);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Application submitted successfully'], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/job-offers/{id}/applications', name: 'list_applications', methods: ['GET'])]
    public function listApplications(
        int $id,
        JobOfferRepository $jobOfferRepository,
        ApplicationRepository $applicationRepository
    ): JsonResponse {
        $jobOffer = $jobOfferRepository->find($id);

        if (!$jobOffer) {
            return new JsonResponse(['error' => 'Job offer not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $applications = $applicationRepository->findBy(['jobOffer' => $jobOffer]);

        $data = array_map(function (Application $application) {
            return [
                'id' => $application->getId(),
                'firstName' => $application->getFirstName(),
                'lastName' => $application->getLastName(),
                'email' => $application->getEmail(),
                'phone' => $application->getPhone(),
                'cvFile' => '/files/' . $application->getCvFile(),
                'appliedAt' => $application->getAppliedAt()->format('Y-m-d H:i:s'),
            ];
        }, $applications);

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }
}
