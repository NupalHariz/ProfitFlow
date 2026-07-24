<?php

namespace App\Http\Controllers\ProfitSchemes;

use App\Actions\ProfitSchemes\CreateProfitSchemeVersion;
use App\Actions\ProfitSchemes\UpdateDraftProfitSchemeVersion;
use App\Enums\ProfitRecipientType;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\ProfitSchemeVersionImmutable;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitSchemes\StoreProfitSchemeVersionRequest;
use App\Http\Requests\ProfitSchemes\UpdateProfitSchemeVersionRequest;
use App\Models\ProductCategory;
use App\Models\ProfitRecipient;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeAllocation;
use App\Models\ProfitSchemeVersion;
use App\Models\User;
use App\Services\ProfitSharing\FixedDecimal;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class ProfitSchemeVersionController extends Controller
{
    private const RECIPIENT_LOOKUP_LIMIT = 250;

    public function create(ProfitScheme $scheme): Response
    {
        return Inertia::render('profit-schemes/versions/create', [
            'scheme' => $this->schemeSummary($scheme),
            'categories' => $this->categoryLookups(),
        ]);
    }

    public function store(
        StoreProfitSchemeVersionRequest $request,
        ProfitScheme $scheme,
        CreateProfitSchemeVersion $action
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        try {
            $version = $action->execute(
                $scheme,
                $request->domainData(),
                $user
            );
        } catch (InvalidProfitSchemeVersion $exception) {
            throw ValidationException::withMessages([
                'version' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Draft version {$version->version} created.",
        ]);

        return to_route('profit-schemes.versions.edit', [
            'scheme' => $scheme,
            'version' => $version,
        ]);
    }

    public function edit(
        ProfitScheme $scheme,
        ProfitSchemeVersion $version
    ): Response {
        $version->loadCount('appliedTransactionItems');
        $version->load([
            'allocations' => fn ($query) => $query
                ->select([
                    'id',
                    'profit_scheme_version_id',
                    'profit_recipient_id',
                    'percentage',
                ])
                ->orderBy('id'),
        ]);
        $selectedRecipientIds = array_values(
            $version->allocations
                ->pluck('profit_recipient_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
        );
        $recipientLookups = $this->recipientLookups(
            $selectedRecipientIds
        );

        return Inertia::render('profit-schemes/versions/edit', [
            'scheme' => $this->schemeSummary($scheme),
            'version' => $this->versionData(
                $version,
                collect($recipientLookups['current'])
                    ->where('is_available', false)
                    ->count()
            ),
            'categories' => $this->categoryLookups(
                $version->product_category_id
            ),
            'recipients' => $recipientLookups['available'],
            'allocation_recipients' => $recipientLookups['current'],
            'lookup_limit' => self::RECIPIENT_LOOKUP_LIMIT,
        ]);
    }

    public function update(
        UpdateProfitSchemeVersionRequest $request,
        ProfitScheme $scheme,
        ProfitSchemeVersion $version,
        UpdateDraftProfitSchemeVersion $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $version,
                $request->domainData()
            );
        } catch (
            InvalidProfitSchemeVersion|ProfitSchemeVersionImmutable $exception
        ) {
            throw ValidationException::withMessages([
                'version' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Version {$updated->version} rule saved.",
        ]);

        return to_route('profit-schemes.versions.edit', [
            'scheme' => $scheme,
            'version' => $updated,
        ]);
    }

    /**
     * The technical-test dataset is bounded. Replace this list with
     * server-side search when category volume materially grows.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     is_active: bool,
     *     is_deleted: bool
     * }>
     */
    private function categoryLookups(?int $selectedId = null): array
    {
        $query = ProductCategory::withTrashed()
            ->select(['id', 'name', 'is_active', 'deleted_at'])
            ->where(function ($query) use ($selectedId): void {
                $query->whereNull('deleted_at');

                if ($selectedId !== null) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(250);

        return array_values(
            $query->get()
                ->map(
                    fn (ProductCategory $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'is_active' => $category->is_active,
                        'is_deleted' => $category->trashed(),
                    ]
                )
                ->all()
        );
    }

    /**
     * The technical-test dataset is bounded. Replace this list with
     * server-side search when recipient volume materially grows.
     *
     * @param  list<int>  $selectedIds
     * @return array{
     *     available: list<array<string, mixed>>,
     *     current: list<array<string, mixed>>
     * }
     */
    private function recipientLookups(array $selectedIds): array
    {
        /** @var Collection<int, ProfitRecipient> $selectedAvailable */
        $selectedAvailable = $selectedIds === []
            ? collect()
            : ProfitRecipient::query()
                ->availableForAllocation()
                ->select($this->recipientLookupColumns())
                ->with([
                    'investor' => fn ($query) => $query
                        ->select(['id', 'name', 'is_active']),
                ])
                ->whereIn('id', $selectedIds)
                ->orderBy('display_name')
                ->orderBy('id')
                ->get();
        $availableSelectedIds = $selectedAvailable
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $availableSelectedLookup = array_fill_keys(
            $availableSelectedIds,
            true
        );
        /** @var Collection<int, ProfitRecipient> $unavailableCurrent */
        $unavailableCurrent = $selectedIds === []
            ? collect()
            : ProfitRecipient::query()
                ->select($this->recipientLookupColumns())
                ->with([
                    'investor' => fn ($query) => $query
                        ->select(['id', 'name', 'is_active']),
                ])
                ->whereIn('id', $selectedIds)
                ->when(
                    $availableSelectedIds !== [],
                    fn ($query) => $query->whereNotIn(
                        'id',
                        $availableSelectedIds
                    )
                )
                ->orderBy('display_name')
                ->orderBy('id')
                ->get();
        $current = $selectedAvailable
            ->concat($unavailableCurrent)
            ->sortBy(
                fn (ProfitRecipient $recipient): string => mb_strtolower(
                    $recipient->display_name
                )
            );
        $selectedAvailableOptions = $selectedAvailable
            ->take(self::RECIPIENT_LOOKUP_LIMIT);
        $remaining = self::RECIPIENT_LOOKUP_LIMIT
            - $selectedAvailableOptions->count();
        /** @var Collection<int, ProfitRecipient> $additionalAvailable */
        $additionalAvailable = ProfitRecipient::query()
            ->availableForAllocation()
            ->select($this->recipientLookupColumns())
            ->with([
                'investor' => fn ($query) => $query
                    ->select(['id', 'name', 'is_active']),
            ])
            ->when(
                $selectedIds !== [],
                fn ($query) => $query->whereNotIn('id', $selectedIds)
            )
            ->orderBy('display_name')
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        $available = $selectedAvailableOptions
            ->concat($additionalAvailable)
            ->unique('id')
            ->sortBy(
                fn (ProfitRecipient $recipient): string => mb_strtolower(
                    $recipient->display_name
                )
            )
            ->map(
                fn (ProfitRecipient $recipient): array => $this
                    ->recipientLookupData($recipient, true)
            )
            ->values()
            ->all();
        $currentData = $current
            ->map(
                fn (ProfitRecipient $recipient): array => $this
                    ->recipientLookupData(
                        $recipient,
                        isset($availableSelectedLookup[$recipient->id])
                    )
            )
            ->values()
            ->all();

        return [
            'available' => array_values($available),
            'current' => array_values($currentData),
        ];
    }

    /**
     * @return list<string>
     */
    private function recipientLookupColumns(): array
    {
        return [
            'id',
            'investor_id',
            'code',
            'display_name',
            'type',
            'is_active',
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     display_name: string,
     *     type: string,
     *     is_active: bool,
     *     is_available: bool,
     *     investor: null|array{
     *         id: int,
     *         name: string,
     *         is_active: bool
     *     }
     * }
     */
    private function recipientLookupData(
        ProfitRecipient $recipient,
        bool $available
    ): array {
        return [
            'id' => $recipient->id,
            'code' => $recipient->code,
            'display_name' => $recipient->display_name,
            'type' => $this->recipientType($recipient->type),
            'is_active' => $recipient->is_active,
            'is_available' => $available,
            'investor' => $recipient->investor === null
                ? null
                : [
                    'id' => $recipient->investor->id,
                    'name' => $recipient->investor->name,
                    'is_active' => $recipient->investor->is_active,
                ],
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, is_active: bool}
     */
    private function schemeSummary(ProfitScheme $scheme): array
    {
        return [
            'id' => $scheme->id,
            'name' => $scheme->name,
            'code' => $scheme->code,
            'is_active' => $scheme->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionData(
        ProfitSchemeVersion $version,
        int $unavailableAllocationCount
    ): array {
        $published = $version->published_at !== null;
        $historicallyReferenced = (int) $version->getAttribute(
            'applied_transaction_items_count'
        ) > 0;
        $totalUnits = $version->allocations->sum(
            fn (ProfitSchemeAllocation $allocation): int => FixedDecimal::percentageToUnits(
                $this->allocationPercentage($allocation)
            )
        );

        return [
            'id' => $version->id,
            'version' => $version->version,
            'product_category_id' => $version->product_category_id,
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
            'is_published' => $published,
            'is_historically_referenced' => $historicallyReferenced,
            'is_immutable' => $published || $historicallyReferenced,
            'can_edit' => ! $published && ! $historicallyReferenced,
            'allocation_total' => FixedDecimal::formatPercentage($totalUnits),
            'unavailable_allocation_count' => $unavailableAllocationCount,
            'is_ready_to_publish' => ! $published
                && ! $historicallyReferenced
                && $version->is_active
                && $unavailableAllocationCount === 0
                && $totalUnits === FixedDecimal::PERCENTAGE_TOTAL_UNITS,
            'allocations' => $version->allocations
                ->map(
                    fn (
                        ProfitSchemeAllocation $allocation
                    ): array => [
                        'id' => $allocation->id,
                        'profit_recipient_id' => $allocation
                            ->profit_recipient_id,
                        'percentage' => $this->allocationPercentage(
                            $allocation
                        ),
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
