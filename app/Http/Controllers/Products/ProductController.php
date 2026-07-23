<?php

namespace App\Http\Controllers\Products;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\SetProductActive;
use App\Actions\Products\UpdateProduct;
use App\Exceptions\Products\InvalidProduct;
use App\Exceptions\Products\ProductSkuAlreadyExists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\IndexProductRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Requests\Products\UpdateProductStatusRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class ProductController extends Controller
{
    private const CATEGORY_OPTION_LIMIT = 250;

    public function index(IndexProductRequest $request): Response
    {
        $filters = $request->filters();
        $query = Product::query()
            ->select([
                'id',
                'product_category_id',
                'name',
                'sku',
                'description',
                'default_selling_price',
                'default_production_cost',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->with([
                'category' => fn ($query) => $query
                    ->select(['id', 'name', 'is_active']),
            ]);

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('name', 'ilike', $search)
                    ->orWhere('sku', 'ilike', $search);
            });
        }

        if ($filters['category'] !== null) {
            $query->where(
                'product_category_id',
                $filters['category']
            );
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

        return Inertia::render('products/index', [
            'filters' => $filters,
            'category_options' => $this->filterCategoryOptions(
                $filters['category']
            ),
            'products' => [
                'data' => $paginator->getCollection()
                    ->map(
                        fn (Product $product): array => $this
                            ->productSummary($product)
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
        return Inertia::render('products/create', [
            'categories' => $this->formCategoryOptions(),
            'category_option_limit' => self::CATEGORY_OPTION_LIMIT,
        ]);
    }

    public function store(
        StoreProductRequest $request,
        CreateProduct $action
    ): RedirectResponse {
        try {
            $product = $action->execute($request->domainData());
        } catch (ProductSkuAlreadyExists $exception) {
            throw ValidationException::withMessages([
                'sku' => $exception->getMessage(),
            ]);
        } catch (InvalidProduct $exception) {
            throw ValidationException::withMessages([
                'product' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product created.',
        ]);

        return to_route('products.edit', $product);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('products/edit', [
            'product' => $this->productFormData($product),
            'categories' => $this->formCategoryOptions(
                $product->product_category_id
            ),
            'category_option_limit' => self::CATEGORY_OPTION_LIMIT,
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        UpdateProduct $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $product,
                $request->domainData()
            );
        } catch (ProductSkuAlreadyExists $exception) {
            throw ValidationException::withMessages([
                'sku' => $exception->getMessage(),
            ]);
        } catch (InvalidProduct $exception) {
            throw ValidationException::withMessages([
                'product' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product updated.',
        ]);

        return to_route('products.edit', $updated);
    }

    public function updateStatus(
        UpdateProductStatusRequest $request,
        Product $product,
        SetProductActive $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $product,
                $request->domainData()
            );
        } catch (InvalidProduct $exception) {
            throw ValidationException::withMessages([
                'product' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $updated->is_active
                ? 'Product activated.'
                : 'Product deactivated.',
        ]);

        return back();
    }

    public function destroy(
        Product $product,
        DeleteProduct $action
    ): RedirectResponse {
        try {
            $action->execute($product);
        } catch (InvalidProduct $exception) {
            throw ValidationException::withMessages([
                'product' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product deleted.',
        ]);

        return to_route('products.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function productSummary(Product $product): array
    {
        $category = $product->category;

        if (! $category instanceof ProductCategory) {
            throw new LogicException(
                'Product category must be loaded for the index.'
            );
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'default_selling_price' => $this->storedDecimal(
                $product,
                'default_selling_price'
            ),
            'default_production_cost' => $this->storedDecimal(
                $product,
                'default_production_cost'
            ),
            'is_active' => $product->is_active,
            'updated_at' => $product->updated_at?->toIso8601String(),
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => $category->is_active,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productFormData(Product $product): array
    {
        $category = ProductCategory::query()
            ->select(['id', 'name', 'is_active'])
            ->whereKey($product->product_category_id)
            ->first();

        if ($category === null) {
            throw new LogicException(
                'Product current category is unavailable. '
                .'Resolve the inconsistent master data before editing.'
            );
        }

        return [
            'id' => $product->id,
            'product_category_id' => $product->product_category_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'default_selling_price' => $this->storedDecimal(
                $product,
                'default_selling_price'
            ),
            'default_production_cost' => $this->storedDecimal(
                $product,
                'default_production_cost'
            ),
            'is_active' => $product->is_active,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => $category->is_active,
            ],
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     is_active: bool,
     *     is_current: bool
     * }>
     */
    private function formCategoryOptions(
        ?int $currentId = null
    ): array {
        /** @var Collection<int, ProductCategory> $current */
        $current = ProductCategory::query()
            ->select(['id', 'name', 'is_active'])
            ->when(
                $currentId !== null,
                fn ($query) => $query->whereKey($currentId),
                fn ($query) => $query->whereRaw('false')
            )
            ->get();

        if ($currentId !== null && $current->isEmpty()) {
            throw new LogicException(
                'Product current category is unavailable. '
                .'Resolve the inconsistent master data before editing.'
            );
        }

        $remainingLimit = self::CATEGORY_OPTION_LIMIT
            - $current->count();
        /** @var Collection<int, ProductCategory> $available */
        $available = ProductCategory::query()
            ->select(['id', 'name', 'is_active'])
            ->where('is_active', true)
            ->when(
                $currentId !== null,
                fn ($query) => $query->whereKeyNot($currentId)
            )
            ->orderBy('name')
            ->orderBy('id')
            ->limit($remainingLimit)
            ->get();

        return array_values(
            $current
                ->concat($available)
                ->unique('id')
                ->sortBy([
                    ['name', 'asc'],
                    ['id', 'asc'],
                ])
                ->map(
                    fn (ProductCategory $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'is_active' => $category->is_active,
                        'is_current' => $currentId === $category->id,
                    ]
                )
                ->values()
                ->all()
        );
    }

    /**
     * @return list<array{id: int, name: string, is_active: bool}>
     */
    private function filterCategoryOptions(?int $selectedId): array
    {
        /** @var Collection<int, ProductCategory> $selected */
        $selected = ProductCategory::query()
            ->select(['id', 'name', 'is_active'])
            ->when(
                $selectedId !== null,
                fn ($query) => $query->whereKey($selectedId),
                fn ($query) => $query->whereRaw('false')
            )
            ->get();
        $remainingLimit = self::CATEGORY_OPTION_LIMIT
            - $selected->count();
        /** @var Collection<int, ProductCategory> $available */
        $available = ProductCategory::query()
            ->select(['id', 'name', 'is_active'])
            ->when(
                $selectedId !== null,
                fn ($query) => $query->whereKeyNot($selectedId)
            )
            ->orderBy('name')
            ->orderBy('id')
            ->limit($remainingLimit)
            ->get();

        return array_values(
            $selected
                ->concat($available)
                ->unique('id')
                ->sortBy([
                    ['name', 'asc'],
                    ['id', 'asc'],
                ])
                ->map(
                    fn (ProductCategory $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'is_active' => $category->is_active,
                    ]
                )
                ->values()
                ->all()
        );
    }

    private function storedDecimal(Model $model, string $field): string
    {
        $value = $model->getRawOriginal($field);

        if (! is_string($value)) {
            throw new LogicException(
                "Stored decimal field [{$field}] must be a string."
            );
        }

        return $value;
    }
}
