<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PdfExportTest extends TestCase
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
    }

    public function test_manager_can_export_weekly_attendance_pdf(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->get('/api/reports/weekly/pdf?year=2026&week=1');

        $response->assertOk();

        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_manager_can_export_monthly_payroll_pdf(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->get('/api/payroll/report/pdf?year=2026&month=1');

        $response->assertOk();

        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF', $content);
    }
}
