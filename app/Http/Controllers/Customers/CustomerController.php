<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Enums\TransactionStatus;
use App\Exceptions\Customers\InvalidCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\IndexCustomerRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class CustomerController extends Controller
{
    public function index(IndexCustomerRequest $request): Response
    {
        $filters = $request->filters();
        $query = Customer::query()
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'address',
                'created_at',
                'updated_at',
            ])
            ->withCount('transactions');

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhere('phone', 'ilike', $search);
            });
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

        return Inertia::render('customers/index', [
            'filters' => $filters,
            'customers' => [
                'data' => $paginator->getCollection()
                    ->map(
                        fn (Customer $customer): array => [
                            'id' => $customer->id,
                            'name' => $customer->name,
                            'email' => $customer->email,
                            'phone' => $customer->phone,
                            'address' => $customer->address,
                            'transactions_count' => (int) $customer
                                ->getAttribute('transactions_count'),
                            'created_at' => $customer->created_at
                                ?->toIso8601String(),
                            'updated_at' => $customer->updated_at
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
        return Inertia::render('customers/create');
    }

    public function store(
        StoreCustomerRequest $request,
        CreateCustomer $action
    ): RedirectResponse {
        try {
            $customer = $action->execute($request->domainData());
        } catch (InvalidCustomer $exception) {
            throw ValidationException::withMessages([
                'customer' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Customer created.',
        ]);

        return to_route('customers.show', $customer);
    }

    public function show(Customer $customer): Response
    {
        $customer->loadCount([
            'transactions',
            'transactions as draft_transactions_count' => fn (
                Builder $query
            ): Builder => $query->where(
                'status',
                TransactionStatus::Draft
            ),
            'transactions as distributed_transactions_count' => fn (
                Builder $query
            ): Builder => $query->where(
                'status',
                TransactionStatus::Distributed
            ),
        ]);
        $recentTransactions = $customer->transactions()
            ->select([
                'id',
                'customer_id',
                'transaction_number',
                'transaction_date',
                'status',
                'total_revenue',
                'total_net_profit',
            ])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return Inertia::render('customers/show', [
            'customer' => $this->customerDetail($customer),
            'transaction_summary' => [
                'total_count' => (int) $customer
                    ->getAttribute('transactions_count'),
                'draft_count' => (int) $customer
                    ->getAttribute('draft_transactions_count'),
                'distributed_count' => (int) $customer
                    ->getAttribute('distributed_transactions_count'),
            ],
            'recent_transactions' => $recentTransactions
                ->map(
                    fn (Transaction $transaction): array => [
                        'id' => $transaction->id,
                        'transaction_number' => $transaction
                            ->transaction_number,
                        'transaction_date' => $this->dateString(
                            $transaction->getAttribute(
                                'transaction_date'
                            )
                        ),
                        'status' => $this->status($transaction)->value,
                        'total_revenue' => $this->storedDecimal(
                            $transaction,
                            'total_revenue'
                        ),
                        'total_net_profit' => $this->storedDecimal(
                            $transaction,
                            'total_net_profit'
                        ),
                    ]
                )
                ->values()
                ->all(),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/edit', [
            'customer' => $this->customerDetail($customer),
        ]);
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        UpdateCustomer $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $customer,
                $request->domainData()
            );
        } catch (InvalidCustomer $exception) {
            throw ValidationException::withMessages([
                'customer' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Customer updated.',
        ]);

        return to_route('customers.show', $updated);
    }

    public function destroy(
        Customer $customer,
        DeleteCustomer $action
    ): RedirectResponse {
        try {
            $action->execute($customer);
        } catch (InvalidCustomer $exception) {
            throw ValidationException::withMessages([
                'customer' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Customer deleted.',
        ]);

        return to_route('customers.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function customerDetail(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }

    private function dateString(mixed $value): string
    {
        if (! $value instanceof CarbonInterface) {
            throw new LogicException(
                'Transaction date must be a date.'
            );
        }

        return $value->toDateString();
    }

    private function status(Transaction $transaction): TransactionStatus
    {
        $status = $transaction->getAttribute('status');

        if (! $status instanceof TransactionStatus) {
            throw new LogicException(
                'Transaction status must be a TransactionStatus.'
            );
        }

        return $status;
    }

    private function storedDecimal(Model $model, string $field): string
    {
        $value = $model->getRawOriginal($field);

        if (! is_string($value)) {
            throw new LogicException(
                "Stored {$field} must be a decimal string."
            );
        }

        return $value;
    }
}
