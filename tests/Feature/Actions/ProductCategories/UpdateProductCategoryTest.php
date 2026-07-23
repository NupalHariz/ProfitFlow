<?php

namespace Tests\Feature\Actions\ProductCategories;

use App\Actions\ProductCategories\UpdateProductCategory;
use App\Exceptions\ProductCategories\InvalidProductCategory;
use App\Models\Product;
use App\Models\ProfitSchemeVersion;
use App\Models\TransactionItem;

class UpdateProductCategoryTest extends ProductCategoryActionTestCase
{
    public function test_name_and_description_update_without_regenerating_slug_or_status(): void
    {
        $category = $this->createCategory();

        $updated = app(UpdateProductCategory::class)->execute(
            $category,
            [
                'name' => '  Workspace Essentials  ',
                'description' => '  Updated description.  ',
            ]
        );

        $this->assertSame('Workspace Essentials', $updated->name);
        $this->assertSame('Updated description.', $updated->description);
        $this->assertSame('home-office', $updated->slug);
        $this->assertTrue($updated->is_active);
    }

    public function test_update_does_not_mutate_products_rules_or_transaction_snapshots(): void
    {
        $category = $this->createCategory();
        $product = Product::factory()
            ->for($category, 'category')
            ->create([
                'name' => 'Standing Desk',
                'is_active' => true,
            ]);
        $version = ProfitSchemeVersion::factory()
            ->forCategory($category)
            ->create();
        $item = TransactionItem::factory()
            ->for($product)
            ->create();
        $snapshot = [
            'product_name_snapshot' => $item->product_name_snapshot,
            'category_name_snapshot' => $item->category_name_snapshot,
            'unit_selling_price' => $item->unit_selling_price,
            'unit_production_cost' => $item->unit_production_cost,
        ];

        app(UpdateProductCategory::class)->execute($category, [
            'name' => 'Renamed Category',
            'description' => null,
        ]);

        $freshProduct = $product->fresh();
        $freshVersion = $version->fresh();
        $freshItem = $item->fresh();

        $this->assertNotNull($freshProduct);
        $this->assertSame($category->id, $freshProduct->product_category_id);
        $this->assertSame('Standing Desk', $freshProduct->name);
        $this->assertTrue($freshProduct->is_active);
        $this->assertNotNull($freshVersion);
        $this->assertSame(
            $category->id,
            $freshVersion->product_category_id
        );
        $this->assertNotNull($freshItem);
        $this->assertSame(
            $snapshot['product_name_snapshot'],
            $freshItem->product_name_snapshot
        );
        $this->assertSame(
            $snapshot['category_name_snapshot'],
            $freshItem->category_name_snapshot
        );
        $this->assertSame(
            $snapshot['unit_selling_price'],
            $freshItem->unit_selling_price
        );
        $this->assertSame(
            $snapshot['unit_production_cost'],
            $freshItem->unit_production_cost
        );
    }

    public function test_status_and_slug_are_not_supported_by_general_update(): void
    {
        $category = $this->createCategory();

        try {
            app(UpdateProductCategory::class)->execute($category, [
                'name' => 'Attempted Change',
                'description' => null,
                'slug' => 'changed',
                'is_active' => false,
            ]);
            $this->fail('Expected unsupported fields to be rejected.');
        } catch (InvalidProductCategory $exception) {
            $this->assertStringContainsString(
                'Unsupported product category fields',
                $exception->getMessage()
            );
        }

        $fresh = $category->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame('Home & Office', $fresh->name);
        $this->assertSame('home-office', $fresh->slug);
        $this->assertTrue($fresh->is_active);
    }
}
