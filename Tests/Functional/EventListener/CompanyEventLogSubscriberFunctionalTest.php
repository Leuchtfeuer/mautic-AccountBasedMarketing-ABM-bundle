<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Import;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Helper\Progress;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\ImportModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanyEventLog;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;

final class CompanyEventLogSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    public function testSegmentChangesAreLogged(): void
    {
        $this->publishAbmIntegration();
        $company = $this->createCompany('Acme');
        $segment = $this->createCompanySegment('Key Accounts', 'key-accounts');

        $companySegmentModel = self::getContainer()->get(CompanySegmentModel::class);
        $this->assertInstanceOf(CompanySegmentModel::class, $companySegmentModel);
        $companySegmentModel->addCompany($company, [$segment], true);
        $companySegmentModel->removeCompany($company, [$segment], true);

        $logs = $this->getLogs($company);
        $this->assertSame([['company_segment', 'added'], ['company_segment', 'removed']], $this->getObjectActions($logs));
        $this->assertSame($segment->getId(), $logs[0]->getObjectId());
        $this->assertSame('Key Accounts', $logs[0]->getProperties()['company_segment_name'] ?? null);
    }

    public function testContactChangesAreLogged(): void
    {
        $this->publishAbmIntegration();
        $company = $this->createCompany('Acme');
        $lead    = new Lead();
        $lead->setFirstname('John');
        $lead->setLastname('Doe');
        $this->em->persist($lead);
        $this->em->flush();

        $companyModel = self::getContainer()->get(CompanyModel::class);
        $this->assertInstanceOf(CompanyModel::class, $companyModel);
        $companyModel->addLeadToCompany($company, $lead);
        $companyModel->removeLeadFromCompany($company, $lead);

        $logs = $this->getLogs($company);
        $this->assertSame([['lead', 'added'], ['lead', 'removed']], $this->getObjectActions($logs));
        $this->assertSame($lead->getId(), $logs[0]->getObjectId());
        $this->assertSame('John Doe', $logs[0]->getProperties()['object_description'] ?? null);
    }

    public function testCompanyImportLogsCreateAndUpdate(): void
    {
        $this->publishAbmIntegration();
        $existing = $this->createCompany('Existing Co');

        $import = $this->runCompanyImport(['New Co', 'Existing Co']);

        $created = $this->em->getRepository(Company::class)->findOneBy(['name' => 'New Co']);
        $this->assertInstanceOf(Company::class, $created);

        $createdLogs = $this->getLogs($created);
        $this->assertSame([['import', 'create']], $this->getObjectActions($createdLogs));
        $this->assertSame($import->getId(), $createdLogs[0]->getObjectId());
        $this->assertSame('companies.csv', $createdLogs[0]->getProperties()['file'] ?? null);

        $this->assertSame([['import', 'update']], $this->getObjectActions($this->getLogs($existing)));
    }

    public function testNothingIsLoggedWhenIntegrationIsUnpublished(): void
    {
        $this->publishAbmIntegration(false);
        $company = $this->createCompany('Acme');
        $segment = $this->createCompanySegment('Key Accounts', 'key-accounts');

        $companySegmentModel = self::getContainer()->get(CompanySegmentModel::class);
        $this->assertInstanceOf(CompanySegmentModel::class, $companySegmentModel);
        $companySegmentModel->addCompany($company, [$segment], true);

        $this->assertSame([], $this->getLogs($company));
    }

    private function createCompany(string $name): Company
    {
        $company = new Company();
        $company->setName($name);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    /**
     * @param string[] $companyNames
     */
    private function runCompanyImport(array $companyNames): Import
    {
        $filePath = tempnam(sys_get_temp_dir(), 'abm_import');
        \assert(is_string($filePath));
        file_put_contents($filePath, implode("\n", array_merge(['companyname'], $companyNames))."\n");

        $import = new Import();
        $import->setObject('company')
            ->setFilePath($filePath)
            ->setOriginalFile('companies.csv')
            ->setHeaders(['companyname'])
            ->setMatchedFields(['companyname' => 'companyname'])
            ->setParserConfig(['batchlimit' => 100, 'delimiter' => ',', 'enclosure' => '"', 'escape' => '\\'])
            ->setLineCount(count($companyNames) + 1)
            ->setStatus(Import::IN_PROGRESS);
        $this->em->persist($import);
        $this->em->flush();

        $importModel = self::getContainer()->get(ImportModel::class);
        $this->assertInstanceOf(ImportModel::class, $importModel);
        $importModel->process($import, new Progress());

        unlink($filePath);

        return $import;
    }

    /**
     * @return CompanyEventLog[]
     */
    private function getLogs(Company $company): array
    {
        return $this->em->getRepository(CompanyEventLog::class)->findBy(['company' => $company], ['id' => 'ASC']);
    }

    /**
     * @param CompanyEventLog[] $logs
     *
     * @return array<array{0: ?string, 1: ?string}>
     */
    private function getObjectActions(array $logs): array
    {
        return array_map(static fn (CompanyEventLog $log): array => [$log->getObject(), $log->getAction()], $logs);
    }
}
