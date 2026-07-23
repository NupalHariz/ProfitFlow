<?php

namespace App\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\Support\ProfitSchemeVersionMutationGuard;
use App\Actions\ProfitSchemes\Support\ProfitSchemeVersionValidator;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Models\ProfitSchemeVersion;
use Illuminate\Support\Facades\DB;

final class UpdateDraftProfitSchemeVersion
{
    public function __construct(
        private readonly ProfitSchemeVersionMutationGuard $guard,
        private readonly ProfitSchemeVersionValidator $validator
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        ProfitSchemeVersion $version,
        array $data
    ): ProfitSchemeVersion {
        return DB::transaction(function () use (
            $version,
            $data
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
            $normalized = $this->validator->normalizeForUpdate(
                $lockedVersion,
                $data
            );

            $lockedVersion->fill($normalized);
            $lockedVersion->save();

            return $lockedVersion->refresh();
        });
    }
}
