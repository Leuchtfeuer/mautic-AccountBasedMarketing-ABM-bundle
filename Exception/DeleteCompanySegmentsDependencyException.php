<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Exception;

use Symfony\Component\HttpFoundation\Response;

final class DeleteCompanySegmentsDependencyException extends \Exception
{
    /**
     * @param object[] $deletedEntities
     * @param object[] $unableToDeleteEntities
     * @param string[] $errors
     */
    public function __construct(
        private readonly array $deletedEntities,
        private readonly array $unableToDeleteEntities,
        private readonly array $errors = [],
        string $message = '',
        int $code = Response::HTTP_CONFLICT,
    ) {
        parent::__construct($message, $code);
    }

    /**
     * @return object[]
     */
    public function getDeletedEntities(): array
    {
        return $this->deletedEntities;
    }

    /**
     * @return object[]
     */
    public function getUnableToDeleteEntities(): array
    {
        return $this->unableToDeleteEntities;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
