<?php

namespace App\Exceptions\ProfitSchemes;

use DomainException;
use Throwable;

final class InvalidProfitSchemeAllocation extends DomainException
{
    public function __construct(
        string $message = '',
        private readonly ?int $allocationIndex = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function allocationIndex(): ?int
    {
        return $this->allocationIndex;
    }
}
