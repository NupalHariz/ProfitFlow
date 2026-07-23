<?php

namespace Tests\Feature\Actions\ProductCategories;

use App\Actions\ProductCategories\CreateProductCategory;
use App\Exceptions\ProductCategories\InvalidProductCategory;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateProductCategoryTest extends ProductCategoryActionTestCase
{
    public function test_category_is_created_from_normalized_trusted_input(): void
    {
        $category = $this->createCategory([
            'name' => '  Home & Office  ',
            'description' => '  Everyday work products.  ',
            'is_active' => false,
        ]);

        $this->assertSame('Home & Office', $category->name);
        $this->assertSame('home-office', $category->slug);
        $this->assertSame(
            'Everyday work products.',
            $category->description
        );
        $this->assertFalse($category->is_active);
    }

    public function test_blank_optional_description_is_stored_as_null(): void
    {
        $category = $this->createCategory([
            'description' => '   ',
        ]);

        $this->assertNull($category->description);
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_name_is_rejected(mixed $name): void
    {
        $this->expectException(InvalidProductCategory::class);
        $this->expectExceptionMessage('name is required');

        $this->createCategory(['name' => $name]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidNames(): array
    {
        return [
            'missing type' => [null],
            'blank after trim' => ['   '],
            'more than 120 characters' => [str_repeat('a', 121)],
        ];
    }

    public function test_client_owned_or_unknown_fields_are_rejected(): void
    {
        $this->expectException(InvalidProductCategory::class);
        $this->expectExceptionMessage('Unsupported product category fields');

        app(CreateProductCategory::class)->execute([
            'name' => 'Client Slug',
            'description' => null,
            'is_active' => true,
            'slug' => 'client-controlled',
        ]);
    }

    public function test_empty_slug_output_uses_safe_category_base(): void
    {
        $category = $this->createCategory(['name' => '東京']);

        $this->assertSame('category', $category->slug);
    }

    public function test_repeated_names_use_deterministic_suffixes(): void
    {
        $first = $this->createCategory();
        $second = $this->createCategory();
        $third = $this->createCategory();

        $this->assertSame('home-office', $first->slug);
        $this->assertSame('home-office-2', $second->slug);
        $this->assertSame('home-office-3', $third->slug);
    }

    public function test_multiple_existing_collisions_choose_the_next_suffix(): void
    {
        ProductCategory::factory()->create(['slug' => 'home-office']);
        ProductCategory::factory()->create(['slug' => 'home-office-2']);
        ProductCategory::factory()->create(['slug' => 'home-office-3']);

        $category = $this->createCategory();

        $this->assertSame('home-office-4', $category->slug);
    }

    public function test_slug_unique_constraint_remains_a_database_safety_layer(): void
    {
        ProductCategory::factory()->create(['slug' => 'database-guard']);

        $this->expectException(QueryException::class);

        ProductCategory::factory()->create(['slug' => 'database-guard']);
    }

    public function test_unrelated_database_errors_are_not_swallowed_or_retried(): void
    {
        ProductCategory::creating(function (): void {
            DB::statement('select * from missing_category_test_table');
        });

        try {
            $this->expectException(QueryException::class);
            $this->expectExceptionMessage('missing_category_test_table');

            $this->createCategory();
        } finally {
            ProductCategory::flushEventListeners();
        }
    }
}
