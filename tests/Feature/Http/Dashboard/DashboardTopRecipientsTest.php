<?php

namespace Tests\Feature\Http\Dashboard;

use App\Enums\TransactionStatus;
use App\Queries\Dashboard\DashboardOverviewQuery;

class DashboardTopRecipientsTest extends DashboardTestCase
{
    public function test_top_recipients_group_exact_ledger_amounts_by_stable_identity(): void
    {
        $investorRecipient = $this->createInvestorRecipient(
            attributes: ['display_name' => 'Alpha Investor']
        );
        $companyRecipient = $this->createCompanyRecipient([
            'display_name' => 'Cubiconia Company',
        ]);
        $version = $this->createSchemeVersion(
            attributes: ['published_at' => now()]
        );
        $firstTransaction = $this->createTransaction(attributes: [
            'status' => TransactionStatus::Distributed,
        ]);
        $secondTransaction = $this->createTransaction(attributes: [
            'status' => TransactionStatus::Distributed,
        ]);
        $this->additionalDistribution(
            $firstTransaction,
            $investorRecipient,
            '100.10',
            $version
        );
        $this->additionalDistribution(
            $firstTransaction,
            $investorRecipient,
            '0.20',
            $version
        );
        $this->additionalDistribution(
            $secondTransaction,
            $investorRecipient,
            '50.00',
            $version
        );
        $this->additionalDistribution(
            $secondTransaction,
            $companyRecipient,
            '200.30',
            $version
        );

        $recipients = app(DashboardOverviewQuery::class)
            ->get()['topRecipients'];

        $this->assertSame($companyRecipient->id, $recipients[0]['id']);
        $this->assertSame('company', $recipients[0]['type']);
        $this->assertSame('200.30', $recipients[0]['total_amount']);
        $this->assertSame(
            $investorRecipient->id,
            $recipients[1]['id']
        );
        $this->assertSame('investor', $recipients[1]['type']);
        $this->assertSame('150.30', $recipients[1]['total_amount']);
        $this->assertSame(
            2,
            $recipients[1]['contributing_transaction_count']
        );
    }

    public function test_top_recipient_ranking_is_bounded_and_deterministic(): void
    {
        $version = $this->createSchemeVersion(
            attributes: ['published_at' => now()]
        );
        $recipients = [];

        foreach ([
            'Zulu',
            'Alpha',
            'Bravo',
            'Charlie',
            'Delta',
            'Echo',
        ] as $name) {
            $recipient = $this->createCompanyRecipient([
                'display_name' => $name,
            ]);
            $transaction = $this->createTransaction(attributes: [
                'status' => TransactionStatus::Distributed,
            ]);
            $this->additionalDistribution(
                $transaction,
                $recipient,
                '10.00',
                $version
            );
            $recipients[$name] = $recipient;
        }

        $ranking = app(DashboardOverviewQuery::class)
            ->get()['topRecipients'];

        $this->assertCount(5, $ranking);
        $this->assertSame([
            'Alpha',
            'Bravo',
            'Charlie',
            'Delta',
            'Echo',
        ], array_column($ranking, 'display_name'));
        $this->assertNotContains(
            $recipients['Zulu']->id,
            array_column($ranking, 'id')
        );
    }

    public function test_recipient_current_name_is_displayed_without_changing_historical_snapshot(): void
    {
        $recipient = $this->createInvestorRecipient(
            attributes: ['display_name' => 'Original Recipient']
        );
        $transaction = $this->createTransaction(attributes: [
            'status' => TransactionStatus::Distributed,
        ]);
        $distribution = $this->distribution(
            $transaction,
            $recipient,
            '25.00'
        );
        $historicalSnapshot = $distribution
            ->getRawOriginal('recipient_name_snapshot');

        $recipient->update(['display_name' => 'Current Recipient']);

        $ranking = app(DashboardOverviewQuery::class)
            ->get()['topRecipients'];

        $this->assertSame(
            'Current Recipient',
            $ranking[0]['display_name']
        );
        $this->assertSame(
            $historicalSnapshot,
            $distribution->refresh()
                ->getRawOriginal('recipient_name_snapshot')
        );
        $this->assertSame('Original Recipient', $historicalSnapshot);
    }

    public function test_no_ledger_rows_returns_empty_top_recipients(): void
    {
        $this->createCompanyRecipient();

        $this->assertSame(
            [],
            app(DashboardOverviewQuery::class)->get()['topRecipients']
        );
    }
}
