<?php

namespace App\Actions\ProfitSchemes\Support;

use App\Exceptions\ProfitSchemes\ProfitSchemeVersionImmutable;
use App\Models\ProfitSchemeVersion;

final class ProfitSchemeVersionMutationGuard
{
    public function assertMutable(ProfitSchemeVersion $version): void
    {
        if ($version->getAttribute('published_at') !== null) {
            throw ProfitSchemeVersionImmutable::published();
        }

        $this->assertNotHistoricallyReferenced($version);
    }

    public function assertNotHistoricallyReferenced(
        ProfitSchemeVersion $version
    ): void {
        if ($version->appliedTransactionItems()->exists()) {
            throw ProfitSchemeVersionImmutable::historicallyReferenced();
        }
    }
}
