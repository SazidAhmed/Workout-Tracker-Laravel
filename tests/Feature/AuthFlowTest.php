<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\GymInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_user_and_send_invitation_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'name' => 'New Client',
            'email' => 'client@example.com',
            'role' => UserRole::Client->value,
            'trainer_id' => $admin->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'client@example.com');

        $invitedUser = User::where('email', 'client@example.com')->firstOrFail();

        $this->assertNotNull($invitedUser->invitation_token);
        Notification::assertSentTo($invitedUser, GymInvitationNotification::class);
    }

    public function test_invited_user_can_activate_account_and_verify_email(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => UserRole::Client,
            'invitation_token' => hash('sha256', 'plain-token'),
            'invitation_expires_at' => now()->addDay(),
            'invitation_accepted_at' => null,
        ]);

        $activateResponse = $this->postJson('/api/auth/invitations/plain-token/activate', [
            'name' => 'Activated Client',
            'password' => 'super-secret',
            'password_confirmation' => 'super-secret',
        ]);

        $activateResponse->assertOk();

        $user->refresh();
        $this->assertNull($user->invitation_token);
        $this->assertNotNull($user->invitation_accepted_at);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $verificationResponse = $this->get($verificationUrl);

        $verificationResponse->assertRedirect(config('app.frontend_url').'/auth/verified?status=success');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
