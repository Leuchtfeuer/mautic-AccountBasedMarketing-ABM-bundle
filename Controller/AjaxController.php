<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Form\Type\FilterPropertiesType;
use Mautic\LeadBundle\Provider\FormAdjustmentsProviderInterface;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AjaxController extends CommonAjaxController
{
    private CompanySegmentRepository $companySegmentRepository;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function autowireLeuchtfeuerAccountBasedMarketingAbmAjaxController(CompanySegmentRepository $companySegmentRepository): void
    {
        $this->companySegmentRepository = $companySegmentRepository;
    }

    public function loadCompanySegmentFilterFormAction(
        Request $request,
        FormFactoryInterface $formFactory,
        FormAdjustmentsProviderInterface $formAdjustmentsProvider,
        CompanySegmentModel $companySegmentModel,
    ): JsonResponse {
        if (true !== $this->security->isGranted(['lead:leads:editown', 'lead:leads:editother'], 'MATCH_ONE')) {
            $this->throwAccessDenied();
        }

        $fieldAlias  = InputHelper::clean($request->request->get('fieldAlias'));
        $fieldObject = InputHelper::clean($request->request->get('fieldObject'));
        $operator    = InputHelper::clean($request->request->get('operator'));
        $filterNum   = $request->request->getInt('filterNum');

        if (!is_string($fieldAlias) || !is_string($fieldObject) || !is_string($operator)) {
            return $this->sendJsonResponse(['success' => 0], Response::HTTP_BAD_REQUEST);
        }

        $form = $formFactory->createNamed('RENAME', FilterPropertiesType::class);

        if ('' !== $fieldAlias && '' !== $operator) {
            $formAdjustmentsProvider->adjustForm(
                $form,
                $fieldAlias,
                $fieldObject,
                $operator,
                $companySegmentModel->getChoiceFields()[$fieldObject][$fieldAlias]
            );
        }

        $formHtml = $this->renderView(
            '@MauticLead/List/filterpropform.html.twig',
            [
                'form' => $form->createView(),
            ]
        );

        $formHtml = str_replace('id="RENAME', "id=\"company_segments_filters_{$filterNum}_properties", $formHtml);
        $formHtml = str_replace('name="RENAME', "name=\"company_segments[filters][{$filterNum}][properties]", $formHtml);

        return $this->sendJsonResponse(
            [
                'viewParameters' => [
                    'form' => $formHtml,
                ],
            ]
        );
    }

    public function getCompaniesCountAction(Request $request, CompanySegmentModel $companySegmentModel): JsonResponse
    {
        if (true !== $this->security->isGranted(['lead:leads:viewown', 'lead:leads:viewother'], 'MATCH_ONE')) {
            $this->throwAccessDenied();
        }

        $id = InputHelper::clean($request->query->get('id'));
        $id = is_numeric($id) ? (int) $id : 0;

        $companyExists = 1 === $this->companySegmentRepository->count(['id' => $id]);

        if (!$companyExists) {
            return $this->sendJsonResponse(['html' => '0', 'companyCount' => 0], Response::HTTP_NOT_FOUND);
        }

        if (!$companySegmentModel->hasSegmentCompanyCountInCache($id)) {
            $companySegmentModel->setSegmentCompanyCountInCache([$id]);
        }

        $companyCounts = $companySegmentModel->getSegmentCompanyCountFromCache([$id]);
        $companyCount  = $companyCounts[$id];

        return $this->sendJsonResponse([
            'html' => $this->translator->trans(
                'mautic.company_segments.companies_count',
                ['%count%' => $companyCount]
            ),
            'companyCount' => $companyCount,
        ]);
    }
}
