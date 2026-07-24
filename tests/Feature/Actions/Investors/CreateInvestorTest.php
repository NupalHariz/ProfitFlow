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
            'phone' => '  +62 812 0000  ',
            'is_active' => false,
        ]);

        $this->assertSame('Naufal Pratama', $investor->name);
        $this->assertSame('investor@example.test', $investor->email);
        $this->assertSame('+62 812 0000', $investor->phone);
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
            'non-boolean status' => [['is_active' => '1']],
            'protected field' => [['deleted_at' => now()]],
        ];
    }
}
