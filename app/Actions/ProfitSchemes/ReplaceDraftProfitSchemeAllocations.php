<?php

namespace App\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\Support\ProfitSchemeAllocationValidator;
use App\Actions\ProfitSchemes\Support\ProfitSchemeVersionMutationGuard;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use Illuminate\Support\Facades\DB;

final class ReplaceDraftProfitSchemeAllocations
{
    public function __construct(
        private readonly ProfitSchemeVersionMutationGuard $guard,
        private readonly ProfitSchemeAllocationValidator $validator
    ) {}

    /**
     * @param  array<array-key, mixed>  $allocations
     */
    public function execute(
        ProfitSchemeVersion $version,
        array $allocations
    ): ProfitSchemeVersion {
        return DB::transaction(function () use (
            $version,
            $allocations
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

            $this->guard->assertMutable($lockedVersion);
            $normalized = $this->validator->normalize($allocations);

            ProfitSchemeAllocation::query()
                ->where('profit_scheme_version_id', $lockedVersion->id)
                ->delete();

            foreach ($normalized as $allocation) {
                ProfitSchemeAllocation::query()->create([
                    'profit_scheme_version_id' => $lockedVersion->id,
                    'profit_recipient_id' => $allocation[
                        'profit_recipient_id'
                    ],
                    'percentage' => $allocation['percentage'],
                ]);
            }

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
