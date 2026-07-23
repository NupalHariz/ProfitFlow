<?php

namespace App\Actions\Customers;

use App\Actions\Customers\Support\CustomerInputNormalizer;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class CreateCustomer
{
    public function __construct(
        private readonly CustomerInputNormalizer $normalizer
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Customer
    {
        $normalized = $this->normalizer->normalize($data);

        return DB::transaction(
            fn (): Customer => Customer::query()->create($normalized)
        );
    }
}
