<?php

namespace Tests\Feature\Actions\Investors;

use App\Actions\Investors\CreateInvestor;
use App\Exceptions\Investors\InvalidInvestor;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateInvestorTest extends InvestorActionTestCase
{
    public function test_it_creates_only_a_normalized_investor_profile(): void
    {
        $investor = app(CreateInvestor::class)->execute([
            'name' => '  Naufal Pratama  ',
            'email' => '  investor@example.test  ',
            'phone' => '  +62 812-0000-1001  ',
            'is_active' => false,
        ]);

        $this->assertSame('Naufal Pratama', $investor->name);
        $this->assertSame('investor@example.test', $investor->email);
        $this->assertSame('+6281200001001', $investor->phone);
        $this->assertFalse($investor->is_active);
        $this->assertDatabaseCount('investors', 1);
        $this->assertDatabaseCount('profit_recipients', 0);
    }

    public function test_blank_options_become_null_and_duplicate_email_is_allowed(): void
    {
        $first = $this->createManagedInvestor([
            'email' => 'shared@example.test',
            'phone' => ' ',
        ]);
        $second = $this->createManagedInvestor([
            'name' => 'Second Investor',
            'email' => 'shared@example.test',
            'phone' => null,
        ]);

        $this->assertNull($first->phone);
        $this->assertNull($second->phone);
        $this->assertSame(
            2,
            Investor::query()
                ->where('email', 'shared@example.test')
                ->count()
        );
        $this->assertSame(0, ProfitRecipient::query()->count());
    }

    #[DataProvider('supportedPhoneFormats')]
    public function test_it_uses_the_shared_phone_normalization(
        string $phone,
        string $expected
    ): void {
        $investor = $this->createManagedInvestor(['phone' => $phone]);

        $this->assertSame($expected, $investor->phone);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function supportedPhoneFormats(): array
    {
        return [
            'local mobile' => ['081234567890', '+6281234567890'],
            'country code mobile' => ['6281234567890', '+6281234567890'],
            'formatted international mobile' => [
                '+62 (812) 3456-7890',
                '+6281234567890',
            ],
            'local landline' => ['0215550102', '+62215550102'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_or_unsupported_input_is_rejected(
        array $overrides
    ): void {
        $this->expectException(InvalidInvestor::class);

        app(CreateInvestor::class)->execute(
            $this->investorData($overrides)
        );
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidInput(): array
    {
        return [
            'blank name' => [['name' => '  ']],
            'long name' => [['name' => str_repeat('x', 161)]],
            'invalid email' => [['email' => 'invalid']],
            'long email' => [['email' => str_repeat('x', 250).'@x.test']],
            'long phone' => [['phone' => str_repeat('1', 41)]],
            'unsupported phone country' => [
                ['phone' => '+1 555 010 1000'],
            ],
            'phone with letters' => [['phone' => '0812-CALL-NOW']],
            'phone without prefix' => [['phone' => '81234567890']],
            'phone too short' => [['phone' => '08123']],
            'non-boolean status' => [['is_active' => '1']],
            'protected field' => [['deleted_at' => now()]],
        ];
    }
}
