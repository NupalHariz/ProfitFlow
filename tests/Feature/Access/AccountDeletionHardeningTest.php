<?php

namespace Tests\Feature\Access;

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesDomainRecords;
use Tests\TestCase;

class AccountDeletionHardeningTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    public function test_self_service_account_deletion_route_controller_and_ui_are_absent(): void
    {
        $this->assertFalse(Route::has('profile.destroy'));
        $this->assertFalse(method_exists(ProfileController::class, 'destroy'));
        $this->assertFileDoesNotExist(
            app_path('Http/Requests/Settings/ProfileDeleteRequest.php'),
        );
        $this->assertFileDoesNotExist(
            resource_path('js/components/delete-user.tsx'),
        );

        $profileSource = file_get_contents(
            resource_path('js/pages/settings/profile.tsx'),
        );

        $this->assertIsString($profileSource);
        $this->assertStringNotContainsString('DeleteUser', $profileSource);
        $this->assertStringNotContainsString('Delete account', $profileSource);
    }

    public function test_delete_request_is_rejected_before_audit_references_can_be_touched(): void
    {
        $user = $this->createUser();
        $scheme = $this->createScheme($user);
        $version = $this->createSchemeVersion($scheme, $user);
        $transaction = $this->createTransaction(creator: $user, attributes: [
            'completed_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('profit_schemes', [
            'id' => $scheme->id,
            'created_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('profit_scheme_versions', [
            'id' => $version->id,
            'created_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'created_by_user_id' => $user->id,
            'completed_by_user_id' => $user->id,
        ]);
        $this->assertNotNull($scheme->fresh());
        $this->assertNotNull($version->fresh());
        $this->assertNotNull($transaction->fresh());
    }
}
