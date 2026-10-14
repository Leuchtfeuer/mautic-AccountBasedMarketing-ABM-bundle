<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Services\CompanySegmentRebuildService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: UpdateCompanySegmentsCommand::NAME,
    description: 'Update companies in company segments based on new company data.',
)]
final class UpdateCompanySegmentsCommand extends ModeratedCommand
{
    public const NAME = 'leuchtfeuer:abm:segments:update';

    public function __construct(
        private readonly Config $config,
        private readonly CompanySegmentModel $companySegmentModel,
        private readonly CompanySegmentRebuildService $companyRebuildService,
        private readonly TranslatorInterface $translator,
        PathsHelper $pathsHelper,
        CoreParametersHelper $coreParametersHelper,
        private readonly CompanySegmentRepository $companySegmentRepository,
    ) {
        parent::__construct($pathsHelper, $coreParametersHelper);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                '--batch-limit',
                '-b',
                InputOption::VALUE_OPTIONAL,
                'Set batch size of companies to process per round. Defaults to 300.',
                300
            )
            ->addOption(
                '--max-companies',
                null,
                InputOption::VALUE_REQUIRED,
                'Set max number of companies to process per company segment for this script execution. Defaults to all.'
            )
            ->addOption(
                'companysegment-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Specific company segment ID to rebuild. Defaults to all.'
            )
            ->addOption(
                '--timing',
                '-tm',
                InputOption::VALUE_OPTIONAL,
                'Measure timing of build with output to CLI.',
                false
            )
            ->addOption(
                'exclude-companysegment-id',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Exclude specific company segment IDs from being rebuilt. Otherwise, all company segments will be rebuilt.',
                []
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isPublished()) {
            $output->writeln('<comment>The ABM plugin is not published. Skipping company segment rebuild.</comment>');

            return Command::SUCCESS;
        }

        $companySegmentId       = $input->getOption('companysegment-id');
        $batch                  = $input->getOption('batch-limit');
        $maxCompanies           = $input->getOption('max-companies');
        $enableTimeMeasurement  = (bool) $input->getOption('timing');
        $excludeCompanySegments = $this->cleanExcludeCompanySegments($input->getOption('exclude-companysegment-id'), $output);

        if (null !== $companySegmentId && (!is_numeric($companySegmentId) || ((int) $companySegmentId <= 0))) {
            $output->writeln('<error>The --companysegment-id option must be a positive number or none.</error>');

            return Command::FAILURE;
        }
        $companySegmentId = null !== $companySegmentId ? (int) $companySegmentId : null;

        if (!is_numeric($batch) || (int) $batch < 1) {
            $output->writeln('<error>The --batch-limit option must be a positive number.</error>');

            return Command::FAILURE;
        }
        $batch = (int) $batch;

        if (null !== $maxCompanies && (!is_numeric($maxCompanies) || (int) $maxCompanies <= 0)) {
            $output->writeln('<error>The --max-companies option must be a positive number or none.</error>');

            return Command::FAILURE;
        }
        $maxCompanies = null !== $maxCompanies ? (int) $maxCompanies : null;

        if (!$this->checkRunStatus($input, $output, $companySegmentId)) {
            return Command::SUCCESS;
        }

        if ($enableTimeMeasurement) {
            $startTime = microtime(true);
        }

        if (null !== $companySegmentId) {
            $result = $this->processSingleCompanySegment($companySegmentId, $batch, $maxCompanies, $output, $enableTimeMeasurement, $excludeCompanySegments);
            if (Command::FAILURE === $result) {
                return Command::FAILURE;
            }
        } else {
            $this->processAllCompanySegments($batch, $maxCompanies, $output, $enableTimeMeasurement, $excludeCompanySegments);
        }

        $this->completeRun();

        if ($enableTimeMeasurement) {
            $totalTime = round(microtime(true) - $startTime, 2);
            $output->writeln('<fg=magenta>'.$this->translator->trans('mautic.company_segments.rebuild.total.time', ['%time%' => $totalTime]).'</>'."\n");
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int> $excludeSegments
     */
    private function processSingleCompanySegment(int $id, int $batch, ?int $max, OutputInterface $output, bool $enableTimeMeasurement, array $excludeSegments): int
    {
        $segment = $this->companySegmentModel->getEntity($id);

        if (!$segment instanceof CompanySegment || null === $segment->getId()) {
            $output->writeln('<error>'.$this->translator->trans('mautic.company_segments.rebuild.not_found', ['%id%' => $id]).'</error>');

            return Command::FAILURE;
        }

        $rebuiltSegments = [];

        if ($segment->hasFilterTypeOf(CompanySegmentModel::PROPERTIES_FIELD)) {
            $this->companyRebuildService->rebuildDependentSegments($segment, $rebuiltSegments, $batch, $max, $output, $enableTimeMeasurement, [], $excludeSegments);
        }

        $this->companyRebuildService->rebuildSegment($segment, $batch, $max, $output, $enableTimeMeasurement);

        return Command::SUCCESS;
    }

    /**
     * @param array<int> $excludeSegments
     */
    private function processAllCompanySegments(int $batch, ?int $max, OutputInterface $output, bool $enableTimeMeasurement, array $excludeSegments): void
    {
        $filter = [
            'iterable_mode' => true,
        ];

        if ([] !== $excludeSegments) {
            $filter['filter'] = [
                'force' => [
                    [
                        'expr'   => 'notIn',
                        'column' => $this->companySegmentRepository->getTableAlias().'.id',
                        'value'  => $excludeSegments,
                    ],
                ],
            ];
        }

        $companySegments = $this->companySegmentModel->getEntities($filter);
        $rebuiltSegments = [];

        foreach ($companySegments as $companySegment) {
            assert($companySegment instanceof CompanySegment);
            $segmentId = $companySegment->getId();

            if (in_array($segmentId, $rebuiltSegments)) {
                continue;
            }

            if ($companySegment->hasFilterTypeOf(CompanySegmentModel::PROPERTIES_FIELD)) {
                $this->companyRebuildService->rebuildDependentSegments($companySegment, $rebuiltSegments, $batch, $max, $output, $enableTimeMeasurement, [], $excludeSegments);
            }

            $rebuiltSegments[] = $segmentId;
            $this->companyRebuildService->rebuildSegment($companySegment, $batch, $max, $output, $enableTimeMeasurement);
            unset($companySegment);
        }
        unset($companySegments);
    }

    /**
     * @param array<mixed> $excludeSegments
     *
     * @return array<int>
     */
    private function cleanExcludeCompanySegments(array $excludeSegments, OutputInterface $output): array
    {
        if (0 === count($excludeSegments)) {
            return [];
        }

        $existingExcludeSegments = [];

        foreach ($excludeSegments as $id) {
            if (null !== $id && (!is_numeric($id) || ((int) $id <= 0))) {
                $output->writeln("<error>Skipped --exclude-companysegment-id id {$id}: The segment id {$id} is not a positive number.</error>");
                continue;
            }
            if (!$this->companySegmentModel->getEntity((int) $id) instanceof CompanySegment) {
                $output->writeln("<error>Skipped --exclude-companysegment-id id {$id}: There is no company segment with the id {$id}.</error>");
                continue;
            }
            $existingExcludeSegments[] = (int) $id;
        }

        return $existingExcludeSegments;
    }
}
