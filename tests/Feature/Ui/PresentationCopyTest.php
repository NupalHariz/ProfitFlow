<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

class PresentationCopyTest extends TestCase
{
    public function test_login_and_application_shell_are_profitflow_branded(): void
    {
        $this->assertSame('ProfitFlow', config('app.name'));

        $app = $this->source('js/app.tsx');
        $layout = $this->source('js/layouts/auth/auth-simple-layout.tsx');
        $login = $this->source('js/pages/auth/login.tsx');
        $header = $this->source('js/components/app-header.tsx');

        $this->assertStringContainsString("'ProfitFlow'", $app);
        $this->assertStringNotContainsString('VITE_APP_NAME', $app);
        $this->assertStringContainsString('ProfitFlow', $layout);
        $this->assertStringContainsString(
            'Dynamic Profit Distribution System',
            $layout
        );
        $this->assertStringContainsString('Welcome back', $login);
        $this->assertStringContainsString(
            'administration dashboard',
            $login
        );
        $this->assertStringNotContainsString(
            'laravel/react-starter-kit',
            $header
        );
        $this->assertStringNotContainsString('laravel.com/docs', $header);
    }

    public function test_key_business_pages_use_business_facing_copy(): void
    {
        $dashboard = $this->source('js/pages/dashboard.tsx');
        $schemeForm = $this->source(
            'js/components/profit-schemes/version-form.tsx'
        );
        $allocationEditor = $this->source(
            'js/components/profit-schemes/allocation-editor.tsx'
        );
        $transactionLifecycle = $this->source(
            'js/components/transactions/transaction-lifecycle.tsx'
        );
        $deleteProduct = $this->source(
            'js/components/products/delete-product-dialog.tsx'
        );
        $recipientShow = $this->source(
            'js/pages/profit-recipients/show.tsx'
        );

        $this->assertStringContainsString(
            'Overview of transactions, profit, and distributions.',
            $dashboard
        );
        $this->assertStringContainsString(
            'Higher-priority rules are applied first.',
            $schemeForm
        );
        $this->assertStringContainsString(
            'Publishing requires a',
            $allocationEditor
        );
        $this->assertStringContainsString(
            'Profit has been distributed.',
            $transactionLifecycle
        );

        foreach ([
            'Waiting for worker',
            'immutable distribution ledger',
            'Immutable ledger rows',
        ] as $oldCopy) {
            $this->assertStringNotContainsString($oldCopy, $dashboard);
        }

        $this->assertStringNotContainsString(
            'publication time, version, then ID',
            $schemeForm
        );
        $this->assertStringNotContainsString(
            'atomically persisting',
            $transactionLifecycle
        );
        foreach ([
            $deleteProduct,
            $this->source(
                'js/components/product-categories/delete-product-category-dialog.tsx'
            ),
            $this->source(
                'js/components/customers/delete-customer-dialog.tsx'
            ),
            $this->source(
                'js/components/investors/delete-investor-dialog.tsx'
            ),
        ] as $deleteDialog) {
            $this->assertStringNotContainsString(
                'soft delete',
                strtolower($deleteDialog)
            );
        }

        $this->assertStringNotContainsString(
            'Database counts only',
            $recipientShow
        );
        $this->assertMatchesRegularExpression(
            '/Existing\s+transactions remain unchanged\./',
            $this->source(
                'js/components/product-categories/delete-product-category-dialog.tsx'
            )
        );
        $this->assertStringContainsString(
            'Unavailable recipients must be replaced before',
            $allocationEditor
        );
    }

    public function test_accessibility_copy_contracts_remain_present(): void
    {
        $this->assertStringContainsString(
            'role="alert"',
            $this->source('js/components/input-error.tsx')
        );
        $this->assertStringContainsString(
            'aria-label="Search customers"',
            $this->source('js/pages/customers/index.tsx')
        );
        $this->assertStringContainsString(
            'aria-label="Search investors"',
            $this->source('js/pages/investors/index.tsx')
        );
    }

    public function test_contact_pages_share_the_phone_display_formatter(): void
    {
        foreach ([
            'js/pages/customers/index.tsx',
            'js/pages/customers/show.tsx',
            'js/pages/investors/index.tsx',
            'js/pages/investors/show.tsx',
            'js/pages/profit-recipients/show.tsx',
            'js/pages/transactions/show.tsx',
        ] as $path) {
            $this->assertStringContainsString(
                'formatIndonesianPhoneNumber',
                $this->source($path),
                "Missing shared phone formatter in [{$path}]."
            );
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(resource_path($path));

        $this->assertIsString($source);

        return $source;
    }
}
