<?php

declare(strict_types=1);

return [
    'name'        => 'ABM (Account Based Marketing) by Leuchtfeuer',
    'description' => 'A suite of company-centric enhancements like Company Segments and Company Scoring',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'menu'        => [
        'main' => [
            'items' => [
                'mautic.company_segments.menu.index' => [
                    'access'   => ['lead:leads:viewown', 'lead:leads:viewother'],
                    'route'    => 'mautic_company_segments_index',
                    'parent'   => 'mautic.companies.menu.index',
                    'priority' => 15,
                ],
                // Makes "Companies" itself clickable as a sub item below the
                // core "Companies" node that now acts as the parent.
                'mautic.companies.menu.sub.index' => [
                    'id'       => 'mautic.companies.menu.index',
                    'parent'   => 'mautic.companies.menu.index',
                    'route'    => 'mautic_company_index',
                    'access'   => ['lead:leads:viewother'],
                    'priority' => 100,
                ],
            ],
        ],
    ],
    'routes'      => [
        'main' => [
            'mautic_company_segments_index' => [
                'path'       => '/company-segments/{page}',
                'controller' => 'MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\CompanySegmentController::indexAction',
            ],
            'mautic_company_segments_action' => [
                'path'       => '/company-segments/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\CompanySegmentController::executeAction',
            ],
            'mautic_company_segments_batch_company_set' => [
                'path'       => '/company-segments/batch/company/set',
                'controller' => 'MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\BatchCompanySegmentController::setAction',
            ],
            'mautic_company_segments_batch_company_view' => [
                'path'       => '/company-segments/batch/company/view',
                'controller' => 'MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\BatchCompanySegmentController::indexAction',
            ],
        ],
        'api' => [
            'mautic_api_companysegmentsstandard' => [
                'standard_entity' => true,
                'name'            => 'companysegments',
                'path'            => '/companysegments',
                'controller'      => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\Api\CompanySegmentApiController::class,
            ],
            'mautic_api_companysegmentaddcompany' => [
                'path'       => '/companysegments/{id}/company/{companyId}/add',
                'controller' => 'MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\Api\CompanySegmentApiController::addCompanyAction',
                'method'     => 'POST',
            ],
            'mautic_api_companysegmentaddcompanies' => [
                'path'       => '/companysegments/{id}/companies/add',
                'controller' => 'MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\Api\CompanySegmentApiController::addCompaniesAction',
                'method'     => 'POST',
            ],
            'mautic_api_companysegmentremovecompany' => [
                'path'       => '/companysegments/{id}/company/{companyId}/remove',
                'controller' => 'MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\Api\CompanySegmentApiController::removeCompanyAction',
                'method'     => 'POST',
            ],
        ],
    ],
];
