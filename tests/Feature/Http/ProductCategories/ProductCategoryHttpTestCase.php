<?php

namespace Tests\Feature\Http\ProductCategories;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ProductCategoryHttpTestCase extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    protected function category(
        array $attributes = []
    ): ProductCategory {
        return ProductCategory::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function categoryPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Home & Office',
            'description' => 'Products for home and work spaces.',
            'is_active' => true,
        ], $overrides);
    }
}
