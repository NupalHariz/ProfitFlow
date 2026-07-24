<?php

namespace App\Actions\ProfitRecipients;

use App\Actions\ProfitRecipients\Support\ProfitRecipientInputNormalizer;
use App\Exceptions\ProfitRecipients\InvalidProfitRecipient;
use App\Models\ProfitRecipient;
use Illuminate\Support\Facades\DB;

final class SetProfitRecipientActive
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
        $normalized = $this->normalizer->normalizeForStatus($data);

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

            if ($lockedRecipient->is_active
                !== $normalized['is_active']) {
                $lockedRecipient->is_active = $normalized['is_active'];
                $lockedRecipient->save();
            }

            return $lockedRecipient->refresh();
        });
    }
}
