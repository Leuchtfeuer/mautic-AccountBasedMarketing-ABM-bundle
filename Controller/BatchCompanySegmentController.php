<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\CompanyBatchType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentActionModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BatchCompanySegmentController extends AbstractFormController
{
    public function setAction(CompanySegmentActionModel $segmentActionModel, Request $request): JsonResponse
    {
        if (true !== $this->security->isGranted(['lead:leads:editown', 'lead:leads:editother'], 'MATCH_ONE')) {
            $this->throwAccessDenied();
        }

        $form = $this->createForm(CompanyBatchType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlashMessage('mautic.core.error.badrequest');

            return new JsonResponse([
                'closeModal' => true,
                'flashes'    => $this->getFlashContent(),
            ]);
        }

        $params     = $form->getData();
        $companyIds = [];

        if (is_string($params['ids'] ?? null) && '' !== $params['ids']) {
            try {
                /** @var array<int> $companyIds */
                $companyIds = json_decode($params['ids'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $companyIds = [];
            }
        }

        if ([] !== $companyIds && is_array($companyIds)) {
            /** @var array<int> $segmentsToAdd */
            $segmentsToAdd    = $params['add'] ?? [];
            /** @var array<int> $segmentsToRemove */
            $segmentsToRemove = $params['remove'] ?? [];

            if (is_array($segmentsToAdd) && [] !== $segmentsToAdd) {
                $segmentActionModel->addCompanies($companyIds, $segmentsToAdd);
            }

            if (is_array($segmentsToRemove) && [] !== $segmentsToRemove) {
                $segmentActionModel->removeCompanies($companyIds, $segmentsToRemove);
            }

            $this->addFlashMessage('mautic.company_segments.batch_companies_affected', [
                '%count%' => count($companyIds),
            ]);
        } else {
            $this->addFlashMessage('mautic.core.error.ids.missing');
        }

        return new JsonResponse([
            'closeModal' => true,
            'flashes'    => $this->getFlashContent(),
        ]);
    }

    public function indexAction(): Response
    {
        $route = $this->generateUrl('mautic_company_segments_batch_company_set');

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form' => $this->createForm(
                        CompanyBatchType::class,
                        [],
                        [
                            'action' => $route,
                        ]
                    )->createView(),
                ],
                'contentTemplate' => '@LeuchtfeuerAccountBasedMarketingAbm/CompanySegment/Batch/form.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_company_index',
                    'mauticContent' => 'companyBatch',
                    'route'         => $route,
                ],
            ]
        );
    }
}
