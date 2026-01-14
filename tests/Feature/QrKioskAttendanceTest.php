<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrKioskAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_can_create_qr_session_and_staff_can_checkin_and_checkout(): void
    {
        // Force "now" into a known shift window.
        Carbon::setTestNow(Carbon::create(2026, 1, 14, 9, 0, 0));

        Shift::create([
            'name' => 'Morning Shift',
            'code' => 'MORNING_SHIFT_TEST',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $user = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff@example.com',
        ]);

        // 1) Kiosk creates a QR session
        $sessionRes = $this->postJson('/api/kiosk/qr/session', [
            'kiosk_id' => 'KIOSK_TEST_01',
        ]);

        $sessionRes->assertStatus(201);
        $sessionRes->assertJsonStructure([
            'status',
            'data' => ['kiosk_id', 'code', 'expires_at', 'ttl_seconds'],
        ]);

        $code = $sessionRes->json('data.code');
        $this->assertNotEmpty($code);

        // 2) Staff scans -> check-in
        $this->actingAs($user, 'sanctum');
        $checkinRes = $this->postJson('/api/attendance/qr', [
            'qr_code' => $code,
            'device_info' => 'Android',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $checkinRes->assertStatus(201);
        $checkinRes->assertJson([
            'status' => 'success',
            'type' => 'check_in',
        ]);

        $this->assertEquals(1, Attendance::where('user_id', $user->id)->count());

        // 3) Scan again -> check-out (same attendance record, no duplicates)
        $checkoutRes = $this->postJson('/api/attendance/qr', [
            'qr_code' => $code,
            'device_info' => 'Android',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $checkoutRes->assertStatus(200);
        $checkoutRes->assertJson([
            'status' => 'success',
            'type' => 'check_out',
        ]);

        $this->assertEquals(1, Attendance::where('user_id', $user->id)->count());
        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance->check_out_time);
    }

    public function test_expired_qr_session_returns_410(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 14, 9, 0, 0));

        Shift::create([
            'name' => 'Morning Shift',
            'code' => 'MORNING_SHIFT_TEST',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $user = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff2@example.com',
        ]);

        $sessionRes = $this->postJson('/api/kiosk/qr/session', [
            'kiosk_id' => 'KIOSK_TEST_02',
        ]);
        $sessionRes->assertStatus(201);
        $code = $sessionRes->json('data.code');

        // Move time forward beyond TTL.
        Carbon::setTestNow(Carbon::create(2026, 1, 14, 9, 30, 0));

        $this->actingAs($user, 'sanctum');
        $res = $this->postJson('/api/attendance/qr', [
            'qr_code' => $code,
            'device_info' => 'Android',
        ]);

        $res->assertStatus(410);
        $res->assertJson([
            'status' => 'error',
            'code' => 'QR_EXPIRED',
        ]);
    }
}
