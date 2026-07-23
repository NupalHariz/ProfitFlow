<?php

namespace App\Http\Controllers\ProfitSchemes;

use App\Actions\ProfitSchemes\ReplaceDraftProfitSchemeAllocations;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeAllocation;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\ProfitSchemeVersionImmutable;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitSchemes\ReplaceProfitSchemeAllocationsRequest;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfitSchemeAllocationController extends Controller
{
    public function update(
        ReplaceProfitSchemeAllocationsRequest $request,
        ProfitScheme $scheme,
        ProfitSchemeVersion $version,
        ReplaceDraftProfitSchemeAllocations $action
    ): RedirectResponse {
        try {
            $updated = $action->execute(
                $version,
                $request->allocations()
            );
        } catch (InvalidProfitSchemeAllocation $exception) {
            throw ValidationException::withMessages([
                'allocations' => $exception->getMessage(),
            ]);
        } catch (
            InvalidProfitSchemeVersion|ProfitSchemeVersionImmutable $exception
        ) {
            throw ValidationException::withMessages([
                'version' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Version {$updated->version} allocations saved.",
        ]);

        return to_route('profit-schemes.versions.edit', [
            'scheme' => $scheme,
            'version' => $updated,
        ]);
    }
}
