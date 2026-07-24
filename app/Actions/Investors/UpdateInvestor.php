<?php

namespace App\Actions\Investors;

use App\Actions\Investors\Support\InvestorInputNormalizer;
use App\Exceptions\Investors\InvalidInvestor;
use App\Models\Investor;
use Illuminate\Support\Facades\DB;

final class UpdateInvestor
{
    public function __construct(
        private readonly InvestorInputNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Investor $investor, array $data): Investor
    {
        $normalized = $this->normalizer->normalizeForUpdate($data);

        return DB::transaction(function () use (
            $investor,
            $normalized
        ): Investor {
            $lockedInvestor = Investor::query()
                ->whereKey($investor->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedInvestor === null) {
                throw new InvalidInvestor(
                    'Investor does not exist.'
                );
            }

            $lockedInvestor->fill($normalized);
            $lockedInvestor->save();

            return $lockedInvestor->refresh();
        });
    }
}
