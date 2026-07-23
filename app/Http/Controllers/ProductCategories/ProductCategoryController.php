<?php

namespace App\Http\Controllers\ProductCategories;

use App\Actions\ProductCategories\CreateProductCategory;
use App\Actions\ProductCategories\DeleteProductCategory;
use App\Actions\ProductCategories\SetProductCategoryActive;
use App\Actions\ProductCategories\UpdateProductCategory;
use App\Exceptions\ProductCategories\InvalidProductCategory;
use App\Exceptions\ProductCategories\ProductCategoryNotDeletable;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCategories\IndexProductCategoryRequest;
use App\Http\Requests\ProductCategories\StoreProductCategoryRequest;
use App\Http\Requests\ProductCategories\UpdateProductCategoryRequest;
use App\Http\Requests\ProductCategories\UpdateProductCategoryStatusRequest;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProductCategoryController extends Controller
{
    public function index(
        IndexProductCategoryRequest $request
    ): Response {
        $filters = $request->filters();
        $query = ProductCategory::query()
            ->select([
                'id',
                'name',
                'slug',
                'description',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->withCount('products');

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('name', 'ilike', $search)
                    ->orWhere('description', 'ilike', $search);
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where(
                'is_active',
                $filters['status'] === 'active'
            );
        }

        $paginator = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
        $firstPage = max(1, $paginator->currentPage() - 2);
        $lastPage = min(
            $paginator->lastPage(),
            $paginator->currentPage() + 2
        );

        return Inertia::render('product-categories/index', [
            'filters' => $filters,
            'categories' => [
                'data' => $paginator->getCollection()
                    ->map(
                        fn (ProductCategory $category): array => [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                            'description' => $category->description,
                            'is_active' => $category->is_active,
                            'products_count' => (int) $category
                                ->getAttribute('products_count'),
                            'created_at' => $category->created_at
                                ?->toIso8601String(),
                            'updated_at' => $category->updated_at
                                ?->toIso8601String(),
                        ]
                    )
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'previous' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                    'pages' => collect(range($firstPage, $lastPage))
                        ->map(
                            fn (int $page): array => [
                                'page' => $page,
                                'url' => $paginator->url($page),
                                'active' => $page
                                    === $paginator->currentPage(),
                            ]
                        )
                        ->all(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('product-categories/create');
    }

    public function store(
        StoreProductCategoryRequest $request,
        CreateProductCategory $action
    ): RedirectResponse {
        try {
            $category = $action->execute($request->domainData());
        } catch (InvalidProductCategory $exception) {
            throw ValidationException::withMessages([
                'category' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product category created.',
        ]);

        return to_route('product-categories.edit', $category);
    }

    public function edit(ProductCategory $productCategory): Response
    {
        return Inertia::render('product-categories/edit', [
            'category' => [
                'id' => $productCategory->id,
                'name' => $productCategory->name,
                'slug' => $productCategory->slug,
                'description' => $productCategory->description,
                'is_active' => $productCategory->is_active,
            ],
        ]);
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $productCategory,
        UpdateProductCategory $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $productCategory,
                $request->domainData()
            );
        } catch (InvalidProductCategory $exception) {
            throw ValidationException::withMessages([
                'category' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product category updated.',
        ]);

        return to_route('product-categories.edit', $updated);
    }

    public function updateStatus(
        UpdateProductCategoryStatusRequest $request,
        ProductCategory $productCategory,
        SetProductCategoryActive $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $productCategory,
                $request->domainData()
            );
        } catch (InvalidProductCategory $exception) {
            throw ValidationException::withMessages([
                'category' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $updated->is_active
                ? 'Product category activated.'
                : 'Product category deactivated.',
        ]);

        return back();
    }

    public function destroy(
        ProductCategory $productCategory,
        DeleteProductCategory $action
    ): RedirectResponse {
        try {
            $action->execute($productCategory);
        } catch (
            InvalidProductCategory|ProductCategoryNotDeletable $exception
        ) {
            throw ValidationException::withMessages([
                'category' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product category deleted.',
        ]);

        return to_route('product-categories.index');
    }
}
