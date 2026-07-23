<?php

namespace Tests\Feature\Actions\ProductCategories;

use App\Actions\ProductCategories\CreateProductCategory;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ProductCategoryActionTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCategory(
        array $overrides = []
    ): ProductCategory {
        return app(CreateProductCategory::class)->execute(
            array_replace([
                'name' => 'Home & Office',
                'description' => 'Products for home and work spaces.',
                'is_active' => true,
            ], $overrides)
        );
    }
}
