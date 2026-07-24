<?php

namespace App\Actions\Investors;

use App\Exceptions\Investors\InvalidInvestor;
use App\Models\Investor;
use Illuminate\Support\Facades\DB;

final class SetInvestorActive
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Investor $investor, array $data): Investor
    {
        $unsupported = array_diff(array_keys($data), ['is_active']);

        if ($unsupported !== []) {
            throw new InvalidInvestor(
                'Unsupported investor status fields: '
                .implode(', ', $unsupported).'.'
            );
        }

        if (! array_key_exists('is_active', $data)
            || ! is_bool($data['is_active'])) {
            throw new InvalidInvestor(
                'Investor is_active must be a boolean.'
            );
        }

        return DB::transaction(function () use (
            $investor,
            $data
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

            if ($lockedInvestor->is_active === $data['is_active']) {
                return $lockedInvestor;
            }

            $lockedInvestor->is_active = $data['is_active'];
            $lockedInvestor->save();

            return $lockedInvestor->refresh();
        });
    }
}
