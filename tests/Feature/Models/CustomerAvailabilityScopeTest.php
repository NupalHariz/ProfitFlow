<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAvailabilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_contains_only_non_deleted_customers(): void
    {
        $available = Customer::factory()->create();
        $deleted = Customer::factory()->create();
        $deleted->delete();

        $customers = Customer::query()
            ->availableForTransaction()
            ->get();

        $this->assertSame(
            [$available->id],
            $customers->pluck('id')->all()
        );
    }

    public function test_scope_remains_authoritative_after_with_trashed(): void
    {
        $available = Customer::factory()->create();
        $deleted = Customer::factory()->create();
        $deleted->delete();

        $customers = Customer::withTrashed()
            ->availableForTransaction()
            ->select(['id', 'name'])
            ->whereIn('id', [$available->id, $deleted->id])
            ->orderBy('name')
            ->limit(10)
            ->get();

        $this->assertCount(1, $customers);
        $this->assertSame($available->id, $customers->sole()->id);
        $this->assertFalse($customers->sole()->relationLoaded(
            'transactions'
        ));
    }
}
