<?php

namespace Tests\Feature\Http\Products;

use App\Actions\Products\UpdateProduct;
use App\Actions\Transactions\CreateTransaction;
use App\Models\Customer;
use App\Models\TransactionItem;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

class ProductTransactionRegressionTest extends ProductHttpTestCase
{
    public function test_master_update_does_not_rewrite_existing_draft_until_explicit_resubmit(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = $this->product(attributes: [
            'name' => 'Original Product',
            'sku' => 'ORIGINAL',
            'default_selling_price' => '100.00',
            'default_production_cost' => '60.00',
        ]);
        $transaction = app(CreateTransaction::class)->execute([
            'customer_id' => $customer->id,
            'transaction_date' => '2026-07-23',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
            ]],
        ], $user);
        $item = $transaction->items->firstOrFail();
        $snapshot = $item->getAttributes();

        app(UpdateProduct::class)->execute($product, [
            'product_category_id' => $product->product_category_id,
            'name' => 'Current Product',
            'sku' => 'CURRENT',
            'description' => null,
            'default_selling_price' => '250.00',
            'default_production_cost' => '100.00',
        ]);

        $this->assertEquals($snapshot, $item->fresh()?->getAttributes());
        $this->actingAs($user)
            ->get(route('transactions.edit', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'transaction.items.0.snapshot.product_name_snapshot',
                        'Original Product'
                    )
                    ->where(
                        'transaction.items.0.snapshot.product_sku_snapshot',
                        'ORIGINAL'
                    )
                    ->where(
                        'transaction.items.0.snapshot.unit_selling_price',
                        '100.00'
                    )
            );
    }

    public function test_deleted_product_is_excluded_and_draft_resubmit_is_atomic(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $product = $this->product();
        $transaction = app(CreateTransaction::class)->execute([
            'customer_id' => $customer->id,
            'transaction_date' => '2026-07-23',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
            ]],
        ], $user);
        $beforeTransaction = $transaction->fresh()?->getAttributes();
        $beforeItems = TransactionItem::query()
            ->where('transaction_id', $transaction->id)
            ->get()
            ->map(fn (TransactionItem $item): array => $item->getAttributes())
            ->all();
        $product->delete();

        $this->actingAs($user)
            ->get(route('transactions.edit', $transaction))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'transaction.items.0.current_product_available',
                        false
                    )
                    ->where(
                        'products',
                        fn (mixed $products): bool => $products
                            instanceof Collection
                            && ! $products->pluck('id')->contains(
                                $product->id
                            )
                    )
            );
        $this->actingAs($user)
            ->patch(route('transactions.update', $transaction), [
                'customer_id' => $customer->id,
                'transaction_date' => '2026-08-01',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_selling_price' => null,
                ]],
            ])
            ->assertSessionHasErrors('transaction');

        $this->assertSame(
            $beforeTransaction,
            $transaction->fresh()?->getAttributes()
        );
        $this->assertSame(
            $beforeItems,
            TransactionItem::query()
                ->where('transaction_id', $transaction->id)
                ->get()
                ->map(
                    fn (TransactionItem $item): array => $item
                        ->getAttributes()
                )
                ->all()
        );
    }

    public function test_draft_can_be_resubmitted_with_available_replacement(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->create();
        $deleted = $this->product();
        $replacement = $this->product(attributes: [
            'name' => 'Replacement',
            'sku' => 'REPLACEMENT',
            'default_selling_price' => '200.00',
            'default_production_cost' => '75.00',
        ]);
        $transaction = app(CreateTransaction::class)->execute([
            'customer_id' => $customer->id,
            'transaction_date' => '2026-07-23',
            'items' => [[
                'product_id' => $deleted->id,
                'quantity' => 1,
            ]],
        ], $user);
        $deleted->delete();

        $this->actingAs($user)
            ->patch(route('transactions.update', $transaction), [
                'customer_id' => $customer->id,
                'transaction_date' => '2026-08-01',
                'items' => [[
                    'product_id' => $replacement->id,
                    'quantity' => 2,
                    'unit_selling_price' => null,
                ]],
            ])
            ->assertSessionHasNoErrors();
        $item = TransactionItem::query()
            ->where('transaction_id', $transaction->id)
            ->sole();

        $this->assertSame($replacement->id, $item->product_id);
        $this->assertSame('Replacement', $item->product_name_snapshot);
        $this->assertSame('REPLACEMENT', $item->product_sku_snapshot);
        $this->assertSame('200.00', $item->unit_selling_price);
        $this->assertSame('75.00', $item->unit_production_cost);
    }

    public function test_inactive_product_and_inactive_category_are_excluded_from_transaction_options(): void
    {
        $active = $this->product(attributes: [
            'name' => 'Available',
            'is_active' => true,
        ]);
        $inactiveProduct = $this->product(attributes: [
            'name' => 'Inactive Product',
            'is_active' => false,
        ]);
        $inactiveCategory = $this->category(['is_active' => false]);
        $effectivelyInactive = $this->product(
            $inactiveCategory,
            [
                'name' => 'Inactive Category Product',
                'is_active' => true,
            ]
        );

        $this->actingAs($this->user())
            ->get(route('transactions.create'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where(
                        'products',
                        fn (mixed $products): bool => $products
                            instanceof Collection
                            && $products->pluck('id')->all() === [
                                $active->id,
                            ]
                            && ! $products->pluck('id')->contains(
                                $inactiveProduct->id
                            )
                            && ! $products->pluck('id')->contains(
                                $effectivelyInactive->id
                            )
                    )
            );
    }
}
