<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FaceAttendanceFlowTest extends TestCase
{
    /**
     * Test flow kiosk face: check-in then check-out.
     * KHÔNG migrate:fresh: chạy migrate idempotent.
     */
    public function test_kiosk_face_attendance_flow(): void
    {
        // Ensure sqlite file exists (pattern giống các test khác trong dự án)
        $sqlitePath = database_path('testing.sqlite');
        if (! file_exists($sqlitePath)) {
            @touch($sqlitePath);
        }

        Artisan::call('migrate', ['--no-interaction' => true]);

        // KioskController đang dùng shift_id = 1 (mặc định), nên test cần đảm bảo shift này tồn tại
        DB::table('shifts')->insertOrIgnore([
            'id' => 1,
            'name' => 'Ca mặc định',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'code' => 'DEFAULT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'staff',
            'hourly_rate' => 20000,
            'is_active' => true,
        ]);

        $payload = [
            'user_id' => $user->id,
            'match_score' => 0.85,
            'model_version' => 'mobilefacenet_v1',
        ];

        $res1 = $this->postJson('/api/kiosk/attendance-face', $payload);
        $res1->assertStatus(201);
        $res1->assertJsonPath('status', 'success');
        $res1->assertJsonPath('type', 'check_in');

        $res2 = $this->postJson('/api/kiosk/attendance-face', $payload);
        $res2->assertStatus(200);
        $res2->assertJsonPath('status', 'success');
        $res2->assertJsonPath('type', 'check_out');
    }
}
