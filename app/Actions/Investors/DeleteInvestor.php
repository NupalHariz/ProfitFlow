<?php

namespace App\Actions\Investors;

use App\Exceptions\Investors\InvalidInvestor;
use App\Models\Investor;
use Illuminate\Support\Facades\DB;

final class DeleteInvestor
{
    public function execute(Investor $investor): void
    {
        DB::transaction(function () use ($investor): void {
            $lockedInvestor = Investor::query()
                ->whereKey($investor->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedInvestor === null) {
                throw new InvalidInvestor(
                    'Investor does not exist.'
                );
            }

            if ($lockedInvestor->profitRecipient()->exists()) {
                throw new InvalidInvestor(
                    'This Investor is linked to a Profit Recipient and cannot '
                    .'be deleted. Deactivate the Investor or manage the '
                    .'Profit Recipient separately.'
                );
            }

            $lockedInvestor->delete();
        });
    }
}
