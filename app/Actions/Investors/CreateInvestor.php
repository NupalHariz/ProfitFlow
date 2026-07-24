<?php

namespace App\Actions\Investors;

use App\Actions\Investors\Support\InvestorInputNormalizer;
use App\Models\Investor;
use Illuminate\Support\Facades\DB;

final class CreateInvestor
{
    public function __construct(
        private readonly InvestorInputNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Investor
    {
        $normalized = $this->normalizer->normalizeForCreation($data);

        return DB::transaction(
            fn (): Investor => Investor::query()->create($normalized)
        );
    }
}
