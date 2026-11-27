<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\UserEntityTrait;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Import;
use Mautic\LeadBundle\Helper\Progress;
use Mautic\LeadBundle\Model\ImportModel;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class CompanyImportSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;
    use UserEntityTrait;

    private const SEGMENT_SELECT = 'select[name="lead_field_import[company_segments][]"]';

    private string $csvFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishAbmIntegration();
    }

    protected function beforeTearDown(): void
    {
        if (isset($this->csvFile) && file_exists($this->csvFile)) {
            unlink($this->csvFile);
        }
    }

    public function testImportAddsCompaniesToSelectedSegments(): void
    {
        $selected = $this->createCompanySegment('selected', 'selected');
        $this->createCompanySegment('not-selected', 'not-selected');

        $form = $this->openMappingForm()->selectButton('Import in background')->form();
        $form->setValues(['lead_field_import[company_segments]' => [(string) $selected->getId()]]);
        $this->client->submit($form);
        $this->assertResponseIsSuccessful();

        $import = $this->em->getRepository(Import::class)->findOneBy(['object' => 'company']);
        $this->assertInstanceOf(Import::class, $import);
        $this->assertSame([(int) $selected->getId()], $import->getDefault('company_segments'));

        $importModel = self::getContainer()->get(ImportModel::class);
        $this->assertInstanceOf(ImportModel::class, $importModel);
        $importModel->process($import, new Progress());
        $import->removeFile();

        $companies = $this->em->getRepository(Company::class)->findBy(['name' => ['Acme', 'Globex']]);
        $this->assertCount(2, $companies);

        $this->em->clear();
        foreach ($companies as $company) {
            $segmentCompanies = $this->em->getRepository(SegmentCompany::class)->findBy(['company' => $company->getId()]);
            $this->assertCount(1, $segmentCompanies);
            $this->assertSame($selected->getId(), $segmentCompanies[0]->getCompanySegment()->getId());
            $this->assertTrue($segmentCompanies[0]->isManuallyAdded());
        }
    }

    public function testMappingFormOffersOnlyEditableSegments(): void
    {
        $user = $this->createUserWithSegmentEditOwnPermission();
        $this->createCompanySegment('foreign', 'foreign');
        $own = $this->createCompanySegment('own', 'own');
        $own->setCreatedBy($user);
        $this->em->persist($own);
        $this->em->flush();

        $this->loginOtherUser($user);

        $options = $this->openMappingForm()->filter(self::SEGMENT_SELECT.' option');
        $this->assertSame([(string) $own->getId()], $options->extract(['value']));
    }

    public function testForeignSegmentIsRejected(): void
    {
        $user    = $this->createUserWithSegmentEditOwnPermission();
        $foreign = $this->createCompanySegment('foreign', 'foreign');

        $this->loginOtherUser($user);

        $form   = $this->openMappingForm()->selectButton('Import in background')->form();
        $values = $form->getPhpValues();
        $this->assertIsArray($values['lead_field_import']);
        $values['lead_field_import']['company_segments'] = [(string) $foreign->getId()];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseIsSuccessful();

        $this->assertNotInstanceOf(Import::class, $this->em->getRepository(Import::class)->findOneBy(['object' => 'company']));
    }

    public function testSegmentFieldIsHiddenWhenIntegrationIsUnpublished(): void
    {
        $this->createCompanySegment('segment', 'segment');
        $this->publishAbmIntegration(false);

        $this->assertCount(0, $this->openMappingForm()->filter(self::SEGMENT_SELECT));
    }

    private function openMappingForm(): Crawler
    {
        $this->csvFile = tempnam(sys_get_temp_dir(), 'abm_import').'.csv';
        file_put_contents($this->csvFile, "companyname\nAcme\nGlobex\n");

        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies/import/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Upload')->form();
        $form->setValues([
            'lead_import[file]'       => $this->csvFile,
            'lead_import[batchlimit]' => 100,
            'lead_import[delimiter]'  => ',',
            'lead_import[enclosure]'  => '"',
            'lead_import[escape]'     => '\\',
        ]);
        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[name="lead_field_import"]'));

        return $crawler;
    }

    private function createUserWithSegmentEditOwnPermission(): User
    {
        $user = $this->createUserWithPermission([
            'email'      => 'own-lists@test.com',
            'user-name'  => 'own-lists',
            'first-name' => 'Own',
            'last-name'  => 'Lists',
            'role'       => ['name' => 'Own lists', 'permissions' => [
                'lead:imports' => 1024,
                'lead:leads'   => 1024,
                'lead:lists'   => 2 | 4 | 8,
            ]],
        ]);
        $this->em->flush();

        return $user;
    }
}
