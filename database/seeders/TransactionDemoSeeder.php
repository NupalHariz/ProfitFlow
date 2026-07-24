<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\DemoTransactionBuilder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TransactionDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(DemoTransactionBuilder $builder): void
    {
        DB::transaction(function () use ($builder): void {
            $builder->distributed(
                number: 'TRX-DEMO-001',
                customerEmail: 'purchasing@nusantara-retail.test',
                transactionDate: '2026-07-10',
                items: [
                    ['sku' => 'ELEC-LAPTOP-01', 'quantity' => 1],
                ],
                createdAt: $this->at('2026-07-10 09:00:00'),
                completedAt: $this->at('2026-07-10 09:05:00'),
                distributedAt: $this->at('2026-07-10 09:06:00')
            );
            $builder->distributed(
                number: 'TRX-DEMO-002',
                customerEmail: 'operations@mitra-digital.test',
                transactionDate: '2026-07-16',
                items: [
                    ['sku' => 'ELEC-LAPTOP-01', 'quantity' => 2],
                ],
                createdAt: $this->at('2026-07-16 09:00:00'),
                completedAt: $this->at('2026-07-16 09:05:00'),
                distributedAt: $this->at('2026-07-16 09:06:00')
            );
            $builder->distributed(
                number: 'TRX-DEMO-003',
                customerEmail: 'finance@solusi-kerja.test',
                transactionDate: '2026-07-17',
                items: [
                    ['sku' => 'OFFICE-DESK-01', 'quantity' => 1],
                ],
                createdAt: $this->at('2026-07-17 10:00:00'),
                completedAt: $this->at('2026-07-17 10:05:00'),
                distributedAt: $this->at('2026-07-17 10:06:00')
            );
            $builder->distributed(
                number: 'TRX-DEMO-004',
                customerEmail: 'admin@maju-bersama.test',
                transactionDate: '2026-07-18',
                items: [
                    ['sku' => 'ELEC-HEADPHONES-01', 'quantity' => 2],
                    [
                        'sku' => 'OFFICE-CHAIR-01',
                        'quantity' => 1,
                        'unit_selling_price' => '1700000.00',
                    ],
                ],
                createdAt: $this->at('2026-07-18 11:00:00'),
                completedAt: $this->at('2026-07-18 11:05:00'),
                distributedAt: $this->at('2026-07-18 11:06:00')
            );
            $builder->distributed(
                number: 'TRX-DEMO-005',
                customerEmail: 'purchasing@nusantara-retail.test',
                transactionDate: '2026-07-19',
                items: [
                    [
                        'sku' => 'LIFE-BOTTLE-01',
                        'quantity' => 2,
                        'unit_selling_price' => '200000.00',
                    ],
                    [
                        'sku' => 'LIFE-BACKPACK-01',
                        'quantity' => 1,
                        'unit_selling_price' => '500000.00',
                    ],
                ],
                createdAt: $this->at('2026-07-19 09:00:00'),
                completedAt: $this->at('2026-07-19 09:05:00'),
                distributedAt: $this->at('2026-07-19 09:06:00')
            );
            $builder->distributed(
                number: 'TRX-DEMO-006',
                customerEmail: 'operations@mitra-digital.test',
                transactionDate: '2026-07-20',
                items: [
                    ['sku' => 'LIFE-BACKPACK-01', 'quantity' => 2],
                ],
                createdAt: $this->at('2026-07-20 13:00:00'),
                completedAt: $this->at('2026-07-20 13:05:00'),
                distributedAt: $this->at('2026-07-20 13:06:00')
            );
            $builder->distributed(
                number: 'TRX-DEMO-007',
                customerEmail: 'finance@solusi-kerja.test',
                transactionDate: '2026-07-21',
                items: [
                    ['sku' => 'LIFE-BOTTLE-01', 'quantity' => 3],
                ],
                createdAt: $this->at('2026-07-21 14:00:00'),
                completedAt: $this->at('2026-07-21 14:05:00'),
                distributedAt: $this->at('2026-07-21 14:06:00')
            );
            $builder->draft(
                number: 'TRX-DEMO-008',
                customerEmail: 'admin@maju-bersama.test',
                transactionDate: '2026-07-22',
                items: [
                    ['sku' => 'OFFICE-DESK-01', 'quantity' => 1],
                ],
                createdAt: $this->at('2026-07-22 09:00:00')
            );
            $builder->draft(
                number: 'TRX-DEMO-009',
                customerEmail: 'purchasing@nusantara-retail.test',
                transactionDate: '2026-06-15',
                items: [
                    ['sku' => 'ELEC-LAPTOP-01', 'quantity' => 1],
                ],
                createdAt: $this->at('2026-07-22 14:00:00')
            );
            $builder->pending(
                number: 'TRX-DEMO-010',
                customerEmail: 'operations@mitra-digital.test',
                transactionDate: '2026-07-23',
                items: [
                    ['sku' => 'ELEC-HEADPHONES-01', 'quantity' => 1],
                ],
                createdAt: $this->at('2026-07-23 08:00:00'),
                completedAt: $this->at('2026-07-23 08:05:00')
            );
            $builder->failed(
                number: 'TRX-DEMO-011',
                customerEmail: 'finance@solusi-kerja.test',
                transactionDate: '2026-07-23',
                items: [
                    ['sku' => 'OFFICE-CHAIR-01', 'quantity' => 1],
                ],
                createdAt: $this->at('2026-07-23 10:00:00'),
                completedAt: $this->at('2026-07-23 10:05:00'),
                distributionStartedAt: $this->at('2026-07-23 10:06:00'),
                failedAt: $this->at('2026-07-23 10:07:00'),
                failureReason: 'Profit distribution could not be completed after the configured retries.'
            );
            $builder->cancelled(
                number: 'TRX-DEMO-012',
                customerEmail: 'admin@maju-bersama.test',
                transactionDate: '2026-07-24',
                items: [
                    ['sku' => 'LIFE-BOTTLE-01', 'quantity' => 1],
                ],
                createdAt: $this->at('2026-07-24 09:00:00'),
                cancelledAt: $this->at('2026-07-24 09:10:00')
            );
        });
    }

    private function at(string $timestamp): CarbonImmutable
    {
        return CarbonImmutable::parse($timestamp, 'UTC');
    }
}
