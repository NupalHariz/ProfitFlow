<?php

namespace App\Jobs;

use App\Services\ProfitSharing\ProfitDistributionFailureRecorder;
use App\Services\ProfitSharing\ProfitDistributor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class DistributeTransactionProfit implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $transactionId
    ) {
        $this->onQueue('critical');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ProfitDistributor $distributor): void
    {
        $distributor->distribute($this->transactionId);
    }

    public function failed(Throwable $exception): void
    {
        app(ProfitDistributionFailureRecorder::class)->record(
            $this->transactionId,
            $exception
        );
    }
}
