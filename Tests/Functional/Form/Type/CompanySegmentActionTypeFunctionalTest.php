<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\Form\Type;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\UserEntityTrait;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener\CampaignSubscriber;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class CompanySegmentActionTypeFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;
    use UserEntityTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishAbmIntegration();
    }

    public function testUserWithoutSegmentEditPermissionKeepsForeignSegmentWhenSavingAction(): void
    {
        $user = $this->createUserWithPermission([
            'email'      => 'own-lists@test.com',
            'user-name'  => 'own-lists',
            'first-name' => 'Own',
            'last-name'  => 'Lists',
            'role'       => ['name' => 'Own lists', 'permissions' => [
                'campaign:campaigns' => 1024,
                'lead:leads'         => 1024,
                'lead:lists'         => 2 | 4 | 8,
            ]],
        ]);
        $this->em->flush();

        $foreign = $this->createCompanySegment('foreign', 'foreign');
        $own     = $this->createCompanySegment('own', 'own');
        $own->setCreatedBy($user);
        $this->em->persist($own);

        $campaign = new Campaign();
        $campaign->setName('Campaign');
        $this->em->persist($campaign);

        $event = new Event();
        $event->setName('Modify company segments');
        $event->setType(CampaignSubscriber::ACTION_CHANGE_COMPANY_SEGMENTS);
        $event->setEventType('action');
        $event->setProperties(['addToLists' => [(string) $foreign->getId()], 'removeFromLists' => []]);
        $event->setOrder(1);
        $event->setCampaign($campaign);
        $this->em->persist($event);
        $this->em->flush();

        $this->loginOtherUser($user);

        $uri = '/s/campaigns/events/edit/'.$event->getId().'?campaignId='.$campaign->getId();
        $this->client->xmlHttpRequest(Request::METHOD_GET, $uri);
        $this->assertResponseIsSuccessful();

        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertIsString($response['newContent']);
        $crawler = new Crawler($response['newContent'], $this->client->getInternalRequest()->getUri());

        $foreignOption = $crawler->filter('select[name="campaignevent[properties][addToLists][]"] option[value="'.$foreign->getId().'"]');
        $this->assertCount(1, $foreignOption);
        $this->assertSame('disabled', $foreignOption->attr('disabled'));

        $form   = $crawler->filterXPath('//form[@name="campaignevent"]')->form();
        $values = $form->getPhpValues();
        $this->assertIsArray($values['campaignevent']);
        $this->assertIsArray($values['campaignevent']['properties']);
        $values['campaignevent']['properties']['addToLists'] = [(string) $own->getId()];

        $this->setCsrfHeader();
        $this->client->xmlHttpRequest($form->getMethod(), $form->getUri(), $values);
        $this->assertResponseIsSuccessful();

        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertIsArray($response['event']);
        $this->assertEqualsCanonicalizing(
            [(int) $own->getId(), (int) $foreign->getId()],
            array_map(intval(...), $response['event']['properties']['addToLists'])
        );
    }
}
