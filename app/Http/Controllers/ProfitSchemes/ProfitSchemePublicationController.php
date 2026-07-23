<?php

namespace App\Http\Controllers\ProfitSchemes;

use App\Actions\ProfitSchemes\PublishProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeAllocation;
use App\Exceptions\ProfitSchemes\InvalidProfitSchemeVersion;
use App\Exceptions\ProfitSchemes\ProfitSchemeVersionImmutable;
use App\Http\Controllers\Controller;
use App\Models\ProfitScheme;
use App\Models\ProfitSchemeVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfitSchemePublicationController extends Controller
{
    public function store(
        ProfitScheme $scheme,
        ProfitSchemeVersion $version,
        PublishProfitSchemeVersion $action
    ): RedirectResponse {
        try {
            $published = $action->execute($version);
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
            'message' => "Version {$published->version} published.",
        ]);

        return to_route('profit-schemes.show', $scheme);
    }
}
