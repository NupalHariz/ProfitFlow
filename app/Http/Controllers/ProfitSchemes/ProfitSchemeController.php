<?php

namespace App\Http\Controllers\ProfitSchemes;

use App\Actions\ProfitSchemes\CreateProfitScheme;
use App\Enums\ProfitRecipientType;
use App\Exceptions\ProfitSchemes\InvalidProfitScheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitSchemes\IndexProfitSchemeRequest;
use App\Http\Requests\ProfitSchemes\StoreProfitSchemeRequest;
use App\Models\ProfitRecipient;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\User;
use App\Services\ProfitSharing\FixedDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class ProfitSchemeController extends Controller
{
    public function index(IndexProfitSchemeRequest $request): Response
    {
        $filters = $request->filters();
        $query = ProfitScheme::query()
            ->select([
                'id',
                'name',
                'code',
                'description',
                'is_active',
                'created_at',
            ])
            ->withCount('versions')
            ->withMax('versions', 'version');

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('name', 'ilike', $search)
                    ->orWhere('code', 'ilike', $search);
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
            ->paginate(10)
            ->withQueryString();
        $firstPage = max(1, $paginator->currentPage() - 2);
        $lastPage = min(
            $paginator->lastPage(),
            $paginator->currentPage() + 2
        );

        return Inertia::render('profit-schemes/index', [
            'filters' => $filters,
            'schemes' => [
                'data' => $paginator->getCollection()
                    ->map(
                        fn (ProfitScheme $scheme): array => [
                            'id' => $scheme->id,
                            'name' => $scheme->name,
                            'code' => $scheme->code,
                            'description_summary' => $scheme->description === null
                                ? null
                                : Str::limit($scheme->description, 120),
                            'is_active' => $scheme->is_active,
                            'version_count' => (int) $scheme->getAttribute(
                                'versions_count'
                            ),
                            'maximum_version_number' => $scheme->getAttribute(
                                'versions_max_version'
                            ) === null
                                ? null
                                : (int) $scheme->getAttribute(
                                    'versions_max_version'
                                ),
                            'created_at' => $scheme->created_at
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
                    'pages' => collect(
                        range($firstPage, $lastPage)
                    )->map(
                        fn (int $page): array => [
                            'page' => $page,
                            'url' => $paginator->url($page),
                            'active' => $page
                                === $paginator->currentPage(),
                        ]
                    )->all(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('profit-schemes/create');
    }

    public function store(
        StoreProfitSchemeRequest $request,
        CreateProfitScheme $action
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        try {
            $scheme = $action->execute($request->domainData(), $user);
        } catch (InvalidProfitScheme $exception) {
            throw ValidationException::withMessages([
                'scheme' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Profit scheme created.',
        ]);

        return to_route('profit-schemes.show', $scheme);
    }

    public function show(ProfitScheme $scheme): Response
    {
        $scheme->load([
            'creator:id,name',
            'versions' => fn ($query) => $query
                ->select([
                    'id',
                    'profit_scheme_id',
                    'version',
                    'product_category_id',
                    'minimum_profit',
                    'maximum_profit',
                    'effective_from',
                    'effective_until',
                    'priority',
                    'is_active',
                    'published_at',
                    'created_by_user_id',
                    'created_at',
                ])
                ->withCount('appliedTransactionItems')
                ->orderByDesc('version'),
            'versions.category' => fn ($query) => $query
                ->withTrashed()
                ->select(['id', 'name', 'is_active', 'deleted_at']),
            'versions.creator:id,name',
            'versions.allocations' => fn ($query) => $query
                ->select([
                    'id',
                    'profit_scheme_version_id',
                    'profit_recipient_id',
                    'percentage',
                ])
                ->orderBy('id'),
            'versions.allocations.recipient:id,code,display_name,type,is_active',
        ]);
        $allocationRecipientIds = $scheme->versions
            ->flatMap(
                fn (ProfitSchemeVersion $version) => $version
                    ->allocations
                    ->pluck('profit_recipient_id')
            )
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $availableRecipientLookup = $allocationRecipientIds === []
            ? []
            : array_fill_keys(
                ProfitRecipient::query()
                    ->availableForAllocation()
                    ->whereIn('id', $allocationRecipientIds)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
                true
            );

        return Inertia::render('profit-schemes/show', [
            'scheme' => [
                'id' => $scheme->id,
                'name' => $scheme->name,
                'code' => $scheme->code,
                'description' => $scheme->description,
                'is_active' => $scheme->is_active,
                'created_at' => $scheme->created_at?->toIso8601String(),
                'creator' => [
                    'id' => $scheme->creator->id,
                    'name' => $scheme->creator->name,
                ],
                'versions' => $scheme->versions
                    ->map(
                        fn (
                            ProfitSchemeVersion $version
                        ): array => $this->versionData(
                            $version,
                            $availableRecipientLookup
                        )
                    )
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * @param  array<int, true>  $availableRecipientLookup
     * @return array<string, mixed>
     */
    private function versionData(
        ProfitSchemeVersion $version,
        array $availableRecipientLookup
    ): array {
        $published = $version->published_at !== null;
        $historicallyReferenced = (int) $version->getAttribute(
            'applied_transaction_items_count'
        ) > 0;
        $allocationTotalUnits = $version->allocations->sum(
            fn (ProfitSchemeAllocation $allocation): int => FixedDecimal::percentageToUnits(
                $this->allocationPercentage($allocation)
            )
        );
        $allRecipientsAvailable = $version->allocations->every(
            fn (ProfitSchemeAllocation $allocation): bool => isset(
                $availableRecipientLookup[$allocation->profit_recipient_id]
            )
        );

        return [
            'id' => $version->id,
            'version' => $version->version,
            'product_category_id' => $version->product_category_id,
            'category' => $version->category === null
                ? null
                : [
                    'id' => $version->category->id,
                    'name' => $version->category->name,
                    'is_active' => $version->category->is_active,
                    'is_deleted' => $version->category->trashed(),
                ],
            'minimum_profit' => $version->minimum_profit,
            'maximum_profit' => $version->maximum_profit,
            'effective_from' => $this->dateString(
                $version->getAttribute('effective_from')
            ),
            'effective_until' => $this->nullableDateString(
                $version->getAttribute('effective_until')
            ),
            'priority' => $version->priority,
            'is_active' => $version->is_active,
            'published_at' => $this->nullableDateTimeString(
                $version->getAttribute('published_at')
            ),
            'created_at' => $version->created_at?->toIso8601String(),
            'creator' => [
                'id' => $version->creator->id,
                'name' => $version->creator->name,
            ],
            'lifecycle_status' => $published ? 'published' : 'draft',
            'is_published' => $published,
            'is_historically_referenced' => $historicallyReferenced,
            'is_immutable' => $published || $historicallyReferenced,
            'can_edit' => ! $published && ! $historicallyReferenced,
            'allocation_total' => FixedDecimal::formatPercentage(
                $allocationTotalUnits
            ),
            'is_ready_to_publish' => ! $published
                && ! $historicallyReferenced
                && $version->is_active
                && $allRecipientsAvailable
                && $allocationTotalUnits
                    === FixedDecimal::PERCENTAGE_TOTAL_UNITS,
            'allocations' => $version->allocations
                ->map(
                    fn (
                        ProfitSchemeAllocation $allocation
                    ): array => [
                        'id' => $allocation->id,
                        'percentage' => $this->allocationPercentage(
                            $allocation
                        ),
                        'recipient' => [
                            'id' => $allocation->recipient->id,
                            'code' => $allocation->recipient->code,
                            'display_name' => $allocation
                                ->recipient
                                ->display_name,
                            'type' => $this->recipientType(
                                $allocation->recipient->type
                            ),
                            'is_active' => $allocation
                                ->recipient
                                ->is_active,
                            'is_available_for_allocation' => isset(
                                $availableRecipientLookup[
                                    $allocation->profit_recipient_id
                                ]
                            ),
                        ],
                    ]
                )
                ->values()
                ->all(),
        ];
    }

    private function recipientType(
        ProfitRecipientType|string $type
    ): string {
        return $type instanceof ProfitRecipientType
            ? $type->value
            : $type;
    }

    private function allocationPercentage(
        ProfitSchemeAllocation $allocation
    ): string {
        $percentage = $allocation->getAttribute('percentage');

        if (! is_string($percentage)) {
            throw new LogicException(
                'Stored allocation percentage must be a decimal string.'
            );
        }

        return $percentage;
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (is_string($value)) {
            return $value;
        }

        throw new LogicException('Stored rule date is invalid.');
    }

    private function nullableDateString(mixed $value): ?string
    {
        return $value === null ? null : $this->dateString($value);
    }

    private function nullableDateTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        if (is_string($value)) {
            return $value;
        }

        throw new LogicException('Stored publication timestamp is invalid.');
    }
}
