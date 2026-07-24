<?php

use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Investors\InvestorController;
use App\Http\Controllers\ProductCategories\ProductCategoryController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\ProfitRecipients\ProfitRecipientController;
use App\Http\Controllers\ProfitSchemes\ProfitSchemeAllocationController;
use App\Http\Controllers\ProfitSchemes\ProfitSchemeController;
use App\Http\Controllers\ProfitSchemes\ProfitSchemePublicationController;
use App\Http\Controllers\ProfitSchemes\ProfitSchemeVersionController;
use App\Http\Controllers\Transactions\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');

    Route::prefix('product-categories')
        ->name('product-categories.')
        ->group(function (): void {
            Route::get('/', [ProductCategoryController::class, 'index'])
                ->name('index');
            Route::get('create', [ProductCategoryController::class, 'create'])
                ->name('create');
            Route::post('/', [ProductCategoryController::class, 'store'])
                ->name('store');
            Route::get(
                '{productCategory}/edit',
                [ProductCategoryController::class, 'edit']
            )->name('edit');
            Route::patch(
                '{productCategory}/status',
                [ProductCategoryController::class, 'updateStatus']
            )->name('update-status');
            Route::patch(
                '{productCategory}',
                [ProductCategoryController::class, 'update']
            )->name('update');
            Route::delete(
                '{productCategory}',
                [ProductCategoryController::class, 'destroy']
            )->name('destroy');
        });

    Route::prefix('products')
        ->name('products.')
        ->group(function (): void {
            Route::get('/', [ProductController::class, 'index'])
                ->name('index');
            Route::get('create', [ProductController::class, 'create'])
                ->name('create');
            Route::post('/', [ProductController::class, 'store'])
                ->name('store');
            Route::get(
                '{product}/edit',
                [ProductController::class, 'edit']
            )->name('edit');
            Route::patch(
                '{product}/status',
                [ProductController::class, 'updateStatus']
            )->name('update-status');
            Route::patch(
                '{product}',
                [ProductController::class, 'update']
            )->name('update');
            Route::delete(
                '{product}',
                [ProductController::class, 'destroy']
            )->name('destroy');
        });

    Route::prefix('customers')
        ->name('customers.')
        ->group(function (): void {
            Route::get('/', [CustomerController::class, 'index'])
                ->name('index');
            Route::get('create', [CustomerController::class, 'create'])
                ->name('create');
            Route::post('/', [CustomerController::class, 'store'])
                ->name('store');
            Route::get(
                '{customer}/edit',
                [CustomerController::class, 'edit']
            )->name('edit');
            Route::patch(
                '{customer}',
                [CustomerController::class, 'update']
            )->name('update');
            Route::delete(
                '{customer}',
                [CustomerController::class, 'destroy']
            )->name('destroy');
            Route::get('{customer}', [CustomerController::class, 'show'])
                ->name('show');
        });

    Route::prefix('investors')
        ->name('investors.')
        ->group(function (): void {
            Route::get('/', [InvestorController::class, 'index'])
                ->name('index');
            Route::get('create', [InvestorController::class, 'create'])
                ->name('create');
            Route::post('/', [InvestorController::class, 'store'])
                ->name('store');
            Route::get(
                '{investor}/edit',
                [InvestorController::class, 'edit']
            )->name('edit');
            Route::patch(
                '{investor}/status',
                [InvestorController::class, 'updateStatus']
            )->name('update-status');
            Route::patch(
                '{investor}',
                [InvestorController::class, 'update']
            )->name('update');
            Route::delete(
                '{investor}',
                [InvestorController::class, 'destroy']
            )->name('destroy');
            Route::get('{investor}', [InvestorController::class, 'show'])
                ->name('show');
        });

    Route::prefix('profit-recipients')
        ->name('profit-recipients.')
        ->group(function (): void {
            Route::get('/', [ProfitRecipientController::class, 'index'])
                ->name('index');
            Route::get(
                'create',
                [ProfitRecipientController::class, 'create']
            )->name('create');
            Route::post('/', [ProfitRecipientController::class, 'store'])
                ->name('store');
            Route::get(
                '{profitRecipient}/edit',
                [ProfitRecipientController::class, 'edit']
            )->name('edit');
            Route::patch(
                '{profitRecipient}/status',
                [ProfitRecipientController::class, 'updateStatus']
            )->name('update-status');
            Route::patch(
                '{profitRecipient}',
                [ProfitRecipientController::class, 'update']
            )->name('update');
            Route::get(
                '{profitRecipient}',
                [ProfitRecipientController::class, 'show']
            )->name('show');
        });

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

    Route::prefix('transactions')
        ->name('transactions.')
        ->group(function (): void {
            Route::get('/', [TransactionController::class, 'index'])
                ->name('index');
            Route::get('create', [TransactionController::class, 'create'])
                ->name('create');
            Route::post('/', [TransactionController::class, 'store'])
                ->name('store');
            Route::get(
                '{transaction}/edit',
                [TransactionController::class, 'edit']
            )->name('edit');
            Route::patch(
                '{transaction}',
                [TransactionController::class, 'update']
            )->name('update');
            Route::post(
                '{transaction}/complete',
                [TransactionController::class, 'complete']
            )->name('complete');
            Route::post(
                '{transaction}/cancel',
                [TransactionController::class, 'cancel']
            )->name('cancel');
            Route::post(
                '{transaction}/retry-distribution',
                [TransactionController::class, 'retryDistribution']
            )->name('retry-distribution');
            Route::get('{transaction}', [TransactionController::class, 'show'])
                ->name('show');
        });
});

require __DIR__.'/settings.php';
