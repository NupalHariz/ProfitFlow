<?php

namespace Tests\Feature\Actions\ProductCategories;

use App\Actions\ProductCategories\DeleteProductCategory;
use App\Exceptions\ProductCategories\ProductCategoryNotDeletable;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitDistribution;
use App\Models\ProfitSchemeVersion;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class DeleteProductCategoryTest extends ProductCategoryActionTestCase
{
    public function test_unreferenced_category_is_soft_deleted_not_hard_deleted(): void
    {
        $category = $this->createCategory();

        app(DeleteProductCategory::class)->execute($category);

        $this->assertSoftDeleted('product_categories', [
            'id' => $category->id,
        ]);
        $this->assertNotNull(
            ProductCategory::withTrashed()->find($category->id)
        );
    }

    public function test_non_deleted_product_blocks_deletion_and_leaves_category_unchanged(): void
    {
        $category = $this->createCategory();
        Product::factory()->for($category, 'category')->create();

        try {
            app(DeleteProductCategory::class)->execute($category);
            $this->fail('Expected product reference to block deletion.');
        } catch (ProductCategoryNotDeletable $exception) {
            $this->assertStringContainsString(
                'products still belong',
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                'Deactivate',
                $exception->getMessage()
            );
        }

        $this->assertNull($category->fresh()?->deleted_at);
    }

    public function test_soft_deleted_product_does_not_block_category_deletion(): void
    {
        $category = $this->createCategory();
        $product = Product::factory()
            ->for($category, 'category')
            ->create();
        $product->delete();

        app(DeleteProductCategory::class)->execute($category);

        $this->assertSoftDeleted('product_categories', [
            'id' => $category->id,
        ]);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    #[DataProvider('blockingVersionStates')]
    public function test_any_profit_scheme_version_reference_blocks_deletion(
        bool $published,
        bool $active
    ): void {
        $category = $this->createCategory();
        $factory = ProfitSchemeVersion::factory()
            ->forCategory($category)
            ->state(['is_active' => $active]);

        if ($published) {
            $factory = $factory->published();
        }

        $version = $factory->create();

        try {
            app(DeleteProductCategory::class)->execute($category);
            $this->fail('Expected scheme reference to block deletion.');
        } catch (ProductCategoryNotDeletable $exception) {
            $this->assertStringContainsString(
                'profit-sharing versions reference',
                $exception->getMessage()
            );
        }

        $this->assertNull($category->fresh()?->deleted_at);
        $this->assertNotNull($version->fresh());
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function blockingVersionStates(): array
    {
        return [
            'active draft' => [false, true],
            'published version' => [true, true],
            'inactive draft' => [false, false],
        ];
    }

    public function test_deletion_preserves_transaction_snapshots_distributions_and_soft_deleted_products(): void
    {
        $category = $this->createCategory();
        $product = Product::factory()
            ->for($category, 'category')
            ->create();
        $version = ProfitSchemeVersion::factory()
            ->published()
            ->create();
        $item = TransactionItem::factory()
            ->for($product)
            ->withAppliedRule($version)
            ->create();
        $distribution = ProfitDistribution::factory()->create([
            'transaction_id' => $item->transaction_id,
            'transaction_item_id' => $item->id,
            'profit_scheme_version_id' => $version->id,
        ]);
        $categorySnapshot = $item->category_name_snapshot;
        $amount = $distribution->amount;
        $product->delete();

        app(DeleteProductCategory::class)->execute($category);

        $freshItem = $item->fresh();
        $freshDistribution = $distribution->fresh();

        $this->assertNotNull($freshItem);
        $this->assertSame(
            $category->id,
            $freshItem->product_category_id
        );
        $this->assertSame(
            $categorySnapshot,
            $freshItem->category_name_snapshot
        );
        $this->assertNotNull($freshDistribution);
        $this->assertSame($amount, $freshDistribution->amount);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertNotNull($version->fresh());
    }

    public function test_category_parent_row_is_locked_before_delete_checks(): void
    {
        $category = $this->createCategory();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(DeleteProductCategory::class)->execute($category);
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $locks = array_filter(
            $queries,
            static fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'from "product_categories"'
            ) && str_contains(strtolower($query['query']), 'for update')
        );

        $this->assertCount(1, $locks);
    }
}
