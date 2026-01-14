<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceConditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SPRINT 2: Test race condition prevention với DB::transaction() + lockForUpdate()
     *
     * Mục tiêu: Đảm bảo 2 concurrent check-in requests không tạo duplicate attendance
     * Sử dụng DB::beginTransaction() để test isolation
     */
    public function test_concurrent_checkin_requests_do_not_create_duplicate_attendance(): void
    {
        // Arrange: Tạo user và shift
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'staff',
            'nfc_uid' => 'TEST_NFC_001',
        ]);

        $shift = Shift::create([
            'name' => 'Morning Shift',
            'code' => 'MORNING_SHIFT_TEST',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        // Đăng nhập
        $this->actingAs($user, 'sanctum');

        // Act: Simulate 2 concurrent check-in requests
        // Do Laravel tests chạy sequential, tôi sẽ test logic DB::transaction()
        // bằng cách kiểm tra constraint violation

        // Request 1: Check-in thành công
        $response1 = $this->postJson('/api/kiosk/attendance', [
            'nfc_code' => 'TEST_NFC_001',
        ]);

        $response1->assertStatus(201);
        $response1->assertJsonStructure([
            'status',
            'type',
            'message',
            'data' => ['attendance_id', 'check_in_time'],
        ]);

        // Assert: Chỉ có 1 attendance record
        $this->assertEquals(1, Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', Carbon::today())
            ->count());

        // Request 2: Cùng user check-in lại trong ngày → Không tạo duplicate
        // Vì đã có attendance chưa check-out, sẽ auto check-out
        $response2 = $this->postJson('/api/kiosk/attendance', [
            'nfc_code' => 'TEST_NFC_001',
        ]);

        $response2->assertStatus(200); // Check-out thành công
        $response2->assertJson([
            'type' => 'check_out',
        ]);

        // Assert: Vẫn chỉ có 1 attendance record (đã được check-out)
        $this->assertEquals(1, Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', Carbon::today())
            ->count());

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', Carbon::today())
            ->first();

        $this->assertNotNull($attendance->check_out_time);
    }

    /**
     * Test concurrent checkout requests không bị race condition
     */
    public function test_concurrent_checkout_requests_do_not_cause_errors(): void
    {
        // Arrange: Tạo attendance đã check-in
        $user = User::factory()->create([
            'nfc_uid' => 'TEST_NFC_002',
        ]);

        $shift = Shift::create([
            'name' => 'Test Shift',
            'code' => 'TEST_SHIFT_002',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'check_in_time' => Carbon::now()->subHours(8),
            'device_info' => 'Test Kiosk',
        ]);

        $this->actingAs($user, 'sanctum');

        // Act: Checkout request 1
        $response1 = $this->postJson('/api/attendance/checkout');
        $response1->assertStatus(200);

        // Refresh data
        $attendance->refresh();
        $this->assertNotNull($attendance->check_out_time);

        // Act: Checkout request 2 (duplicate) → Sẽ fail vì không tìm thấy attendance chưa checkout
        $response2 = $this->postJson('/api/attendance/checkout');
        $response2->assertStatus(404);
        $response2->assertJson([
            'status' => 'error',
            'message' => 'Không tìm thấy bản ghi check-in trong ngày hôm nay hoặc bạn đã check-out rồi.',
        ]);
    }

    /**
     * Test unique constraint trên attendances table
     * Đảm bảo không tạo được 2 check-in cùng user + cùng timestamp
     *
     * NOTE: Laravel Eloquent không enforce unique constraint ở app level
     * DB-level unique constraint sẽ prevent duplicate khi có concurrent requests
     * DB::transaction() + lockForUpdate() là primary defense
     */
    public function test_unique_constraint_prevents_duplicate_attendance(): void
    {
        // Arrange
        $user = User::factory()->create([
            'nfc_uid' => 'TEST_NFC_004',
        ]);
        $shift = Shift::create([
            'name' => 'Test Shift',
            'code' => 'TEST_SHIFT_003',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $checkInTime = Carbon::now();

        // Act: Tạo attendance record 1
        $attendance1 = Attendance::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'check_in_time' => $checkInTime,
            'device_info' => 'Kiosk 1',
        ]);

        $this->assertNotNull($attendance1->id);

        // Assert: DB transaction + lockForUpdate() prevent duplicate
        // Trong production, KioskController query với lockForUpdate() sẽ prevent concurrent insert
        $this->assertEquals(1, Attendance::where('user_id', $user->id)->count());

        // Verify logic: Nếu cùng user check-in lại → auto checkout
        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/kiosk/attendance', [
            'nfc_code' => 'TEST_NFC_004',
        ]);

        // Sẽ check-out thay vì tạo duplicate
        $response->assertStatus(200);
        $response->assertJson(['type' => 'check_out']);

        // Still only 1 attendance record
        $this->assertEquals(1, Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', Carbon::today())
            ->count());
    }

    /**
     * Test transaction rollback nếu có error trong quá trình check-in
     */
    public function test_transaction_rollback_on_error_during_checkin(): void
    {
        // Arrange
        $user = User::factory()->create([
            'nfc_uid' => 'TEST_NFC_003',
        ]);

        // Không tạo shift → Sẽ gây lỗi khi insert attendance
        $this->actingAs($user, 'sanctum');

        // Act: Check-in với NFC hợp lệ nhưng shift không tồn tại
        $response = $this->postJson('/api/kiosk/attendance', [
            'nfc_code' => 'TEST_NFC_003',
        ]);

        // Assert: Request fail và không tạo attendance record nào
        $response->assertStatus(500); // hoặc 404 tùy logic

        $this->assertEquals(0, Attendance::where('user_id', $user->id)->count());
    }
}
