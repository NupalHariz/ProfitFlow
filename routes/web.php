<?php

use App\Http\Controllers\ProfitSchemes\ProfitSchemeAllocationController;
use App\Http\Controllers\ProfitSchemes\ProfitSchemeController;
use App\Http\Controllers\ProfitSchemes\ProfitSchemePublicationController;
use App\Http\Controllers\ProfitSchemes\ProfitSchemeVersionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::scopeBindings()
        ->prefix('profit-schemes')
        ->name('profit-schemes.')
        ->group(function (): void {
            Route::get('/', [ProfitSchemeController::class, 'index'])
                ->name('index');
            Route::get('create', [ProfitSchemeController::class, 'create'])
                ->name('create');
            Route::post('/', [ProfitSchemeController::class, 'store'])
                ->name('store');
            Route::get('{scheme}', [ProfitSchemeController::class, 'show'])
                ->name('show');

            Route::get(
                '{scheme}/versions/create',
                [ProfitSchemeVersionController::class, 'create']
            )->name('versions.create');
            Route::post(
                '{scheme}/versions',
                [ProfitSchemeVersionController::class, 'store']
            )->name('versions.store');
            Route::get(
                '{scheme}/versions/{version}/edit',
                [ProfitSchemeVersionController::class, 'edit']
            )->name('versions.edit');
            Route::patch(
                '{scheme}/versions/{version}',
                [ProfitSchemeVersionController::class, 'update']
            )->name('versions.update');
            Route::put(
                '{scheme}/versions/{version}/allocations',
                [ProfitSchemeAllocationController::class, 'update']
            )->name('versions.allocations.update');
            Route::post(
                '{scheme}/versions/{version}/publish',
                [ProfitSchemePublicationController::class, 'store']
            )->name('versions.publish');
        });
});

require __DIR__.'/settings.php';
