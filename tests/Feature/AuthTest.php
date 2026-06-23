<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_correct_credentials(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);
    }

    public function test_non_admin_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'user@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_creates_audit_log(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'resource_type' => 'User',
            'resource_id' => $admin->id,
        ]);
    }

    public function test_admin_can_access_dashboard_with_token(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $admin->email);
    }

    public function test_non_admin_token_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('test', ['*'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(401);
    }
}