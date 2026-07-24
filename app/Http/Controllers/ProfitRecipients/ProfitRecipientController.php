<?php

namespace App\Http\Controllers\ProfitRecipients;

use App\Actions\ProfitRecipients\CreateProfitRecipient;
use App\Actions\ProfitRecipients\SetProfitRecipientActive;
use App\Actions\ProfitRecipients\UpdateProfitRecipient;
use App\Enums\ProfitRecipientType;
use App\Exceptions\ProfitRecipients\InvalidProfitRecipient;
use App\Exceptions\ProfitRecipients\InvestorLinkUnavailable;
use App\Exceptions\ProfitRecipients\ProfitRecipientCodeAlreadyExists;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitRecipients\IndexProfitRecipientRequest;
use App\Http\Requests\ProfitRecipients\StoreProfitRecipientRequest;
use App\Http\Requests\ProfitRecipients\UpdateProfitRecipientRequest;
use App\Http\Requests\ProfitRecipients\UpdateProfitRecipientStatusRequest;
use App\Models\Investor;
use App\Models\ProfitRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProfitRecipientController extends Controller
{
    private const INVESTOR_OPTION_LIMIT = 250;

    public function index(IndexProfitRecipientRequest $request): Response
    {
        $filters = $request->filters();
        $query = ProfitRecipient::query()
            ->select([
                'id',
                'code',
                'type',
                'investor_id',
                'display_name',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->with([
                'investor' => fn ($query) => $query->select([
                    'id',
                    'name',
                    'email',
                    'phone',
                    'is_active',
                ]),
            ])
            ->withCount(['allocations', 'distributions']);

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('code', 'ilike', $search)
                    ->orWhere('display_name', 'ilike', $search)
                    ->orWhereHas(
                        'investor',
                        fn (Builder $investorQuery) => $investorQuery
                            ->where('name', 'ilike', $search)
                    );
            });
        }

        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if ($filters['status'] !== 'all') {
            $query->where(
                'is_active',
                $filters['status'] === 'active'
            );
        }

        $paginator = $query
            ->orderBy('display_name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
        $firstPage = max(1, $paginator->currentPage() - 2);
        $lastPage = min(
            $paginator->lastPage(),
            $paginator->currentPage() + 2
        );

        return Inertia::render('profit-recipients/index', [
            'filters' => $filters,
            'recipients' => [
                'data' => $paginator->getCollection()
                    ->map(
                        fn (ProfitRecipient $recipient): array => [
                            ...$this->recipientData($recipient),
                            'investor' => $this->investorData(
                                $recipient->investor
                            ),
                            'usage_summary' => [
                                'allocation_count' => (int) $recipient
                                    ->getAttribute('allocations_count'),
                                'distribution_count' => (int) $recipient
                                    ->getAttribute('distributions_count'),
                            ],
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
        return Inertia::render('profit-recipients/create', [
            'type_options' => [
                [
                    'value' => ProfitRecipientType::Investor->value,
                    'label' => 'Investor',
                ],
                [
                    'value' => ProfitRecipientType::Company->value,
                    'label' => 'Company',
                ],
            ],
            'investors' => Investor::query()
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone',
                    'is_active',
                ])
                ->whereDoesntHave('profitRecipient')
                ->orderBy('name')
                ->orderBy('id')
                ->limit(self::INVESTOR_OPTION_LIMIT)
                ->get()
                ->map(
                    fn (Investor $investor): array => $this
                        ->investorData($investor)
                )
                ->all(),
            'investor_option_limit' => self::INVESTOR_OPTION_LIMIT,
            'defaults' => [
                'code' => '',
                'type' => ProfitRecipientType::Investor->value,
                'investor_id' => null,
                'display_name' => '',
                'is_active' => true,
            ],
        ]);
    }

    public function store(
        StoreProfitRecipientRequest $request,
        CreateProfitRecipient $action
    ): RedirectResponse {
        try {
            $recipient = $action->execute($request->domainData());
        } catch (ProfitRecipientCodeAlreadyExists $exception) {
            throw ValidationException::withMessages([
                'code' => $exception->getMessage(),
            ]);
        } catch (InvestorLinkUnavailable $exception) {
            throw ValidationException::withMessages([
                'investor_id' => $exception->getMessage(),
            ]);
        } catch (InvalidProfitRecipient $exception) {
            throw ValidationException::withMessages([
                'recipient' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Profit Recipient created.',
        ]);

        return to_route('profit-recipients.show', $recipient);
    }

    public function show(ProfitRecipient $profitRecipient): Response
    {
        $recipient = $this->recipientQuery($profitRecipient)
            ->with([
                'investor' => fn ($query) => $query->select([
                    'id',
                    'name',
                    'email',
                    'phone',
                    'is_active',
                ]),
            ])
            ->withCount([
                'allocations',
                'allocations as draft_allocations_count' => fn (
                    Builder $query
                ) => $query->whereHas(
                    'version',
                    fn (Builder $versionQuery) => $versionQuery
                        ->whereNull('published_at')
                ),
                'allocations as published_allocations_count' => fn (
                    Builder $query
                ) => $query->whereHas(
                    'version',
                    fn (Builder $versionQuery) => $versionQuery
                        ->whereNotNull('published_at')
                ),
                'distributions',
            ])
            ->firstOrFail();

        return Inertia::render('profit-recipients/show', [
            'recipient' => $this->recipientData($recipient),
            'investor' => $this->investorData($recipient->investor),
            'usage_summary' => [
                'allocation_count' => (int) $recipient->getAttribute(
                    'allocations_count'
                ),
                'draft_allocation_count' => (int) $recipient->getAttribute(
                    'draft_allocations_count'
                ),
                'published_allocation_count' => (int) $recipient
                    ->getAttribute('published_allocations_count'),
                'distribution_count' => (int) $recipient->getAttribute(
                    'distributions_count'
                ),
            ],
        ]);
    }

    public function edit(ProfitRecipient $profitRecipient): Response
    {
        $recipient = $this->recipientQuery($profitRecipient)
            ->with([
                'investor' => fn ($query) => $query->select([
                    'id',
                    'name',
                    'email',
                    'phone',
                    'is_active',
                ]),
            ])
            ->firstOrFail();

        return Inertia::render('profit-recipients/edit', [
            'recipient' => $this->recipientData($recipient),
            'investor' => $this->investorData($recipient->investor),
        ]);
    }

    public function update(
        UpdateProfitRecipientRequest $request,
        ProfitRecipient $profitRecipient,
        UpdateProfitRecipient $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $profitRecipient,
                $request->domainData()
            );
        } catch (ProfitRecipientCodeAlreadyExists $exception) {
            throw ValidationException::withMessages([
                'code' => $exception->getMessage(),
            ]);
        } catch (InvalidProfitRecipient $exception) {
            throw ValidationException::withMessages([
                'recipient' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Profit Recipient updated.',
        ]);

        return to_route('profit-recipients.show', $updated);
    }

    public function updateStatus(
        UpdateProfitRecipientStatusRequest $request,
        ProfitRecipient $profitRecipient,
        SetProfitRecipientActive $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $profitRecipient,
                $request->domainData()
            );
        } catch (InvalidProfitRecipient $exception) {
            throw ValidationException::withMessages([
                'recipient' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $updated->is_active
                ? 'Profit Recipient activated.'
                : 'Profit Recipient deactivated.',
        ]);

        return back();
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     type: string,
     *     display_name: string,
     *     is_active: bool,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function recipientData(
        ProfitRecipient $recipient
    ): array {
        return [
            'id' => $recipient->id,
            'code' => $recipient->code,
            'type' => $this->recipientType($recipient->type),
            'display_name' => $recipient->display_name,
            'is_active' => $recipient->is_active,
            'created_at' => $recipient->created_at?->toIso8601String(),
            'updated_at' => $recipient->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     is_active: bool
     * }|null
     */
    private function investorData(?Investor $investor): ?array
    {
        if ($investor === null) {
            return null;
        }

        return [
            'id' => $investor->id,
            'name' => $investor->name,
            'email' => $investor->email,
            'phone' => $investor->phone,
            'is_active' => $investor->is_active,
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
    private function recipientQuery(
        ProfitRecipient $recipient
    ): Builder {
        return ProfitRecipient::query()
            ->select([
                'id',
                'code',
                'type',
                'investor_id',
                'display_name',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->whereKey($recipient->getKey());
    }
}
