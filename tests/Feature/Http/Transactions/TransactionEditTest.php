<?php

namespace Tests\Feature\Http\Transactions;

use App\Actions\Transactions\CreateTransaction;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

class TransactionEditTest extends TransactionHttpTestCase
{
    public function test_draft_edit_page_renders_with_stored_order_and_selling_prices(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $first = Product::factory()->create([
            'sku' => 'FIRST',
            'default_selling_price' => '100.00',
        ]);
        $second = Product::factory()->create([
            'sku' => 'SECOND',
            'default_selling_price' => '200.00',
        ]);
        $transaction = app(CreateTransaction::class)->execute(
            $this->payload($customer, items: [
                [
                    'product_id' => $second->id,
                    'quantity' => 3,
                    'unit_selling_price' => '175.50',
                ],
                [
                    'product_id' => $first->id,
                    'quantity' => 1,
                    'unit_selling_price' => null,
                ],
            ]),
            $user
        );

        $this->actingAs($user)
            ->get(route('transactions.edit', $transaction))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('transactions/edit')
                    ->where(
                        'transaction.transaction_number',
                        $transaction->transaction_number
                    )
                    ->has('transaction.items', 2)
                    ->where(
                        'transaction.items.0.product_id',
                        $second->id
                    )
                    ->where(
                        'transaction.items.0.unit_selling_price',
                        '175.50'
                    )
                    ->where(
                        'transaction.items.0.snapshot.product_sku_snapshot',
                        'SECOND'
                    )
                    ->where(
                        'transaction.items.1.product_id',
                        $first->id
                    )
                    ->where(
                        'transaction.items.1.unit_selling_price',
                        '100.00'
                    )
            );
    }

    public function test_valid_update_fully_replaces_draft_through_existing_action(): void
    {
        $user = $this->user();
        $originalCustomer = Customer::factory()->create();
        $replacementCustomer = Customer::factory()->create();
        $originalProduct = Product::factory()->create();
        $replacement = Product::factory()->create([
            'name' => 'Current Replacement Product',
            'sku' => 'REPLACEMENT',
            'default_selling_price' => '150.00',
            'default_production_cost' => '50.00',
        ]);
        $transaction = $this->draft(
            $user,
            $originalCustomer,
            $originalProduct
        );
        $number = $transaction->transaction_number;
        $creatorId = $transaction->created_by_user_id;

        $this->actingAs($user)
            ->patch(route('transactions.update', $transaction), [
                'customer_id' => (string) $replacementCustomer->id,
                'transaction_date' => '2026-08-01',
                'items' => [
                    [
                        'product_id' => (string) $replacement->id,
                        'quantity' => '2',
                        'unit_selling_price' => '125.00',
                    ],
                    [
                        'product_id' => (string) $replacement->id,
                        'quantity' => '1',
                        'unit_selling_price' => null,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('transactions.show', $transaction)
            );

        $fresh = Transaction::query()->findOrFail($transaction->id);
        $items = $fresh->items()->orderBy('id')->get();

        $this->assertSame($number, $fresh->transaction_number);
        $this->assertSame($creatorId, $fresh->created_by_user_id);
        $this->assertSame($replacementCustomer->id, $fresh->customer_id);
        $this->assertSame('2026-08-01', $fresh
            ->transaction_date
            ->toDateString());
        $this->assertCount(2, $items);
        $this->assertSame(
            ['125.00', '150.00'],
            $items->pluck('unit_selling_price')->all()
        );
        $this->assertSame(
            ['Current Replacement Product', 'Current Replacement Product'],
            $items->pluck('product_name_snapshot')->all()
        );
        $this->assertSame('400.00', $fresh->total_revenue);
        $this->assertSame('150.00', $fresh->total_production_cost);
        $this->assertSame('250.00', $fresh->total_net_profit);
        $this->assertSame(
            0,
            TransactionItem::query()
                ->where('transaction_id', $fresh->id)
                ->where('product_id', $originalProduct->id)
                ->count()
        );
    }

    public function test_failed_update_preserves_previous_complete_state(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $transaction = $this->draft($user, $customer, $product);
        $beforeHeader = $this->headerState($transaction);
        $beforeItems = $this->itemState($transaction);
        $inactive = Product::factory()->inactive()->create();

        $this->actingAs($user)
            ->from(route('transactions.edit', $transaction))
            ->patch(route('transactions.update', $transaction), [
                'customer_id' => $customer->id,
                'transaction_date' => '2027-01-01',
                'items' => [[
                    'product_id' => $inactive->id,
                    'quantity' => 1,
                    'unit_selling_price' => null,
                ]],
            ])
            ->assertRedirect(route('transactions.edit', $transaction))
            ->assertSessionHasErrors('transaction')
            ->assertSessionHasInput('transaction_date', '2027-01-01');

        $this->assertSame(
            $beforeHeader,
            $this->headerState($transaction)
        );
        $this->assertSame(
            $beforeItems,
            $this->itemState($transaction)
        );
    }

    public function test_non_draft_edit_route_is_not_found(): void
    {
        $user = $this->user();
        $transaction = $this->draft(
            $user,
            Customer::factory()->create()
        );
        $transaction->update([
            'status' => TransactionStatus::PendingDistribution,
        ]);

        $this->actingAs($user)
            ->get(route('transactions.edit', $transaction))
            ->assertNotFound();
    }

    public function test_non_draft_update_returns_domain_validation_error_and_preserves_state(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $transaction = $this->draft($user, $customer, $product);
        $transaction->update([
            'status' => TransactionStatus::Distributed,
        ]);
        $beforeHeader = $this->headerState($transaction);
        $beforeItems = $this->itemState($transaction);

        $this->actingAs($user)
            ->from(route('transactions.show', $transaction))
            ->patch(
                route('transactions.update', $transaction),
                $this->payload($customer, $product)
            )
            ->assertRedirect(route('transactions.show', $transaction))
            ->assertSessionHasErrors('transaction');

        $this->assertSame(
            $beforeHeader,
            $this->headerState($transaction)
        );
        $this->assertSame(
            $beforeItems,
            $this->itemState($transaction)
        );
    }

    public function test_unavailable_product_is_represented_by_stored_snapshot_and_not_selectable(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Retired Product',
            'sku' => 'RETIRED',
            'default_selling_price' => '100.00',
            'default_production_cost' => '60.00',
        ]);
        $transaction = $this->draft($user, $customer, $product);
        $product->delete();

        $this->actingAs($user)
            ->get(route('transactions.edit', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'transaction.items.0.product_id',
                        $product->id
                    )
                    ->where(
                        'transaction.items.0.current_product_available',
                        false
                    )
                    ->where(
                        'transaction.items.0.snapshot.product_name_snapshot',
                        'Retired Product'
                    )
                    ->where(
                        'transaction.items.0.snapshot.product_sku_snapshot',
                        'RETIRED'
                    )
                    ->where(
                        'transaction.items.0.snapshot.unit_selling_price',
                        '100.00'
                    )
                    ->where(
                        'products',
                        fn (mixed $products): bool => $products
                            instanceof Collection
                            && ! $products
                                ->pluck('id')
                                ->contains($product->id)
                    )
            );
    }

    public function test_update_rejects_protected_header_and_item_fields(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $transaction = $this->draft($user, $customer, $product);
        $payload = $this->payload($customer, $product);
        $payload['status'] = 'distributed';
        $payload['items'][0]['unit_production_cost'] = '0.01';

        $this->actingAs($user)
            ->patch(route('transactions.update', $transaction), $payload)
            ->assertSessionHasErrors([
                'status',
                'items.0.unit_production_cost',
            ]);

        $this->assertSame(
            TransactionStatus::Draft,
            Transaction::query()->findOrFail($transaction->id)->status
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function headerState(Transaction $transaction): array
    {
        $fresh = Transaction::query()->findOrFail($transaction->id);

        return Arr::only($fresh->getRawOriginal(), [
            'transaction_number',
            'customer_id',
            'transaction_date',
            'status',
            'total_revenue',
            'total_production_cost',
            'total_net_profit',
            'created_by_user_id',
            'completed_by_user_id',
            'completed_at',
            'distribution_started_at',
            'distributed_at',
            'cancelled_at',
            'failure_reason',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemState(Transaction $transaction): array
    {
        return TransactionItem::query()
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->map(
                fn (TransactionItem $item): array => $item
                    ->getRawOriginal()
            )
            ->all();
    }
}
