<?php

namespace Tests\Feature\Actions\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Exceptions\Customers\InvalidCustomer;
use App\Models\Customer;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateCustomerTest extends CustomerActionTestCase
{
    public function test_it_creates_a_normalized_customer(): void
    {
        $customer = app(CreateCustomer::class)->execute([
            'name' => '  PT Nusantara Retail  ',
            'email' => '  finance@nusantara.test  ',
            'phone' => '  +62 21 555 0101  ',
            'address' => '  Jakarta  ',
        ]);

        $this->assertTrue($customer->exists);
        $this->assertSame('PT Nusantara Retail', $customer->name);
        $this->assertSame(
            'finance@nusantara.test',
            $customer->email
        );
        $this->assertSame('+62 21 555 0101', $customer->phone);
        $this->assertSame('Jakarta', $customer->address);
    }

    public function test_blank_and_omitted_optional_values_become_null(): void
    {
        $blank = app(CreateCustomer::class)->execute([
            'name' => 'Blank Contact',
            'email' => '   ',
            'phone' => '',
            'address' => "\n ",
        ]);
        $omitted = app(CreateCustomer::class)->execute([
            'name' => 'Only Name',
        ]);

        foreach ([$blank, $omitted] as $customer) {
            $this->assertNull($customer->email);
            $this->assertNull($customer->phone);
            $this->assertNull($customer->address);
        }
    }

    public function test_duplicate_email_is_allowed(): void
    {
        $first = $this->createManagedCustomer();
        $second = $this->createManagedCustomer([
            'name' => 'Second Customer',
        ]);

        $this->assertSame($first->email, $second->email);
        $this->assertDatabaseCount('customers', 2);
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_name_is_rejected(mixed $name): void
    {
        $this->expectException(InvalidCustomer::class);

        app(CreateCustomer::class)->execute(
            $this->customerData(['name' => $name])
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidNames(): array
    {
        return [
            'missing type' => [123],
            'blank' => ['   '],
            'too long' => [str_repeat('x', 161)],
        ];
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->expectException(InvalidCustomer::class);

        app(CreateCustomer::class)->execute(
            $this->customerData(['email' => 'not-an-email'])
        );
    }

    public function test_phone_length_is_enforced(): void
    {
        $this->expectException(InvalidCustomer::class);

        app(CreateCustomer::class)->execute(
            $this->customerData(['phone' => str_repeat('1', 41)])
        );
    }

    public function test_unsupported_fields_are_rejected(): void
    {
        try {
            app(CreateCustomer::class)->execute([
                ...$this->customerData(),
                'id' => 999,
                'deleted_at' => now(),
                'transactions' => [],
            ]);
            $this->fail('Expected unsupported fields to fail.');
        } catch (InvalidCustomer $exception) {
            $this->assertStringContainsString(
                'id',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, Customer::query()->count());
    }
}
