<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanyTimelineModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CompanyTimelineController extends AbstractFormController
{
    public function indexAction(Request $request, CompanyModel $companyModel, CompanyTimelineModel $timelineModel, int $companyId, int $page = 1): Response
    {
        $company = $companyModel->getEntity($companyId);
        if (!$company instanceof Company) {
            throw new NotFoundHttpException();
        }

        if (!$this->security->hasEntityAccess('lead:leads:viewown', 'lead:leads:viewother', $company->getPermissionUser())) {
            $this->throwAccessDenied();
        }

        $sessionKey = 'mautic.company.'.$companyId.'.timeline';
        $session    = $request->getSession();

        if (!$session->has($sessionKey.'.orderby')) {
            $session->set($sessionKey.'.orderby', 'timestamp');
            $session->set($sessionKey.'.orderbydir', 'DESC');
        }

        $this->setListFilters();

        if ($request->isMethod(Request::METHOD_POST) && $request->request->has('search')) {
            $session->set($sessionKey.'.filters', [
                'search'        => InputHelper::clean($request->request->get('search')),
                'includeEvents' => InputHelper::clean($request->request->all()['includeEvents'] ?? []),
                'excludeEvents' => InputHelper::clean($request->request->all()['excludeEvents'] ?? []),
            ]);
        }

        $events = $timelineModel->getEngagements(
            $company,
            $session->get($sessionKey.'.filters', []),
            [$session->get($sessionKey.'.orderby'), $session->get($sessionKey.'.orderbydir')],
            max(1, $page)
        );

        return $this->delegateView([
            'viewParameters' => [
                'company' => $company,
                'page'    => $page,
                'events'  => $events,
            ],
            'passthroughVars' => [
                'route'         => false,
                'mauticContent' => 'companyTimeline',
                'timelineCount' => $events['total'],
            ],
            'contentTemplate' => '@LeuchtfeuerAccountBasedMarketingAbm/Timeline/_list.html.twig',
        ]);
    }
}
