<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_only_sees_own_clients(): void
    {
        $trainer = User::factory()->trainer()->create();
        $otherTrainer = User::factory()->trainer()->create();
        $ownedClient = User::factory()->client($trainer)->create();
        User::factory()->client($otherTrainer)->create();

        Sanctum::actingAs($trainer);

        $response = $this->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownedClient->id);
    }

    public function test_client_cannot_access_admin_user_listing(): void
    {
        $client = User::factory()->client()->create();
        Sanctum::actingAs($client);

        $response = $this->getJson('/api/users');

        $response->assertForbidden();
    }
}
