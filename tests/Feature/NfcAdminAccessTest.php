<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NfcAdminAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Đảm bảo migrate đã chạy (không migrate:fresh)
        Artisan::call('migrate', ['--no-interaction' => true]);

        // Đảm bảo có shift id=1 để không vướng các flow khác
        DB::table('shifts')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Ca mặc định',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'code' => 'DEFAULT_SHIFT',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function test_staff_cannot_issue_nfc_payload(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        $target = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($staff);

        $res = $this->postJson("/api/users/{$target->id}/nfc/issue");
        $res->assertStatus(403);
        $res->assertJsonPath('status', 'error');
    }

    public function test_admin_can_issue_nfc_payload(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $target = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $res = $this->postJson("/api/users/{$target->id}/nfc/issue");
        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');
        $this->assertNotEmpty($res->json('data.payload'));
    }

    public function test_staff_cannot_update_user_nfc_uid(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        $target = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($staff);

        $res = $this->putJson("/api/users/{$target->id}/nfc", ['nfc_uid' => 'AA:BB:CC:DD']);
        $res->assertStatus(403);
        $res->assertJsonPath('status', 'error');
    }

    public function test_admin_can_update_user_nfc_uid(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $target = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $res = $this->putJson("/api/users/{$target->id}/nfc", ['nfc_uid' => 'AA:BB:CC:DD']);
        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');
        $res->assertJsonPath('data.new_nfc_uid', 'AA:BB:CC:DD');
    }
}
