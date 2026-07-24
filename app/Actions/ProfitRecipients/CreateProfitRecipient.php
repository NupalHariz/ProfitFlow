<?php

namespace App\Actions\ProfitRecipients;

use App\Actions\ProfitRecipients\Support\ProfitRecipientInputNormalizer;
use App\Enums\ProfitRecipientType;
use App\Exceptions\ProfitRecipients\InvestorLinkUnavailable;
use App\Exceptions\ProfitRecipients\ProfitRecipientCodeAlreadyExists;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CreateProfitRecipient
{
    public function __construct(
        private readonly ProfitRecipientInputNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ProfitRecipient
    {
        $normalized = $this->normalizer->normalizeForCreation($data);

        try {
            return DB::transaction(function () use (
                $normalized
            ): ProfitRecipient {
                if ($normalized['type'] === ProfitRecipientType::Investor) {
                    $investor = Investor::query()
                        ->whereKey($normalized['investor_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($investor === null) {
                        throw new InvestorLinkUnavailable(
                            'The selected Investor does not exist or has '
                            .'been deleted.'
                        );
                    }

                    if ($investor->profitRecipient()->exists()) {
                        throw new InvestorLinkUnavailable(
                            'The selected Investor is already linked to a '
                            .'Profit Recipient.'
                        );
                    }
                }

                return ProfitRecipient::query()->create($normalized);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation(
                $exception,
                'uq_profit_recipients_code'
            )) {
                throw new ProfitRecipientCodeAlreadyExists(
                    "Profit Recipient code [{$normalized['code']}] already "
                    .'exists.',
                    previous: $exception
                );
            }

            if ($this->isUniqueViolation(
                $exception,
                'uq_profit_recipients_investor'
            )) {
                throw new InvestorLinkUnavailable(
                    'The selected Investor is already linked to a Profit '
                    .'Recipient.',
                    previous: $exception
                );
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(
        QueryException $exception,
        string $constraint
    ): bool {
        return (string) $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), $constraint);
    }
}
