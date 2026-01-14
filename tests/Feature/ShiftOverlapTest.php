<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * SPRINT 2: Test Shift Overlap Prevention
 * Verify ca làm việc không bị trùng khung giờ
 */
class ShiftOverlapTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Tạo admin user
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);
    }

    /** @test */
    public function it_prevents_creating_overlapping_same_day_shifts()
    {
        // Tạo Ca Sáng: 08:00 - 12:00
        Shift::create([
            'name' => 'Ca Sáng',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'code' => 'MORNING',
        ]);

        // Thử tạo Ca Trùng: 10:00 - 14:00 (overlap với Ca Sáng)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/shifts', [
                'name' => 'Ca Trùng',
                'start_time' => '10:00:00',
                'end_time' => '14:00:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Khung giờ ca làm việc bị trùng với ca khác. Vui lòng chọn thời gian khác.',
            ]);

        $this->assertDatabaseCount('shifts', 1); // Chỉ có Ca Sáng
    }

    /** @test */
    public function it_allows_creating_non_overlapping_same_day_shifts()
    {
        // Tạo Ca Sáng: 08:00 - 12:00
        Shift::create([
            'name' => 'Ca Sáng',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'code' => 'MORNING',
        ]);

        // Tạo Ca Chiều: 13:00 - 17:00 (KHÔNG overlap)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/shifts', [
                'name' => 'Ca Chiều',
                'start_time' => '13:00:00',
                'end_time' => '17:00:00',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Ca Chiều']);

        $this->assertDatabaseCount('shifts', 2);
    }

    /** @test */
    public function it_allows_creating_overnight_shift()
    {
        // Tạo Ca Đêm: 22:00 - 02:00 (qua đêm)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/shifts', [
                'name' => 'Ca Đêm',
                'start_time' => '22:00:00',
                'end_time' => '02:00:00',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Ca Đêm']);

        // Verify ca đã được tạo
        $this->assertDatabaseHas('shifts', [
            'name' => 'Ca Đêm',
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
        ]);
    }

    /** @test */
    public function it_prevents_overlapping_overnight_shifts()
    {
        // Tạo Ca Đêm 1: 22:00 - 02:00
        Shift::create([
            'name' => 'Ca Đêm 1',
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
            'code' => 'NIGHT1',
        ]);

        // Thử tạo Ca Đêm 2: 23:00 - 03:00 (overlap)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/shifts', [
                'name' => 'Ca Đêm 2',
                'start_time' => '23:00:00',
                'end_time' => '03:00:00',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('shifts', 1);
    }

    /** @test */
    public function it_allows_same_day_shift_and_overnight_shift_without_overlap()
    {
        // Tạo Ca Sáng: 08:00 - 17:00
        Shift::create([
            'name' => 'Ca Sáng',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'code' => 'MORNING',
        ]);

        // Tạo Ca Đêm: 20:00 - 02:00 (KHÔNG overlap với Ca Sáng)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/shifts', [
                'name' => 'Ca Đêm',
                'start_time' => '20:00:00',
                'end_time' => '02:00:00',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('shifts', 2);
    }

    /** @test */
    public function it_prevents_same_day_shift_overlapping_with_overnight_shift_morning_part()
    {
        // Tạo Ca Đêm: 22:00 - 06:00
        Shift::create([
            'name' => 'Ca Đêm',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'code' => 'NIGHT',
        ]);

        // Thử tạo Ca Sáng Sớm: 05:00 - 09:00 (overlap phần sáng của Ca Đêm)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/shifts', [
                'name' => 'Ca Sáng Sớm',
                'start_time' => '05:00:00',
                'end_time' => '09:00:00',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('shifts', 1);
    }

    /** @test */
    public function it_allows_updating_shift_without_creating_overlap()
    {
        // Tạo Ca Sáng: 08:00 - 12:00
        $shift = Shift::create([
            'name' => 'Ca Sáng',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'code' => 'MORNING',
        ]);

        // Update thời gian Ca Sáng: 07:00 - 12:00 (không overlap với chính nó)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/shifts/{$shift->id}", [
                'start_time' => '07:00:00',
                'end_time' => '12:00:00',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'start_time' => '07:00:00',
            'end_time' => '12:00:00',
        ]);
    }

    /** @test */
    public function it_prevents_updating_shift_to_create_overlap()
    {
        // Tạo Ca Sáng: 08:00 - 12:00
        $shift1 = Shift::create([
            'name' => 'Ca Sáng',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'code' => 'MORNING',
        ]);

        // Tạo Ca Chiều: 13:00 - 17:00
        $shift2 = Shift::create([
            'name' => 'Ca Chiều',
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
            'code' => 'AFTERNOON',
        ]);

        // Thử update Ca Chiều thành 11:00 - 15:00 (overlap với Ca Sáng)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/shifts/{$shift2->id}", [
                'start_time' => '11:00:00',
                'end_time' => '15:00:00',
            ]);

        $response->assertStatus(422);

        // Verify Ca Chiều không đổi
        $this->assertDatabaseHas('shifts', [
            'id' => $shift2->id,
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
        ]);
    }
}
