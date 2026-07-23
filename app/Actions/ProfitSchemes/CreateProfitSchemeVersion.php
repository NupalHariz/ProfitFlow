<?php

namespace App\Actions\ProfitSchemes;

use App\Actions\ProfitSchemes\Support\ProfitSchemeVersionValidator;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateProfitSchemeVersion
{
    public function __construct(
        private readonly ProfitSchemeVersionValidator $validator
    ) {}

    /**
     * @param  array{
     *     product_category_id?: mixed,
     *     minimum_profit?: mixed,
     *     maximum_profit?: mixed,
     *     effective_from: mixed,
     *     effective_until?: mixed,
     *     priority: mixed,
     *     is_active: mixed
     * }  $data
     */
    public function execute(
        ProfitScheme $scheme,
        array $data,
        User $creator
    ): ProfitSchemeVersion {
        $normalized = $this->validator->normalizeForCreation($data);

        if (! $creator->exists) {
            throw new InvalidProfitSchemeVersion(
                'Profit scheme version creator must be a persisted user.'
            );
        }

        return DB::transaction(function () use (
            $scheme,
            $normalized,
            $creator
        ): ProfitSchemeVersion {
            $lockedScheme = ProfitScheme::query()
                ->whereKey($scheme->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedScheme === null) {
                throw new InvalidProfitSchemeVersion(
                    'Profit scheme must exist before creating a version.'
                );
            }

            $currentMaximum = ProfitSchemeVersion::query()
                ->where('profit_scheme_id', $lockedScheme->id)
                ->max('version');
            $nextVersion = ((int) ($currentMaximum ?? 0)) + 1;

            return ProfitSchemeVersion::query()->create([
                'profit_scheme_id' => $lockedScheme->id,
                'version' => $nextVersion,
                ...$normalized,
                'published_at' => null,
                'created_by_user_id' => $creator->id,
            ]);
        });
    }
}
