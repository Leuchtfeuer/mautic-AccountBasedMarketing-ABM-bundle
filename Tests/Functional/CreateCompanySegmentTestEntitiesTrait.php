<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\LeuchtfeuerAccountBasedMarketingAbmIntegration;

trait CreateCompanySegmentTestEntitiesTrait
{
    private function publishAbmIntegration(bool $isPublished = true): void
    {
        $plugin = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerAccountBasedMarketingAbmBundle']);

        if (null === $plugin) {
            $plugin = new Plugin();
            $plugin->setName(LeuchtfeuerAccountBasedMarketingAbmIntegration::DISPLAY_NAME);
            $plugin->setBundle('LeuchtfeuerAccountBasedMarketingAbmBundle');
            $this->em->persist($plugin);
        }

        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LeuchtfeuerAccountBasedMarketingAbmIntegration::NAME])
            ?? new Integration();
        $integration->setName(LeuchtfeuerAccountBasedMarketingAbmIntegration::NAME);
        $integration->setPlugin($plugin);
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);
        $this->em->flush();
    }

    private function createCompanyLead(Company $company, Lead $lead, bool $isPrimary = true): CompanyLead
    {
        $companyLead = new CompanyLead();
        $companyLead->setCompany($company);
        $companyLead->setLead($lead);
        $companyLead->setPrimary($isPrimary);
        $companyLead->setDateAdded(new \DateTime());

        $this->em->persist($companyLead);
        $this->em->flush();

        return $companyLead;
    }

    /**
     * @param array<array<mixed>> $filters
     */
    private function createCompanySegment(string $name, string $alias, bool $isPublished = true, array $filters = []): CompanySegment
    {
        $companySegment = new CompanySegment();
        $companySegment->setName($name);
        $companySegment->setAlias($alias);
        $companySegment->setIsPublished($isPublished);
        if ([] !== $filters) {
            $companySegment->setFilters($filters);
        }

        $this->em->persist($companySegment);
        $this->em->flush();

        return $companySegment;
    }

    private function addCompanyToCompanySegment(Company $company, CompanySegment $companySegment): SegmentCompany
    {
        $segmentCompany = new SegmentCompany();
        $segmentCompany->setCompany($company);
        $segmentCompany->setCompanySegment($companySegment);
        $segmentCompany->setDateAdded(new \DateTime());

        $this->em->persist($segmentCompany);
        $this->em->flush();

        return $segmentCompany;
    }

    private function manuallyRemoveCompanyFromCompanySegment(Company $company, CompanySegment $companySegment): void
    {
        $segmentCompany = $this->em->getRepository(SegmentCompany::class)->findOneBy([
            'company'        => $company,
            'companySegment' => $companySegment,
        ]);
        $segmentCompany->setManuallyRemoved(true);
        $this->em->persist($segmentCompany);
        $this->em->flush();
    }
}
