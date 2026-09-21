<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model;

use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Contracts\Service\Attribute\Required;

class CompanyModelDecorated extends CompanyModel
{
    private bool $repositorySetUp = false;

    private ?Config $abmConfig = null;

    #[Required]
    public function setAbmConfig(Config $abmConfig): void
    {
        $this->abmConfig = $abmConfig;
    }

    public function getRepository(): CompanyRepository
    {
        $repository = parent::getRepository();

        // Without the registration the core treats "companysegment:x" as plain
        // text search, so an unpublished plugin causes no SQL error.
        if (!$this->repositorySetUp && true === $this->abmConfig?->isPublished()) {
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
