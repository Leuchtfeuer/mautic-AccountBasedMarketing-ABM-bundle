<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\Migrations;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanyEventLog;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanyEventLogRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Migrations\Version_7_1_0_1;

final class Version_7_1_0_1Test extends MauticMysqlTestCase
{
    public function testLegacyContactRowsGetTheContactName(): void
    {
        $company = new Company();
        $company->setName('Acme');
        $lead = new Lead();
        $lead->setFirstname('John');
        $lead->setLastname('Doe');
        $this->em->persist($company);
        $this->em->persist($lead);
        $this->em->flush();

        $repository = self::getContainer()->get(CompanyEventLogRepository::class);
        $repository->insertLog((int) $company->getId(), 'lead', 'added', $lead->getId(), ['object_description' => 'Acme'], null, 'System', new \DateTime());
        $repository->insertLog((int) $company->getId(), 'lead', 'removed', 999999, [], null, 'System', new \DateTime());

        $migration = new Version_7_1_0_1($this->em, MAUTIC_TABLE_PREFIX);
        $this->assertTrue($migration->shouldExecute());
        $migration->execute();
        $this->assertFalse($migration->shouldExecute());

        $this->em->clear();
        $names = array_map(
            static fn (CompanyEventLog $log): mixed => $log->getProperties()['contact_name'] ?? null,
            $this->em->getRepository(CompanyEventLog::class)->findBy([], ['id' => 'ASC'])
        );
        $this->assertSame(['John Doe', ''], $names);
    }
}
