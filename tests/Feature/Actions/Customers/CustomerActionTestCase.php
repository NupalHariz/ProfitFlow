<?php

namespace Tests\Feature\Actions\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CustomerActionTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', config('database.default'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createManagedCustomer(
        array $overrides = []
    ): Customer {
        return app(CreateCustomer::class)->execute(
            $this->customerData($overrides)
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function customerData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'PT Nusantara Retail',
            'email' => 'finance@nusantara.test',
            'phone' => '+62 21 555 0101',
            'address' => 'Jakarta',
        ], $overrides);
    }
}
