<?php

namespace App\Http\Controllers\Transactions;

use App\Actions\Transactions\CompleteTransaction;
use App\Actions\Transactions\CreateTransaction;
use App\Actions\Transactions\RetryProfitDistribution;
use App\Actions\Transactions\UpdateDraftTransaction;
use App\Enums\TransactionStatus;
use App\Exceptions\Transactions\InvalidTransactionCompletion;
use App\Exceptions\Transactions\InvalidTransactionDraft;
use App\Exceptions\Transactions\MissingTransactionItemCategory;
use App\Exceptions\Transactions\ProfitDistributionRetryNotAllowed;
use App\Exceptions\Transactions\ProfitRuleNotFoundForTransactionItem;
use App\Exceptions\Transactions\TransactionNotCompletable;
use App\Exceptions\Transactions\TransactionNotEditable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\IndexTransactionRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProfitDistribution;
use App\Models\ProfitScheme;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class TransactionController extends Controller
{
    private const LOOKUP_LIMIT = 250;

    public function index(IndexTransactionRequest $request): Response
    {
        $filters = $request->filters();
        $query = Transaction::query()
            ->select([
                'id',
                'transaction_number',
                'customer_id',
                'transaction_date',
                'status',
                'total_revenue',
                'total_production_cost',
                'total_net_profit',
                'created_at',
            ])
            ->with([
                'customer' => fn ($query) => $query
                    ->withTrashed()
                    ->select(['id', 'name']),
            ])
            ->withCount('items');

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';

            $query->where(
                function (Builder $query) use ($search): void {
                    $query
                        ->where(
                            'transaction_number',
                            'ilike',
                            $search
                        )
                        ->orWhereIn(
                            'customer_id',
                            Customer::withTrashed()
                                ->select('id')
                                ->where('name', 'ilike', $search)
                        );
                }
            );
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['date_from'] !== null) {
            $query->whereDate(
                'transaction_date',
                '>=',
                $filters['date_from']
            );
        }

        if ($filters['date_until'] !== null) {
            $query->whereDate(
                'transaction_date',
                '<=',
                $filters['date_until']
            );
        }

        if ($filters['customer_id'] !== null) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $paginator = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
        $firstPage = max(1, $paginator->currentPage() - 2);
        $lastPage = min(
            $paginator->lastPage(),
            $paginator->currentPage() + 2
        );

        return Inertia::render('transactions/index', [
            'filters' => $filters,
            'customer_options' => $this->customerLookups(
                $filters['customer_id']
            ),
            'transactions' => [
                'data' => $paginator->getCollection()
                    ->map(
                        fn (Transaction $transaction): array => $this
                            ->transactionSummary($transaction)
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
        return Inertia::render('transactions/create', [
            'customers' => $this->customerLookups(),
            'products' => $this->productLookups(),
            'lookup_limit' => self::LOOKUP_LIMIT,
        ]);
    }

    public function store(
        StoreTransactionRequest $request,
        CreateTransaction $action
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        try {
            $transaction = $action->execute(
                $request->domainData(),
                $user
            );
        } catch (InvalidTransactionDraft $exception) {
            throw ValidationException::withMessages([
                'transaction' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft transaction created.',
        ]);

        return to_route('transactions.show', $transaction);
    }

    public function show(Transaction $transaction): Response
    {
        $transaction->load([
            'customer' => fn ($query) => $query
                ->withTrashed()
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone',
                    'address',
                    'deleted_at',
                ]),
            'creator:id,name,email',
            'completer:id,name,email',
            'items' => fn ($query) => $query
                ->select([
                    'id',
                    'transaction_id',
                    'product_id',
                    'product_category_id',
                    'applied_profit_scheme_version_id',
                    'product_name_snapshot',
                    'product_sku_snapshot',
                    'category_name_snapshot',
                    'quantity',
                    'unit_selling_price',
                    'unit_production_cost',
                    'total_revenue',
                    'total_production_cost',
                    'net_profit',
                    'rule_resolved_at',
                ])
                ->withSum(
                    'distributions as distributed_total',
                    'amount'
                )
                ->orderBy('id'),
            'items.appliedProfitSchemeVersion' => fn ($query) => $query
                ->select([
                    'id',
                    'profit_scheme_id',
                    'version',
                    'published_at',
                ]),
            'items.appliedProfitSchemeVersion.scheme' => fn (
                $query
            ) => $query->select(['id', 'name']),
            'items.distributions' => fn ($query) => $query
                ->select([
                    'id',
                    'transaction_item_id',
                    'profit_recipient_id',
                    'recipient_name_snapshot',
                    'percentage',
                    'amount',
                    'created_at',
                ])
                ->orderBy('id'),
        ]);

        return Inertia::render('transactions/show', [
            'transaction' => $this->transactionDetail($transaction),
            'recipientSummary' => fn (): array => $this
                ->recipientSummary($transaction),
            'distributedTotal' => fn (): string => $this
                ->distributedTotal($transaction),
        ]);
    }

    public function edit(Transaction $transaction): Response
    {
        abort_unless(
            $this->status($transaction) === TransactionStatus::Draft,
            404
        );

        $transaction->load([
            'items' => fn ($query) => $query
                ->select([
                    'id',
                    'transaction_id',
                    'product_id',
                    'product_category_id',
                    'product_name_snapshot',
                    'product_sku_snapshot',
                    'category_name_snapshot',
                    'quantity',
                    'unit_selling_price',
                    'unit_production_cost',
                    'total_revenue',
                    'total_production_cost',
                    'net_profit',
                ])
                ->orderBy('id'),
        ]);
        $selectedProductIds = array_values(
            $transaction->items
                ->pluck('product_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all()
        );
        $products = $this->productLookups($selectedProductIds);
        $availableProductIds = array_column($products, 'id');
        $customers = $this->customerLookups($transaction->customer_id);
        $availableCustomerIds = array_column($customers, 'id');

        return Inertia::render('transactions/edit', [
            'transaction' => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'customer_id' => $transaction->customer_id,
                'transaction_date' => $this->dateString(
                    $transaction->getAttribute('transaction_date')
                ),
                'current_customer_available' => in_array(
                    $transaction->customer_id,
                    $availableCustomerIds,
                    true
                ),
                'items' => $transaction->items
                    ->map(
                        fn (TransactionItem $item): array => [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                            'unit_selling_price' => $this
                                ->storedDecimal(
                                    $item,
                                    'unit_selling_price'
                                ),
                            'current_product_available' => $item
                                ->product_id !== null
                                && in_array(
                                    $item->product_id,
                                    $availableProductIds,
                                    true
                                ),
                            'snapshot' => $this->itemSnapshot($item),
                        ]
                    )
                    ->values()
                    ->all(),
            ],
            'customers' => $customers,
            'products' => $products,
            'lookup_limit' => self::LOOKUP_LIMIT,
        ]);
    }

    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
        UpdateDraftTransaction $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $transaction,
                $request->domainData()
            );
        } catch (
            InvalidTransactionDraft|TransactionNotEditable $exception
        ) {
            throw ValidationException::withMessages([
                'transaction' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draft transaction updated.',
        ]);

        return to_route('transactions.show', $updated);
    }

    public function complete(
        Request $request,
        Transaction $transaction,
        CompleteTransaction $action
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        try {
            $completed = $action->execute($transaction, $user);
        } catch (
            InvalidTransactionCompletion
            |MissingTransactionItemCategory
            |ProfitRuleNotFoundForTransactionItem
            |TransactionNotCompletable $exception
        ) {
            throw ValidationException::withMessages([
                'transaction' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Transaction completed and queued for profit distribution.',
        ]);

        return to_route('transactions.show', $completed);
    }

    public function retryDistribution(
        Transaction $transaction,
        RetryProfitDistribution $action
    ): RedirectResponse {
        $status = $this->status($transaction);

        try {
            $result = $action->execute($transaction);
        } catch (ProfitDistributionRetryNotAllowed $exception) {
            throw ValidationException::withMessages([
                'transaction' => $exception->getMessage(),
            ]);
        }

        $message = match ($status) {
            TransactionStatus::DistributionFailed => 'Profit distribution has been queued for retry.',
            TransactionStatus::PendingDistribution => 'Profit distribution has been requeued.',
            TransactionStatus::Distributed => 'Profit distribution is already complete.',
            default => 'Profit distribution request accepted.',
        };

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return to_route('transactions.show', $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionSummary(Transaction $transaction): array
    {
        $customer = $transaction->customer;

        if (! $customer instanceof Customer) {
            throw new LogicException(
                'Transaction customer must be loaded.'
            );
        }

        return [
            'id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'transaction_date' => $this->dateString(
                $transaction->getAttribute('transaction_date')
            ),
            'status' => $this->status($transaction)->value,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
            'total_revenue' => $this->storedDecimal(
                $transaction,
                'total_revenue'
            ),
            'total_production_cost' => $this->storedDecimal(
                $transaction,
                'total_production_cost'
            ),
            'total_net_profit' => $this->storedDecimal(
                $transaction,
                'total_net_profit'
            ),
            'item_count' => (int) $transaction->getAttribute(
                'items_count'
            ),
            'created_at' => $this->dateTimeString(
                $transaction->getAttribute('created_at')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionDetail(Transaction $transaction): array
    {
        $customer = $transaction->customer;
        $creator = $transaction->creator;
        $completer = $transaction->completer;
        $status = $this->status($transaction);

        if (! $customer instanceof Customer
            || ! $creator instanceof User) {
            throw new LogicException(
                'Transaction detail relationships must be loaded.'
            );
        }

        return [
            'id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'transaction_date' => $this->dateString(
                $transaction->getAttribute('transaction_date')
            ),
            'status' => $status->value,
            'can_edit' => $status === TransactionStatus::Draft,
            'can_complete' => $status === TransactionStatus::Draft,
            'can_retry_distribution' => $status
                === TransactionStatus::DistributionFailed,
            'can_redrive_distribution' => $status
                === TransactionStatus::PendingDistribution,
            'total_revenue' => $this->storedDecimal(
                $transaction,
                'total_revenue'
            ),
            'total_production_cost' => $this->storedDecimal(
                $transaction,
                'total_production_cost'
            ),
            'total_net_profit' => $this->storedDecimal(
                $transaction,
                'total_net_profit'
            ),
            'created_at' => $this->dateTimeString(
                $transaction->getAttribute('created_at')
            ),
            'completed_at' => $this->nullableDateTimeString(
                $transaction->getAttribute('completed_at')
            ),
            'distribution_started_at' => $this
                ->nullableDateTimeString(
                    $transaction->getAttribute(
                        'distribution_started_at'
                    )
                ),
            'distributed_at' => $this->nullableDateTimeString(
                $transaction->getAttribute('distributed_at')
            ),
            'cancelled_at' => $this->nullableDateTimeString(
                $transaction->getAttribute('cancelled_at')
            ),
            'failure_reason' => $transaction->failure_reason,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'is_deleted' => $customer->trashed(),
            ],
            'creator' => [
                'id' => $creator->id,
                'name' => $creator->name,
                'email' => $creator->email,
            ],
            'completer' => $completer === null
                ? null
                : [
                    'id' => $completer->id,
                    'name' => $completer->name,
                    'email' => $completer->email,
                ],
            'items' => $transaction->items
                ->map(
                    fn (TransactionItem $item): array => $this
                        ->transactionItemDetail($item)
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionItemDetail(
        TransactionItem $item
    ): array {
        $version = $item->appliedProfitSchemeVersion;
        $appliedRule = null;

        if ($version !== null) {
            if (! $version->scheme instanceof ProfitScheme) {
                throw new LogicException(
                    'Frozen profit-scheme relationship must be loaded.'
                );
            }

            $appliedRule = [
                'profit_scheme_version_id' => $version->id,
                'version' => $version->version,
                'published_at' => $this->nullableDateTimeString(
                    $version->getAttribute('published_at')
                ),
                'scheme' => [
                    'id' => $version->scheme->id,
                    'name' => $version->scheme->name,
                ],
            ];
        }

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_category_id' => $item->product_category_id,
            ...$this->itemSnapshot($item),
            'applied_profit_scheme_version_id' => $item
                ->applied_profit_scheme_version_id,
            'applied_rule' => $appliedRule,
            'rule_resolved_at' => $this->nullableDateTimeString(
                $item->getAttribute('rule_resolved_at')
            ),
            'distributed_total' => $this->aggregateDecimal(
                $item->getAttribute('distributed_total')
            ),
            'distributions' => $item->distributions
                ->map(
                    fn (ProfitDistribution $distribution): array => [
                        'id' => $distribution->id,
                        'profit_recipient_id' => $distribution
                            ->profit_recipient_id,
                        'recipient_name_snapshot' => $distribution
                            ->recipient_name_snapshot,
                        'percentage' => $this->storedDecimal(
                            $distribution,
                            'percentage'
                        ),
                        'amount' => $this->storedDecimal(
                            $distribution,
                            'amount'
                        ),
                        'created_at' => $this->dateTimeString(
                            $distribution->getAttribute('created_at')
                        ),
                    ]
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{
     *     profit_recipient_id: int,
     *     recipient_name_snapshot: string,
     *     total_amount: string,
     *     contributing_item_count: int
     * }>
     */
    private function recipientSummary(
        Transaction $transaction
    ): array {
        if ($this->status($transaction)
            !== TransactionStatus::Distributed) {
            return [];
        }

        return array_values(DB::table('profit_distributions')
            ->select([
                'profit_recipient_id',
                'recipient_name_snapshot',
            ])
            ->selectRaw(
                'SUM(amount)::numeric(18, 2) AS total_amount'
            )
            ->selectRaw(
                'COUNT(DISTINCT transaction_item_id) AS contributing_item_count'
            )
            ->where('transaction_id', $transaction->id)
            ->groupBy([
                'profit_recipient_id',
                'recipient_name_snapshot',
            ])
            ->orderByDesc('total_amount')
            ->orderBy('recipient_name_snapshot')
            ->orderBy('profit_recipient_id')
            ->get()
            ->map(
                function (object $row): array {
                    if (! is_int($row->profit_recipient_id)
                        || ! is_string($row->recipient_name_snapshot)
                        || ! is_string($row->total_amount)) {
                        throw new LogicException(
                            'Recipient summary returned invalid database values.'
                        );
                    }

                    return [
                        'profit_recipient_id' => $row
                            ->profit_recipient_id,
                        'recipient_name_snapshot' => $row
                            ->recipient_name_snapshot,
                        'total_amount' => $row->total_amount,
                        'contributing_item_count' => (int) $row
                            ->contributing_item_count,
                    ];
                }
            )
            ->values()
            ->all());
    }

    private function distributedTotal(
        Transaction $transaction
    ): string {
        if ($this->status($transaction)
            !== TransactionStatus::Distributed) {
            return '0.00';
        }

        $total = DB::table('profit_distributions')
            ->where('transaction_id', $transaction->id)
            ->selectRaw(
                'COALESCE(SUM(amount), 0)::numeric(18, 2) AS total'
            )
            ->value('total');

        return $this->aggregateDecimal($total);
    }

    private function aggregateDecimal(mixed $value): string
    {
        if ($value === null) {
            return '0.00';
        }

        if (! is_string($value)) {
            throw new LogicException(
                'Aggregated decimal value must be a string.'
            );
        }

        return $value;
    }

    /**
     * @return array{
     *     product_name_snapshot: string,
     *     product_sku_snapshot: string,
     *     category_name_snapshot: string|null,
     *     quantity: int,
     *     unit_selling_price: string,
     *     unit_production_cost: string,
     *     total_revenue: string,
     *     total_production_cost: string,
     *     net_profit: string
     * }
     */
    private function itemSnapshot(TransactionItem $item): array
    {
        return [
            'product_name_snapshot' => $item
                ->product_name_snapshot,
            'product_sku_snapshot' => $item->product_sku_snapshot,
            'category_name_snapshot' => $item
                ->category_name_snapshot,
            'quantity' => $item->quantity,
            'unit_selling_price' => $this->storedDecimal(
                $item,
                'unit_selling_price'
            ),
            'unit_production_cost' => $this->storedDecimal(
                $item,
                'unit_production_cost'
            ),
            'total_revenue' => $this->storedDecimal(
                $item,
                'total_revenue'
            ),
            'total_production_cost' => $this->storedDecimal(
                $item,
                'total_production_cost'
            ),
            'net_profit' => $this->storedDecimal(
                $item,
                'net_profit'
            ),
        ];
    }

    /**
     * The technical-test dataset is bounded. Replace this list with
     * server-side search when customer volume materially grows.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     email: string|null,
     *     phone: string|null
     * }>
     */
    private function customerLookups(?int $selectedId = null): array
    {
        /** @var Collection<int, Customer> $selected */
        $selected = Customer::query()
            ->when(
                $selectedId !== null,
                fn ($query) => $query->whereKey($selectedId),
                fn ($query) => $query->whereRaw('false')
            )
            ->get(['id', 'name', 'email', 'phone']);
        $remainingLimit = self::LOOKUP_LIMIT - $selected->count();
        /** @var Collection<int, Customer> $available */
        $available = Customer::query()
            ->when(
                $selectedId !== null,
                fn ($query) => $query->whereKeyNot($selectedId)
            )
            ->select(['id', 'name', 'email', 'phone'])
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
                    fn (Customer $customer): array => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                    ]
                )
                ->values()
                ->all()
        );
    }

    /**
     * The technical-test dataset is bounded. Replace this list with
     * server-side search when product volume materially grows.
     *
     * @param  list<int>  $selectedIds
     * @return list<array{
     *     id: int,
     *     name: string,
     *     sku: string,
     *     category: array{id: int, name: string},
     *     default_selling_price: string,
     *     default_production_cost: string
     * }>
     */
    private function productLookups(array $selectedIds = []): array
    {
        $selectedIds = array_slice(
            array_values(array_unique($selectedIds)),
            0,
            self::LOOKUP_LIMIT
        );
        /** @var Collection<int, Product> $selected */
        $selected = $this->activeProductQuery()
            ->when(
                $selectedIds !== [],
                fn ($query) => $query->whereIn('id', $selectedIds),
                fn ($query) => $query->whereRaw('false')
            )
            ->get();
        $remainingLimit = self::LOOKUP_LIMIT - $selected->count();
        /** @var Collection<int, Product> $available */
        $available = $this->activeProductQuery()
            ->when(
                $selectedIds !== [],
                fn ($query) => $query->whereNotIn('id', $selectedIds)
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
                    fn (Product $product): array => $this
                        ->productLookupData($product)
                )
                ->values()
                ->all()
        );
    }

    /**
     * @return Builder<Product>
     */
    private function activeProductQuery(): Builder
    {
        return Product::query()
            ->select([
                'id',
                'product_category_id',
                'name',
                'sku',
                'default_selling_price',
                'default_production_cost',
            ])
            ->where('is_active', true)
            ->whereHas(
                'category',
                fn (Builder $query) => $query->where(
                    'is_active',
                    true
                )
            )
            ->with([
                'category' => fn ($query) => $query
                    ->select(['id', 'name']),
            ]);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     sku: string,
     *     category: array{id: int, name: string},
     *     default_selling_price: string,
     *     default_production_cost: string
     * }
     */
    private function productLookupData(Product $product): array
    {
        $category = $product->category;

        if (! $category instanceof ProductCategory) {
            throw new LogicException(
                'Active product category must be loaded.'
            );
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'default_selling_price' => $this->storedDecimal(
                $product,
                'default_selling_price'
            ),
            'default_production_cost' => $this->storedDecimal(
                $product,
                'default_production_cost'
            ),
        ];
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

    private function status(Transaction $transaction): TransactionStatus
    {
        $status = $transaction->getAttribute('status');

        if (! $status instanceof TransactionStatus) {
            throw new LogicException(
                'Transaction status must use TransactionStatus.'
            );
        }

        return $status;
    }

    private function dateString(mixed $value): string
    {
        if (! $value instanceof CarbonInterface) {
            throw new LogicException(
                'Transaction date must be a Carbon date.'
            );
        }

        return $value->toDateString();
    }

    private function dateTimeString(mixed $value): string
    {
        if (! $value instanceof CarbonInterface) {
            throw new LogicException(
                'Transaction timestamp must be a Carbon date.'
            );
        }

        return $value->toIso8601String();
    }

    private function nullableDateTimeString(mixed $value): ?string
    {
        return $value === null
            ? null
            : $this->dateTimeString($value);
    }
}
