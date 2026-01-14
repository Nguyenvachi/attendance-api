<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_logs_user_creation_in_audit_log()
    {
        // Arrange: Login as admin
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        // Act: Create a new user via API
        $response = $this->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'StrongUniqueP@ss2026!',
            'phone' => '0901234567',
            'role' => 'staff',
            'hourly_rate' => 25000,
        ]);

        // Assert: Response successful
        $response->assertStatus(201);

        // Assert: Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'POST',
            'model' => 'User',
        ]);

        $log = AuditLog::where('user_id', $admin->id)
            ->where('action', 'POST')
            ->where('model', 'User')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->new_data);
        $this->assertEquals('test@example.com', $log->new_data['email'] ?? null);
    }

    /** @test */
    public function it_logs_user_update_with_old_and_new_data()
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['name' => 'Old Name', 'role' => 'staff']);
        Sanctum::actingAs($admin);

        // Act: Update user
        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'New Name',
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => 'manager',
        ]);

        // Assert
        $response->assertStatus(200);

        $log = AuditLog::where('model', 'User')
            ->where('model_id', $user->id)
            ->where('action', 'PUT')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Old Name', $log->old_data['name'] ?? null);
        $this->assertEquals('New Name', $log->new_data['name'] ?? null);
        $this->assertEquals('staff', $log->old_data['role'] ?? null);
        $this->assertEquals('manager', $log->new_data['role'] ?? null);
    }

    /** @test */
    public function it_stores_ip_address_and_user_agent()
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        // Act
        $response = $this->postJson('/api/users', [
            'name' => 'Test',
            'email' => 'ip.test@example.com',
            'password' => 'SecureUnique2026!P@ss',
            'phone' => '0901111111',
            'role' => 'staff',
            'hourly_rate' => 25000,
        ], [
            'User-Agent' => 'TestAgent/1.0',
        ]);

        // Assert
        $response->assertStatus(201);

        $log = AuditLog::latest()->first();
        $this->assertNotNull($log->ip_address);
        $this->assertEquals('TestAgent/1.0', $log->user_agent);
    }

    /** @test */
    public function admin_can_view_audit_logs()
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        AuditLog::factory()->create(['user_id' => $admin->id, 'action' => 'POST', 'model' => 'User']);
        Sanctum::actingAs($admin);

        // Act
        $response = $this->getJson('/api/audit-logs');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'user_id', 'action', 'model', 'created_at'],
            ],
        ]);
    }

    /** @test */
    public function non_admin_cannot_view_audit_logs()
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        // Act
        $response = $this->getJson('/api/audit-logs');

        // Assert
        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_filter_logs_by_model()
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        AuditLog::factory()->create(['model' => 'User']);
        AuditLog::factory()->create(['model' => 'Shift']);
        Sanctum::actingAs($admin);

        // Act
        $response = $this->getJson('/api/audit-logs?model=User');

        // Assert
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        foreach ($data as $log) {
            $this->assertEquals('User', $log['model']);
        }
    }
}
