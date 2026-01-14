<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Services\GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class GoogleSheetsExportTest extends TestCase
{
    // Không dùng migrate:fresh; dùng RefreshDatabase theo cấu hình test của dự án
    use RefreshDatabase;

    public function test_manager_can_export_attendance_statistics_to_google_sheets(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);

        config([
            'services.google_sheets.spreadsheet_id' => 'TEST_SPREADSHEET_ID',
            'services.google_sheets.statistics_sheet' => 'Statistics',
        ]);

        $shift = Shift::create([
            'name' => 'Ca Sáng',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'code' => 'SHIFT_TEST_1',
        ]);

        Attendance::create([
            'user_id' => $staff->id,
            'shift_id' => $shift->id,
            'check_in_time' => now()->subHours(1),
            'device_info' => 'test',
        ]);

        Sanctum::actingAs($manager);

        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('ensureSheetExists')->andReturnNull();
        $mock->shouldReceive('appendHeaderIfEmpty')->andReturnNull();
        $mock->shouldReceive('deleteRowsByMeta')->andReturn(0);
        $mock->shouldReceive('appendRows')->andReturn([
            'updatedRows' => 1,
            'sheetName' => 'Statistics',
        ]);
        $this->app->instance(GoogleSheetsService::class, $mock);

        $res = $this->postJson('/api/google-sheets/attendance-statistics', [
            'period' => 'weekly',
            'year' => (int) now()->year,
            'week' => (int) now()->isoWeek,
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('google_sheets_exports', [
            'user_id' => $manager->id,
            'type' => 'attendance-statistics',
        ]);
    }

    public function test_manager_can_export_payroll_to_google_sheets(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff', 'hourly_rate' => 10000]);

        config([
            'services.google_sheets.spreadsheet_id' => 'TEST_SPREADSHEET_ID',
            'services.google_sheets.payroll_sheet' => 'Payroll',
        ]);

        $shift = Shift::create([
            'name' => 'Ca Chiều',
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
            'code' => 'SHIFT_TEST_2',
        ]);

        $att = Attendance::create([
            'user_id' => $staff->id,
            'shift_id' => $shift->id,
            'check_in_time' => now()->subHours(6),
            'device_info' => 'test',
        ]);
        $att->check_out_time = now()->subHours(1);
        $att->work_hours = 5.0;
        $att->save();

        Sanctum::actingAs($manager);

        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('ensureSheetExists')->andReturnNull();
        $mock->shouldReceive('appendHeaderIfEmpty')->andReturnNull();
        $mock->shouldReceive('deleteRowsByMeta')->andReturn(0);
        $mock->shouldReceive('appendRows')->andReturn([
            'updatedRows' => 1,
            'sheetName' => 'Payroll',
        ]);
        $this->app->instance(GoogleSheetsService::class, $mock);

        $res = $this->postJson('/api/google-sheets/payroll', [
            'period' => 'monthly',
            'year' => (int) now()->year,
            'month' => (int) now()->month,
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('google_sheets_exports', [
            'user_id' => $manager->id,
            'type' => 'payroll',
        ]);
    }

    public function test_append_mode_does_not_delete_rows(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);

        config([
            'services.google_sheets.spreadsheet_id' => 'TEST_SPREADSHEET_ID',
            'services.google_sheets.statistics_sheet' => 'Statistics',
            'services.google_sheets.export_mode' => 'replace',
        ]);

        $shift = Shift::create([
            'name' => 'Ca Sáng',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'code' => 'SHIFT_TEST_3',
        ]);

        Attendance::create([
            'user_id' => $staff->id,
            'shift_id' => $shift->id,
            'check_in_time' => now()->subHours(1),
            'device_info' => 'test',
        ]);

        Sanctum::actingAs($manager);

        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('ensureSheetExists')->andReturnNull();
        $mock->shouldReceive('appendHeaderIfEmpty')->andReturnNull();
        $mock->shouldReceive('deleteRowsByMeta')->never();
        $mock->shouldReceive('appendRows')->andReturn([
            'updatedRows' => 1,
            'sheetName' => 'Statistics',
        ]);
        $this->app->instance(GoogleSheetsService::class, $mock);

        $res = $this->postJson('/api/google-sheets/attendance-statistics', [
            'period' => 'weekly',
            'year' => (int) now()->year,
            'week' => (int) now()->isoWeek,
            'mode' => 'append',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('status', 'success');
        $res->assertJsonPath('mode', 'append');

        $this->assertDatabaseHas('google_sheets_exports', [
            'user_id' => $manager->id,
            'type' => 'attendance-statistics',
            'mode' => 'append',
        ]);
    }
}
