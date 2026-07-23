<?php

namespace App\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\Support\ProfitSchemeAllocationValidator;
use App\Actions\ProfitSchemes\Support\ProfitSchemeVersionMutationGuard;
use App\Actions\ProfitSchemes\Support\ProfitSchemeVersionValidator;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use Illuminate\Support\Facades\DB;

final class PublishProfitSchemeVersion
{
    public function __construct(
        private readonly ProfitSchemeVersionMutationGuard $guard,
        private readonly ProfitSchemeVersionValidator $versionValidator,
        private readonly ProfitSchemeAllocationValidator $allocationValidator
    ) {}

    public function execute(
        ProfitSchemeVersion $version
    ): ProfitSchemeVersion {
        return DB::transaction(function () use (
            $version
        ): ProfitSchemeVersion {
            $lockedVersion = ProfitSchemeVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedVersion === null) {
                throw new InvalidProfitSchemeVersion(
                    'Profit scheme version does not exist.'
                );
            }

            if ($lockedVersion->getAttribute('published_at') !== null) {
                return $this->loadOrderedAllocations($lockedVersion);
            }

            $this->guard->assertNotHistoricallyReferenced($lockedVersion);
            $this->versionValidator->assertValidForPublishing(
                $lockedVersion
            );

            $allocationModels = ProfitSchemeAllocation::query()
                ->where(
                    'profit_scheme_version_id',
                    $lockedVersion->id
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $entries = [];

            foreach ($allocationModels as $allocation) {
                $entries[] = [
                    'profit_recipient_id' => (int) $allocation->getAttribute(
                        'profit_recipient_id'
                    ),
                    'percentage' => (string) $allocation->getAttribute(
                        'percentage'
                    ),
                ];
            }

            $normalized = $this->allocationValidator->normalize($entries);
            $this->allocationValidator->assertPublishable($normalized);

            $lockedVersion->setAttribute('published_at', now());
            $lockedVersion->save();

            return $this->loadOrderedAllocations($lockedVersion);
        });
    }

    private function loadOrderedAllocations(
        ProfitSchemeVersion $version
    ): ProfitSchemeVersion {
        $version->refresh();
        $version->setRelation(
            'allocations',
            $version->allocations()->orderBy('id')->get()
        );

        return $version;
    }
}
