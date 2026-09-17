<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model;

use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Model\CompanyModel;

class CompanyModelDecorated extends CompanyModel
{
    private bool $repositorySetUp = false;

    public function getRepository(): CompanyRepository
    {
        $repository = parent::getRepository();

        if (!$this->repositorySetUp) {
            $this->repositorySetUp   = true;
            $defaultCommands         = $repository->getStandardSearchCommands();
            $availableSearchCommands = $repository->getSearchCommands();
            $companyCommands         = array_diff($availableSearchCommands, $defaultCommands);
            $companyCommands[]       = $this->translator->trans(CompanySegmentModel::SEARCH_COMMAND);

            $repository->setAvailableSearchFields($companyCommands);
        }

        return $repository;
    }
}
