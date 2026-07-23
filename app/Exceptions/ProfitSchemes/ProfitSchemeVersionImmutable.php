<?php

namespace App\Exceptions\ProfitSchemes;

use DomainException;

final class ProfitSchemeVersionImmutable extends DomainException
{
    public static function published(): self
    {
        return new self(
            'Published profit scheme versions cannot be modified.'
        );
    }

    public static function historicallyReferenced(): self
    {
        return new self(
            'Historically referenced profit scheme versions cannot be modified.'
        );
    }
}
