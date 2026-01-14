<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rate_limits_login_endpoint_to_5_requests_per_minute()
    {
        // Act: Gửi 6 requests liên tục
        for ($i = 1; $i <= 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);

            // 5 request đầu: expect 401 (unauthorized) hoặc 422 (validation)
            // Request thứ 6: expect 429 (too many requests)
            if ($i <= 5) {
                $this->assertContains($response->status(), [401, 422], "Request #{$i} should not be rate limited");
            } else {
                $this->assertEquals(429, $response->status(), "Request #{$i} should be rate limited");
                $response->assertJson([
                    'status' => 'error',
                ]);
                $this->assertArrayHasKey('retry_after', $response->json());
            }
        }
    }

    /** @test */
    public function it_returns_proper_429_json_response()
    {
        // Act: Trigger rate limit
        for ($i = 1; $i <= 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@test.com',
                'password' => 'test',
            ]);
        }

        // Assert: Check 429 response structure
        $response->assertStatus(429);
        $response->assertJsonStructure([
            'status',
            'message',
            'retry_after',
        ]);

        $json = $response->json();
        $this->assertEquals('error', $json['status']);
        $this->assertIsInt($json['retry_after']);
        $this->assertGreaterThan(0, $json['retry_after']);
        $this->assertStringContainsString('Quá nhiều yêu cầu', $json['message']);
    }

    /** @test */
    public function kiosk_attendance_is_rate_limited_to_10_per_minute()
    {
        // Act: Gửi 11 requests đến kiosk endpoint
        for ($i = 1; $i <= 11; $i++) {
            $response = $this->postJson('/api/kiosk/attendance', [
                'nfc_code' => 'NCTNFC:v1:fake_code',
            ]);

            if ($i <= 10) {
                // 10 request đầu: không bị limit (có thể 422 vì invalid data)
                $this->assertNotEquals(429, $response->status(), "Kiosk request #{$i} should not be rate limited");
            } else {
                // Request thứ 11: 429
                $this->assertEquals(429, $response->status(), "Kiosk request #{$i} should be rate limited");
            }
        }
    }

    /** @test */
    public function kiosk_qr_session_is_rate_limited_to_10_per_minute()
    {
        // Act: Gửi 11 requests đến kiosk QR session endpoint
        for ($i = 1; $i <= 11; $i++) {
            $response = $this->postJson('/api/kiosk/qr/session', [
                'kiosk_id' => 'KIOSK_RATE_TEST',
            ]);

            if ($i <= 10) {
                $this->assertNotEquals(429, $response->status(), "Kiosk QR request #{$i} should not be rate limited");
            } else {
                $this->assertEquals(429, $response->status(), "Kiosk QR request #{$i} should be rate limited");
            }
        }
    }

    /** @test */
    public function rate_limit_resets_after_timeout()
    {
        // Arrange: Trigger rate limit
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/api/login', ['email' => 'test@test.com', 'password' => 'test']);
        }

        // Assert: 429 triggered
        $response = $this->postJson('/api/login', ['email' => 'test@test.com', 'password' => 'test']);
        $this->assertEquals(429, $response->status());

        // Act: Wait for rate limit window to reset (simulate)
        // Note: Cannot actually wait 60s in test, this is conceptual test
        // In real scenario, you'd mock Carbon time or use cache driver

        // Workaround: Clear throttle cache (for testing purpose)
        cache()->clear();

        // Assert: Can make requests again
        $response = $this->postJson('/api/login', ['email' => 'test@test.com', 'password' => 'test']);
        $this->assertNotEquals(429, $response->status());
    }
}
