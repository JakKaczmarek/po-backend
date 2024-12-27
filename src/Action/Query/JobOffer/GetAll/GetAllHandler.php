<?php

namespace App\Action\Query\JobOffer\GetAll;

use App\Repository\JobOfferRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GetAllHandler
{

    public function __construct(private JobOfferRepository $jobOfferRepository)
    {
    }

    public function __invoke(GetAll $query)
    {
    return $this->jobOfferRepository->findAll();
    }
}
