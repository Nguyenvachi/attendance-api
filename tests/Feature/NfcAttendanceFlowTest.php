<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NfcAttendanceFlowTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * BỔ SUNG: đảm bảo DB test có schema mà KHÔNG dùng migrate:fresh.
     * Dùng sqlite file database/testing.sqlite + chạy migrate idempotent.
     */
    protected function setUp(): void
    {
        // Tạo file DB sqlite nếu chưa có (PHẢI chạy trước parent::setUp)
        $basePath = dirname(__DIR__, 2);
        $dbPath = $basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'testing.sqlite';
        if (! file_exists($dbPath)) {
            @mkdir(dirname($dbPath), 0777, true);
            file_put_contents($dbPath, '');
        }

        parent::setUp();

        // Chạy migrate (idempotent) để đảm bảo có bảng
        Artisan::call('migrate', ['--no-interaction' => true]);

        // KioskController hardcode shift_id=1, nên đảm bảo có shift id=1
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

    public function test_issue_payload_then_kiosk_attendance_by_payload(): void
    {
        // Admin phát hành payload
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'hourly_rate' => 10000,
        ]);

        Sanctum::actingAs($admin);

        $issueRes = $this->postJson("/api/users/{$staff->id}/nfc/issue");
        $issueRes->assertStatus(200);
        $issueRes->assertJsonPath('status', 'success');

        $payload = $issueRes->json('data.payload');
        $this->assertNotEmpty($payload);
        $this->assertStringStartsWith('NCTNFC:v1:'.$staff->id.':', $payload);

        // Kiosk chấm công (không auth)
        $kioskIn = $this->postJson('/api/kiosk/attendance', ['nfc_code' => $payload]);
        $kioskIn->assertStatus(201);
        $kioskIn->assertJsonPath('status', 'success');
        $kioskIn->assertJsonPath('type', 'check_in');

        // Quẹt lần 2 -> check-out
        $kioskOut = $this->postJson('/api/kiosk/attendance', ['nfc_code' => $payload]);
        $kioskOut->assertStatus(200);
        $kioskOut->assertJsonPath('status', 'success');
        $kioskOut->assertJsonPath('type', 'check_out');

        $workHours = $kioskOut->json('data.work_hours');
        $this->assertNotNull($workHours);
    }

    public function test_kiosk_rejects_locked_user_even_with_valid_payload(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
        $issueRes = $this->postJson("/api/users/{$staff->id}/nfc/issue");
        $payload = $issueRes->json('data.payload');

        // Khóa user
        $staff->is_active = false;
        $staff->save();

        $kiosk = $this->postJson('/api/kiosk/attendance', ['nfc_code' => $payload]);
        $kiosk->assertStatus(403);
        $kiosk->assertJsonPath('status', 'error');
    }

    public function test_kiosk_accepts_payload_with_whitespace_and_null_bytes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
        $issueRes = $this->postJson("/api/users/{$staff->id}/nfc/issue");
        $payload = $issueRes->json('data.payload');

        $dirty = "  {$payload}\0\0\n";
        $kiosk = $this->postJson('/api/kiosk/attendance', ['nfc_code' => $dirty]);
        $kiosk->assertStatus(201);
        $kiosk->assertJsonPath('status', 'success');
    }
}
