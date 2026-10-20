<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Services\Mobile\MobileNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('roles')->insert(['id' => 1, 'name' => 'technician', 'description' => 'Técnico']);
        $this->user = User::factory()->create(['role_id' => 1, 'phone' => '3121111111', 'password' => 'old-password']);
        $this->other = User::factory()->create(['role_id' => 1, 'phone' => '3121111112']);
    }

    public function test_profile_changes_are_validated_and_cannot_change_role_or_another_account(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->actingAs($this->user, 'api')->getJson('/api/profile')->assertOk()->assertJsonPath('data.role', 'technician')->assertJsonMissingPath('data.password');
        $this->postJson('/api/update-profile', ['name' => 'Nuevo', 'email' => $this->other->email])->assertUnprocessable();
        $this->postJson('/api/update-profile', ['name' => 'Nuevo', 'email' => 'nuevo@example.com', 'role_id' => 999, 'id' => $this->other->id])->assertOk()->assertJsonPath('data.name', 'Nuevo')->assertJsonPath('data.email', 'nuevo@example.com')->assertJsonPath('data.id', $this->user->id)->assertJsonPath('data.role', 'technician');
        $this->assertSame($this->other->email, $this->other->fresh()->email);
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_password_change_requires_current_password_and_confirmation_and_preserves_only_current_token(): void
    {
        $this->actingAs($this->user, 'api');
        $payload = ['current_password' => 'wrong', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
        $this->postJson('/api/change-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/api/change-password', [...$payload, 'current_password' => 'old-password', 'password_confirmation' => 'wrong'])->assertUnprocessable();
        $current = \Laravel\Passport\Token::create(['id' => str_repeat('a', 80), 'user_id' => $this->user->id, 'client_id' => (string) Str::uuid(), 'scopes' => [], 'revoked' => false]);
        $other = \Laravel\Passport\Token::create(['id' => str_repeat('b', 80), 'user_id' => $this->user->id, 'client_id' => (string) Str::uuid(), 'scopes' => [], 'revoked' => false]);
        $this->user->withAccessToken(new \Laravel\Passport\AccessToken(['oauth_access_token_id' => $current->id]));
        $this->postJson('/api/change-password', [...$payload, 'current_password' => 'old-password'])->assertOk()->assertJsonPath('message', 'Contraseña actualizada');
        $this->assertTrue(Hash::check('new-password-123', $this->user->fresh()->password));
        $this->assertFalse((bool) $current->fresh()->revoked);
        $this->assertTrue((bool) $other->fresh()->revoked);
    }

    public function test_notification_history_and_mark_read_are_private_paginated_and_deduplicated(): void
    {
        $id = (string) Str::uuid();
        $service = app(MobileNotificationService::class);
        $service->remember($this->user, $id, 'Aviso', 'Evento actualizado', 123);
        $service->remember($this->user, $id, 'Aviso', 'Evento actualizado', 123);
        $service->remember($this->other, (string) Str::uuid(), 'Privado', 'Otro usuario', 456);
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->actingAs($this->user, 'api')->getJson('/api/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.event_id', 123)->assertJsonPath('data.0.read_at', null);
        $read = $this->patchJson('/api/notifications/'.$id.'/read', [])->assertOk()->json('data.read_at');
        $this->assertNotNull($read);
        $this->patchJson('/api/notifications/'.$id.'/read', [])->assertOk()->assertJsonPath('data.read_at', $read);
        $this->actingAs($this->other, 'api')->patchJson('/api/notifications/'.$id.'/read', [])->assertNotFound();
        $this->assertDatabaseCount('notifications', 2);
    }
}
