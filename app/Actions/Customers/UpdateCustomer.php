<?php

namespace App\Actions\Customers;

use App\Actions\Customers\Support\CustomerInputNormalizer;
use App\Exceptions\Customers\InvalidCustomer;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class UpdateCustomer
{
    public function __construct(
        private readonly CustomerInputNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Customer $customer, array $data): Customer
    {
        $normalized = $this->normalizer->normalize($data);

        return DB::transaction(function () use (
            $customer,
            $normalized
        ): Customer {
            $lockedCustomer = Customer::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedCustomer === null) {
                throw new InvalidCustomer(
                    'Customer does not exist.'
                );
            }

            $lockedCustomer->fill($normalized);
            $lockedCustomer->save();

            return $lockedCustomer->refresh();
        });
    }
}
