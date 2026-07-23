<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_number' => strtoupper(
                $this->faker->unique()->bothify('TRX-########-????')
            ),
            'idempotency_key' => null,
            'customer_id' => Customer::factory(),
            'transaction_date' => now()->toDateString(),
            'status' => TransactionStatus::Draft,
            'total_revenue' => '0.00',
            'total_production_cost' => '0.00',
            'total_net_profit' => '0.00',
            'failure_reason' => null,
            'created_by_user_id' => User::factory(),
            'completed_by_user_id' => null,
            'completed_at' => null,
            'distribution_started_at' => null,
            'distributed_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function pendingDistribution(): static
    {
        return $this->state(function (): array {
            $completedAt = CarbonImmutable::now()->subMinutes(2);

            return [
                'status' => TransactionStatus::PendingDistribution,
                'completed_by_user_id' => User::factory(),
                'completed_at' => $completedAt,
                'distribution_started_at' => null,
                'distributed_at' => null,
                'cancelled_at' => null,
                'failure_reason' => null,
            ];
        });
    }

    public function processingDistribution(): static
    {
        return $this->state(function (): array {
            $now = CarbonImmutable::now();

            return [
                'status' => TransactionStatus::ProcessingDistribution,
                'completed_by_user_id' => User::factory(),
                'completed_at' => $now->subMinutes(2),
                'distribution_started_at' => $now->subMinute(),
                'distributed_at' => null,
                'cancelled_at' => null,
                'failure_reason' => null,
            ];
        });
    }

    public function distributed(): static
    {
        return $this->state(function (): array {
            $now = CarbonImmutable::now();

            return [
                'status' => TransactionStatus::Distributed,
                'completed_by_user_id' => User::factory(),
                'completed_at' => $now->subMinutes(3),
                'distribution_started_at' => $now->subMinutes(2),
                'distributed_at' => $now,
                'cancelled_at' => null,
                'failure_reason' => null,
            ];
        });
    }

    public function distributionFailed(): static
    {
        return $this->state(function (): array {
            $now = CarbonImmutable::now();

            return [
                'status' => TransactionStatus::DistributionFailed,
                'completed_by_user_id' => User::factory(),
                'completed_at' => $now->subMinutes(3),
                'distribution_started_at' => $now->subMinutes(2),
                'distributed_at' => null,
                'cancelled_at' => null,
                'failure_reason' => 'Factory-generated distribution failure.',
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatus::Cancelled,
            'completed_by_user_id' => null,
            'completed_at' => null,
            'distribution_started_at' => null,
            'distributed_at' => null,
            'cancelled_at' => CarbonImmutable::now(),
            'failure_reason' => null,
        ]);
    }
}
