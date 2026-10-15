<?php

declare(strict_types=1);

return [
    'name'        => 'ABM (Account Based Marketing) by Leuchtfeuer',
    'description' => 'A suite of company-centric enhancements like Company Segments and Company Scoring',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'categories'  => [
        'company_segment' => [
            'class' => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment::class,
        ],
    ],
    'menu'        => [
        'main' => [
            'items' => [
                'mautic.company_segments.menu.index' => [
                    'access'   => ['lead:lists:viewown', 'lead:lists:viewother'],
                    'route'    => 'mautic_company_segments_index',
                    'parent'   => 'mautic.companies.menu.index',
                    'priority' => 15,
                    'checks'   => [
                        'integration' => [
                            MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\LeuchtfeuerAccountBasedMarketingAbmIntegration::NAME => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                ],
                // Makes "Companies" itself clickable as a sub item below the
                // core "Companies" node that now acts as the parent.
                'mautic.companies.menu.sub.index' => [
                    'id'       => 'mautic.companies.menu.index',
                    'parent'   => 'mautic.companies.menu.index',
                    'route'    => 'mautic_company_index',
                    'access'   => ['lead:leads:viewother'],
                    'priority' => 100,
                    'checks'   => [
                        'integration' => [
                            MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\LeuchtfeuerAccountBasedMarketingAbmIntegration::NAME => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'routes'      => [
        'main' => [
            'mautic_company_segments_index' => [
                'path'       => '/company-segments/{page}',
                'controller' => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\CompanySegmentController::class.'::indexAction',
            ],
            'mautic_company_segments_action' => [
                'path'       => '/company-segments/{objectAction}/{objectId}',
                'controller' => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\CompanySegmentController::class.'::executeAction',
            ],
            'mautic_company_segments_batch_company_set' => [
                'path'       => '/company-segments/batch/company/set',
                'controller' => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\BatchCompanySegmentController::class.'::setAction',
            ],
            'mautic_company_segments_batch_company_view' => [
                'path'       => '/company-segments/batch/company/view',
                'controller' => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\BatchCompanySegmentController::class.'::indexAction',
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
                'controller' => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\Api\CompanySegmentApiController::class.'::addCompanyAction',
                'method'     => 'POST',
            ],
            'mautic_api_companysegmentaddcompanies' => [
                'path'       => '/companysegments/{id}/companies/add',
                'controller' => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\Api\CompanySegmentApiController::class.'::addCompaniesAction',
                'method'     => 'POST',
            ],
            'mautic_api_companysegmentremovecompany' => [
                'path'       => '/companysegments/{id}/company/{companyId}/remove',
                'controller' => MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\Api\CompanySegmentApiController::class.'::removeCompanyAction',
                'method'     => 'POST',
            ],
        ],
    ],
];
