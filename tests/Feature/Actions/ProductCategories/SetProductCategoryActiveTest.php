<?php

namespace Tests\Feature\Actions\ProductCategories;

use App\Actions\ProductCategories\SetProductCategoryActive;
use App\Exceptions\ProductCategories\InvalidProductCategory;
use App\Models\Product;
use App\Models\TransactionItem;

class SetProductCategoryActiveTest extends ProductCategoryActionTestCase
{
    public function test_category_can_be_deactivated_and_reactivated(): void
    {
        $category = $this->createCategory();

        $inactive = app(SetProductCategoryActive::class)->execute(
            $category,
            ['is_active' => false]
        );
        $active = app(SetProductCategoryActive::class)->execute(
            $inactive,
            ['is_active' => true]
        );

        $this->assertFalse($inactive->is_active);
        $this->assertTrue($active->is_active);
    }

    public function test_deactivation_preserves_product_master_state_and_snapshots(): void
    {
        $category = $this->createCategory();
        $product = Product::factory()
            ->for($category, 'category')
            ->create(['is_active' => true]);
        $item = TransactionItem::factory()
            ->for($product)
            ->create();
        $categorySnapshot = $item->category_name_snapshot;

        app(SetProductCategoryActive::class)->execute(
            $category,
            ['is_active' => false]
        );

        $freshProduct = $product->fresh();
        $freshItem = $item->fresh();

        $this->assertNotNull($freshProduct);
        $this->assertTrue($freshProduct->is_active);
        $this->assertNotNull($freshItem);
        $this->assertSame(
            $categorySnapshot,
            $freshItem->category_name_snapshot
        );
    }

    public function test_status_action_rejects_unrelated_fields(): void
    {
        $category = $this->createCategory();

        $this->expectException(InvalidProductCategory::class);
        $this->expectExceptionMessage('status fields');

        app(SetProductCategoryActive::class)->execute($category, [
            'is_active' => false,
            'name' => 'Not allowed',
        ]);
    }
}
