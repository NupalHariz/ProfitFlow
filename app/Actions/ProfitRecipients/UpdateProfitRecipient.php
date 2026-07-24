<?php

namespace App\Actions\ProfitRecipients;

use App\Actions\ProfitRecipients\Support\ProfitRecipientInputNormalizer;
use App\Exceptions\ProfitRecipients\InvalidProfitRecipient;
use App\Exceptions\ProfitRecipients\ProfitRecipientCodeAlreadyExists;
use App\Models\ProfitRecipient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class UpdateProfitRecipient
{
    public function __construct(
        private readonly ProfitRecipientInputNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        ProfitRecipient $recipient,
        array $data
    ): ProfitRecipient {
        $normalized = $this->normalizer->normalizeForUpdate($data);

        try {
            return DB::transaction(function () use (
                $recipient,
                $normalized
            ): ProfitRecipient {
                $lockedRecipient = ProfitRecipient::query()
                    ->whereKey($recipient->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($lockedRecipient === null) {
                    throw new InvalidProfitRecipient(
                        'Profit Recipient does not exist.'
                    );
                }

                $lockedRecipient->fill($normalized);
                $lockedRecipient->save();

                return $lockedRecipient->refresh();
            });
        } catch (QueryException $exception) {
            if ($this->isCodeUniqueViolation($exception)) {
                throw new ProfitRecipientCodeAlreadyExists(
                    "Profit Recipient code [{$normalized['code']}] already "
                    .'exists.',
                    previous: $exception
                );
            }

            throw $exception;
        }
    }

    private function isCodeUniqueViolation(
        QueryException $exception
    ): bool {
        return (string) $exception->getCode() === '23505'
            && str_contains(
                $exception->getMessage(),
                'uq_profit_recipients_code'
            );
    }
}
