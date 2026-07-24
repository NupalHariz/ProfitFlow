<?php

namespace Tests\Feature\Actions\Products;

use App\Actions\ProductCategories\DeleteProductCategory;
use App\Actions\ProductCategories\SetProductCategoryActive;
use App\Actions\ProductCategories\UpdateProductCategory;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\SetProductActive;
use App\Actions\Products\UpdateProduct;
use App\Models\ProfitDistribution;
use App\Models\ProfitRecipient;
use App\Models\ProfitSchemeVersion;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;

class Stage3AHistoricalIntegrityTest extends ProductActionTestCase
{
    public function test_master_data_lifecycle_changes_preserve_distributed_history(): void
    {
        $originalCategory = $this->category([
            'name' => 'Original Category',
        ]);
        $replacementCategory = $this->category([
            'name' => 'Replacement Category',
        ]);
        $product = $this->createManagedProduct($originalCategory);
        $version = ProfitSchemeVersion::factory()
            ->published()
            ->create();
        $transaction = Transaction::factory()
            ->distributed()
            ->create([
                'total_revenue' => '300000.00',
                'total_production_cost' => '180000.00',
                'total_net_profit' => '120000.00',
            ]);
        $item = TransactionItem::factory()
            ->for($transaction)
            ->for($product)
            ->withAppliedRule($version)
            ->create([
                'quantity' => 2,
                'unit_selling_price' => '150000.00',
                'unit_production_cost' => '90000.00',
            ]);
        $recipient = ProfitRecipient::factory()->company()->create([
            'display_name' => 'Original Company Recipient',
        ]);
        $distribution = ProfitDistribution::factory()
            ->for($transaction)
            ->for($item, 'transactionItem')
            ->for($version, 'profitSchemeVersion')
            ->for($recipient, 'recipient')
            ->create([
                'recipient_name_snapshot' => 'Original Company Recipient',
                'percentage' => '100.0000',
                'amount' => '120000.00',
            ]);
        $transactionState = $this->storedRow('transactions', $transaction->id);
        $itemState = $this->storedRow('transaction_items', $item->id);
        $versionState = $this->storedRow(
            'profit_scheme_versions',
            $version->id
        );
        $distributionState = $this->storedRow(
            'profit_distributions',
            $distribution->id
        );

        app(UpdateProductCategory::class)->execute($originalCategory, [
            'name' => 'Renamed Category',
            'description' => 'Current master-data description.',
        ]);
        app(SetProductCategoryActive::class)->execute(
            $originalCategory,
            ['is_active' => false]
        );
        $updatedProduct = app(UpdateProduct::class)->execute($product, [
            'product_category_id' => $replacementCategory->id,
            'name' => 'Renamed Product',
            'sku' => 'RENAMED-SKU',
            'description' => 'Current master-data description.',
            'default_selling_price' => '1.00',
            'default_production_cost' => '0.00',
        ]);
        $inactiveProduct = app(SetProductActive::class)->execute(
            $updatedProduct,
            ['is_active' => false]
        );
        app(DeleteProduct::class)->execute($inactiveProduct);
        app(DeleteProductCategory::class)->execute($originalCategory);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSoftDeleted('product_categories', [
            'id' => $originalCategory->id,
        ]);
        $this->assertEquals(
            $transactionState,
            $this->storedRow('transactions', $transaction->id)
        );
        $this->assertEquals(
            $itemState,
            $this->storedRow('transaction_items', $item->id)
        );
        $this->assertEquals(
            $versionState,
            $this->storedRow('profit_scheme_versions', $version->id)
        );
        $this->assertEquals(
            $distributionState,
            $this->storedRow('profit_distributions', $distribution->id)
        );
    }

    private function storedRow(string $table, int $id): object
    {
        return DB::table($table)->where('id', $id)->firstOrFail();
    }
}
