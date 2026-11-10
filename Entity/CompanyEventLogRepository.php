<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompanyEventLog>
 */
class CompanyEventLogRepository extends CommonRepository
{
    public function getTableAlias(): string
    {
        return 'cel';
    }
}
