<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Investors\CreateInvestor;
use App\Actions\Investors\DeleteInvestor;
use App\Actions\Investors\SetInvestorActive;
use App\Actions\Investors\UpdateInvestor;
use App\Enums\ProfitRecipientType;
use App\Exceptions\Investors\InvalidInvestor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investors\IndexInvestorRequest;
use App\Http\Requests\Investors\StoreInvestorRequest;
use App\Http\Requests\Investors\UpdateInvestorRequest;
use App\Http\Requests\Investors\UpdateInvestorStatusRequest;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class InvestorController extends Controller
{
    public function index(IndexInvestorRequest $request): Response
    {
        $filters = $request->filters();
        $query = Investor::query()
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->with([
                'profitRecipient' => fn ($query) => $query->select([
                    'id',
                    'investor_id',
                    'code',
                    'type',
                    'display_name',
                    'is_active',
                ]),
            ]);

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';
            $phoneSearch = IndonesianPhoneNumber::normalizeSearchPrefix(
                $filters['search']
            );

            $query->where(function (Builder $query) use (
                $phoneSearch,
                $search
            ): void {
                $query
                    ->where('name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhere('phone', 'ilike', $search);

                if ($phoneSearch !== null) {
                    $query->orWhere(
                        'phone',
                        'ilike',
                        '%'.$phoneSearch.'%'
                    );
                }
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

        return Inertia::render('investors/index', [
            'filters' => $filters,
            'investors' => [
                'data' => $paginator->getCollection()
                    ->map(
                        fn (Investor $investor): array => [
                            ...$this->investorData($investor),
                            'linked_recipient' => $this
                                ->linkedRecipientData(
                                    $investor->profitRecipient
                                ),
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
        return Inertia::render('investors/create');
    }

    public function store(
        StoreInvestorRequest $request,
        CreateInvestor $action
    ): RedirectResponse {
        try {
            $investor = $action->execute($request->domainData());
        } catch (InvalidInvestor $exception) {
            throw ValidationException::withMessages([
                'investor' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Investor created.',
        ]);

        return to_route('investors.show', $investor);
    }

    public function show(Investor $investor): Response
    {
        $recipient = $this->linkedRecipientQuery($investor)
            ->withCount(['allocations', 'distributions'])
            ->first();

        return Inertia::render('investors/show', [
            'investor' => $this->investorData($investor),
            'linked_recipient' => $this->linkedRecipientData($recipient),
            'allocation_summary' => [
                'allocation_count' => $recipient === null
                    ? 0
                    : (int) $recipient->getAttribute(
                        'allocations_count'
                    ),
                'distribution_count' => $recipient === null
                    ? 0
                    : (int) $recipient->getAttribute(
                        'distributions_count'
                    ),
            ],
        ]);
    }

    public function edit(Investor $investor): Response
    {
        return Inertia::render('investors/edit', [
            'investor' => $this->investorData($investor),
            'linked_recipient' => $this->linkedRecipientData(
                $this->linkedRecipientQuery($investor)->first()
            ),
        ]);
    }

    public function update(
        UpdateInvestorRequest $request,
        Investor $investor,
        UpdateInvestor $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $investor,
                $request->domainData()
            );
        } catch (InvalidInvestor $exception) {
            throw ValidationException::withMessages([
                'investor' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Investor updated.',
        ]);

        return to_route('investors.show', $updated);
    }

    public function updateStatus(
        UpdateInvestorStatusRequest $request,
        Investor $investor,
        SetInvestorActive $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $investor,
                $request->domainData()
            );
        } catch (InvalidInvestor $exception) {
            throw ValidationException::withMessages([
                'investor' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $updated->is_active
                ? 'Investor activated.'
                : 'Investor deactivated.',
        ]);

        return back();
    }

    public function destroy(
        Investor $investor,
        DeleteInvestor $action
    ): RedirectResponse {
        try {
            $action->execute($investor);
        } catch (InvalidInvestor $exception) {
            throw ValidationException::withMessages([
                'investor' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Investor deleted.',
        ]);

        return to_route('investors.index');
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     is_active: bool,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function investorData(Investor $investor): array
    {
        return [
            'id' => $investor->id,
            'name' => $investor->name,
            'email' => $investor->email,
            'phone' => $investor->phone,
            'is_active' => $investor->is_active,
            'created_at' => $investor->created_at?->toIso8601String(),
            'updated_at' => $investor->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     type: string,
     *     display_name: string,
     *     is_active: bool
     * }|null
     */
    private function linkedRecipientData(
        ?ProfitRecipient $recipient
    ): ?array {
        if ($recipient === null) {
            return null;
        }

        return [
            'id' => $recipient->id,
            'code' => $recipient->code,
            'type' => $this->recipientType($recipient->type),
            'display_name' => $recipient->display_name,
            'is_active' => $recipient->is_active,
        ];
    }

    private function recipientType(
        ProfitRecipientType|string $type
    ): string {
        return $type instanceof ProfitRecipientType
            ? $type->value
            : $type;
    }

    /**
     * @return Builder<ProfitRecipient>
     */
    private function linkedRecipientQuery(
        Investor $investor
    ): Builder {
        return ProfitRecipient::query()
            ->select([
                'id',
                'investor_id',
                'code',
                'type',
                'display_name',
                'is_active',
            ])
            ->where('investor_id', $investor->id);
    }
}
