<?php

namespace App\Services\ProfitSharing;

final readonly class ProfitAllocationResult
{
    public function __construct(
        public int|string $recipientId,
        public string $percentage,
        public string $amount,
    ) {}

    /**
     * @return array{
     *     recipient_id: int|string,
     *     percentage: string,
     *     amount: string
     * }
     */
    public function toArray(): array
    {
        return [
            'recipient_id' => $this->recipientId,
            'percentage' => $this->percentage,
            'amount' => $this->amount,
        ];
    }
}
