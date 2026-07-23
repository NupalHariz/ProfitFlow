<?php

namespace App\Actions\Customers;

use App\Exceptions\Customers\InvalidCustomer;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class DeleteCustomer
{
    public function execute(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedCustomer === null) {
                throw new InvalidCustomer(
                    'Customer does not exist.'
                );
            }

            $lockedCustomer->delete();
        });
    }
}
