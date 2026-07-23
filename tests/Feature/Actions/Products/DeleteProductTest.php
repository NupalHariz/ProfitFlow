<?php

namespace Tests\Feature\Actions\Products;

use App\Actions\Products\DeleteProduct;
use App\Enums\TransactionStatus;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;

class DeleteProductTest extends ProductActionTestCase
{
    public function test_referenced_product_is_soft_deleted_without_mutating_history(): void
    {
        $product = $this->createManagedProduct();
        $version = ProfitSchemeVersion::factory()
            ->forCategory($product->category)
            ->published()
            ->create();
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Distributed,
            'total_revenue' => '300000.00',
            'total_production_cost' => '180000.00',
            'total_net_profit' => '120000.00',
        ]);
        $item = TransactionItem::factory()
            ->for($transaction)
            ->for($product)
            ->for($version, 'appliedProfitSchemeVersion')
            ->create([
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'category_name_snapshot' => $product->category->name,
                'unit_selling_price' => '150000.00',
                'unit_production_cost' => '90000.00',
                'total_revenue' => '300000.00',
                'total_production_cost' => '180000.00',
                'net_profit' => '120000.00',
            ]);
        $recipient = ProfitRecipient::factory()->company()->create();
        $distribution = ProfitDistribution::factory()
            ->for($transaction)
            ->for($item, 'transactionItem')
            ->for($version, 'profitSchemeVersion')
            ->for($recipient, 'recipient')
            ->create([
                'recipient_name_snapshot' => $recipient->display_name,
                'percentage' => '100.0000',
                'amount' => '120000.00',
            ]);
        $transactionState = DB::table('transactions')
            ->where('id', $transaction->id)
            ->first();
        $itemState = DB::table('transaction_items')
            ->where('id', $item->id)
            ->first();
        $versionState = DB::table('profit_scheme_versions')
            ->where('id', $version->id)
            ->first();
        $distributionState = DB::table('profit_distributions')
            ->where('id', $distribution->id)
            ->first();
        $categoryState = DB::table('product_categories')
            ->where('id', $product->product_category_id)
            ->first();

        app(DeleteProduct::class)->execute($product);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertEquals(
            $transactionState,
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->first()
        );
        $this->assertEquals(
            $itemState,
            DB::table('transaction_items')
                ->where('id', $item->id)
                ->first()
        );
        $this->assertEquals(
            $versionState,
            DB::table('profit_scheme_versions')
                ->where('id', $version->id)
                ->first()
        );
        $this->assertEquals(
            $distributionState,
            DB::table('profit_distributions')
                ->where('id', $distribution->id)
                ->first()
        );
        $this->assertEquals(
            $categoryState,
            DB::table('product_categories')
                ->where('id', $product->product_category_id)
                ->first()
        );
    }

    public function test_delete_locks_product_row(): void
    {
        $product = $this->createManagedProduct();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(DeleteProduct::class)->execute($product);
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertTrue(collect($queries)->contains(
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "products"'
            ) && str_contains(strtolower($query['query']), 'for update')
        ));
    }
}
